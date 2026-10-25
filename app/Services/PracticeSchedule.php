<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\Registration;
use App\Models\SpecialClosure;
use App\Models\WeeklySchedule;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class PracticeSchedule
{
    public function isOpen(): bool
    {
        return DB::table('system_settings')->where('key', 'practice_open')->value('value') === 'true';
    }

    public function todayBookingData(): array
    {
        $date = CarbonImmutable::now('Asia/Jakarta')->startOfDay();
        $profile = Profile::query()->findOrFail(1);
        $schedule = WeeklySchedule::query()->find($date->dayOfWeek);
        $isOpen = $this->isOpen();
        $isScheduled = (bool) $schedule?->is_open;
        $isClosedForDate = SpecialClosure::query()->whereDate('date', $date->toDateString())->exists();
        $slots = $isOpen && $isScheduled && !$isClosedForDate && $schedule
            ? $this->hourlySlots($date, $schedule, $profile)
            : [];

        return [
            'date' => $date->toDateString(),
            'dateLabel' => $date->locale('id')->translatedFormat('l, j F Y'),
            'isOpen' => $isOpen,
            'isScheduled' => $isScheduled && !$isClosedForDate,
            'slots' => $slots,
        ];
    }

    public function hourlySlots(CarbonImmutable $date, WeeklySchedule $schedule, Profile $profile): array
    {
        $dateValue = $date->toDateString();
        $reservedTimes = Registration::query()
            ->whereDate('visit_date', $dateValue)
            ->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->whereNotNull('appointment_time')
            ->pluck('appointment_time')
            ->map(fn ($time): int => $this->minutes((string) $time))
            ->all();
        $bookingCount = Registration::query()
            ->whereDate('visit_date', $dateValue)
            ->whereNotIn('status', ['CANCELLED', 'NO_SHOW'])
            ->count();
        $capacityAvailable = $bookingCount < $profile->capacity;

        $opening = $this->minutes($schedule->open_time);
        $closing = $this->minutes($schedule->close_time);
        $firstSlot = (int) ceil($opening / 60) * 60;
        $now = CarbonImmutable::now('Asia/Jakarta');
        $currentSeconds = $now->hour * 3600 + $now->minute * 60 + $now->second;

        $slots = [];
        for ($minute = $firstSlot; $minute + $profile->duration <= $closing; $minute += 60) {
            $time = sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60);
            $startSeconds = $minute * 60;
            $overlapsBooking = collect($reservedTimes)->contains(fn (int $reservedStart): bool =>
                $reservedStart < $minute + $profile->duration && $reservedStart + $profile->duration > $minute
            );
            $isPast = $dateValue === $now->toDateString() && $startSeconds < $currentSeconds;
            $reason = match (true) {
                $isPast => 'Waktu sudah lewat',
                $overlapsBooking => 'Jadwal sudah diambil',
                !$capacityAvailable => 'Kuota hari ini penuh',
                default => '',
            };

            $slots[] = [
                'time' => $time,
                'available' => $reason === '',
                'reason' => $reason,
            ];
        }

        if (!$capacityAvailable && $slots === []) {
            return [];
        }

        return $slots;
    }

    public function estimateTime(int $position, int $duration, ?string $openTime, string $visitDate): string
    {
        $visit = CarbonImmutable::createFromFormat('!Y-m-d', $visitDate, 'Asia/Jakarta');
        $minutes = max($this->minutes($openTime), $this->minutesForNow($visit)) + $position * $duration;

        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    public function minutes(?string $value): int
    {
        [$hours, $minutes] = array_pad(array_map('intval', explode(':', substr((string) $value, 0, 5))), 2, 0);

        return $hours * 60 + $minutes;
    }

    private function minutesForNow(CarbonImmutable $date): int
    {
        return $date->toDateString() === CarbonImmutable::now('Asia/Jakarta')->toDateString()
            ? $this->minutes(CarbonImmutable::now('Asia/Jakarta')->format('H:i'))
            : 0;
    }
}