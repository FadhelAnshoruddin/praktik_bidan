<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Registration;
use App\Models\SpecialClosure;
use App\Models\VisitType;
use App\Models\WeeklySchedule;
use App\Services\PracticeSchedule;
use App\Support\PatientCrypto;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PublicController extends Controller
{
    public function __construct(
        private readonly PracticeSchedule $schedule,
        private readonly PatientCrypto $crypto,
    ) {}

    public function home()
    {
        return view('public.home', [
            'profile' => Profile::query()->findOrFail(1),
            'open' => $this->schedule->isOpen(),
            'schedules' => $this->schedulePayload(),
            'visitTypes' => VisitType::query()->where('is_active', true)->orderBy('id')->get(),
        ]);
    }

    public function create()
    {
        $booking = $this->schedule->todayBookingData();

        return view('public.registration', [
            'profile' => Profile::query()->findOrFail(1),
            'open' => $booking['isOpen'],
            'visitTypes' => VisitType::query()->where('is_active', true)->orderBy('id')->get(),
            'booking' => $booking,
        ]);
    }

    public function todaySlots()
    {
        return response()
            ->json($this->schedule->todayBookingData())
            ->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $today = now('Asia/Jakarta')->toDateString();
        $values = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9\s().-]{9,20}$/'],
            'date' => ['required', 'date_format:Y-m-d', Rule::in([$today])],
            'appointment_time' => ['required', 'date_format:H:i'],
            'type' => ['required', 'string', 'max:80', Rule::exists('visit_types', 'name')->where('is_active', true)],
            'complaint' => ['nullable', 'string', 'max:1000'],
        ], [
            'name.min' => 'Nama lengkap minimal 3 karakter.',
            'phone.regex' => 'Masukkan nomor telepon/WhatsApp yang valid.',
            'type.exists' => 'Jenis pemeriksaan tidak tersedia.',
            'date.in' => 'Tanggal pendaftaran telah berganti. Muat ulang halaman untuk memilih jadwal hari ini.',
        ]);

        $values['name'] = trim($values['name']);
        $values['phone'] = trim($values['phone']);
        $values['complaint'] = trim($values['complaint'] ?? '');

        if (!$this->schedule->isOpen()) {
            throw ValidationException::withMessages(['date' => 'Praktik sedang tidak menerima pendaftaran.']);
        }

        $booking = $this->schedule->todayBookingData();
        $selectedSlot = collect($booking['slots'])->firstWhere('time', $values['appointment_time']);
        if (!$booking['isScheduled'] || !$selectedSlot) {
            throw ValidationException::withMessages(['date' => 'Jadwal praktik hari ini tutup atau belum tersedia.']);
        }
        if (!$selectedSlot['available']) {
            throw ValidationException::withMessages(['appointment_time' => 'Jam tersebut sudah dipesan atau tidak lagi tersedia. Silakan pilih slot lain.']);
        }

        try {
            $registration = DB::transaction(function () use ($values): Registration {
                $profile = Profile::query()->lockForUpdate()->findOrFail(1);
                $date = CarbonImmutable::createFromFormat('!Y-m-d', $values['date'], 'Asia/Jakarta');
                $schedule = WeeklySchedule::query()->lockForUpdate()->find($date->dayOfWeek);

                if (!$this->schedule->isOpen() || !$schedule?->is_open || SpecialClosure::query()->whereDate('date', $values['date'])->exists()) {
                    throw ValidationException::withMessages(['date' => 'Jadwal praktik tutup pada tanggal tersebut.']);
                }

                $selectedSlot = collect($this->schedule->hourlySlots($date, $schedule, $profile))
                    ->firstWhere('time', $values['appointment_time']);
                if (!$selectedSlot || !$selectedSlot['available']) {
                    throw ValidationException::withMessages(['appointment_time' => 'Jam tersebut baru saja dipesan atau tidak lagi tersedia. Silakan pilih slot lain.']);
                }

                $queueNumber = (int) Registration::query()->whereDate('visit_date', $values['date'])->max('queue_number') + 1;

                $code = $this->crypto->createCode($values['date']);

                return Registration::query()->create([
                    'public_code_hash' => $this->crypto->hashCode($code),
                    'public_code_encrypted' => $this->crypto->encrypt($code),
                    'patient_name_encrypted' => $this->crypto->encrypt($values['name']),
                    'phone_encrypted' => $this->crypto->encrypt($values['phone']),
                    'visit_type' => $values['type'],
                    'visit_date' => $values['date'],
                    'appointment_time' => $values['appointment_time'],
                    'complaint_encrypted' => $this->crypto->encrypt($values['complaint']),
                    'queue_number' => $queueNumber,
                    'status' => 'WAITING',
                ]);
            }, 3);
        } catch (QueryException $exception) {
            if (!str_contains($exception->getMessage(), 'registrations_active_appointment_time_unique')) {
                throw $exception;
            }

            throw ValidationException::withMessages([
                'appointment_time' => 'Jam tersebut baru saja dipesan. Silakan pilih slot lain.',
            ]);
        }

        $request->session()->put('last_registration_code', $this->crypto->decrypt($registration->public_code_encrypted));
        $request->session()->put('lookup_code', $this->crypto->decrypt($registration->public_code_encrypted));

        return redirect()->route('registration.success');
    }

    public function success(Request $request)
    {
        $registration = $this->findByCode((string) $request->session()->get('last_registration_code'));

        return view('public.success', [
            'registration' => $registration
                ? $this->registrationDetails($this->crypto->decrypt($registration->public_code_encrypted))
                : null,
            'profile' => Profile::query()->findOrFail(1),
        ]);
    }

    public function check(Request $request)
    {
        $code = (string) $request->session()->get('lookup_code', '');
        $registration = $code === '' ? null : $this->registrationDetails($code);

        return view('public.check', [
            'code' => $code,
            'registration' => $registration,
            'profile' => Profile::query()->findOrFail(1),
        ]);
    }

    public function lookup(Request $request)
    {
        $values = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $code = strtoupper(trim($values['code']));

        if (!$this->findByCode($code)) {
            return back()->withErrors(['code' => 'Kode pendaftaran tidak ditemukan.'])->withInput();
        }

        $request->session()->put('lookup_code', $code);

        return redirect()->route('queue.check');
    }

    private function schedulePayload(): array
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

        return WeeklySchedule::query()->orderBy('day_of_week')->get()->map(fn (WeeklySchedule $schedule): array => [
            'day' => $schedule->day_of_week,
            'dayName' => $days[$schedule->day_of_week],
            'isOpen' => $schedule->is_open,
            'openTime' => $schedule->open_time ? substr($schedule->open_time, 0, 5) : '',
            'closeTime' => $schedule->close_time ? substr($schedule->close_time, 0, 5) : '',
        ])->all();
    }

    private function findByCode(string $code): ?Registration
    {
        if (!preg_match('/^BID-\d{8}-[A-F0-9]{10}$/', strtoupper($code))) {
            return null;
        }

        return Registration::query()->where('public_code_hash', $this->crypto->hashCode($code))->first();
    }

    private function registrationDetails(string $code): ?array
    {
        $row = $this->findByCode($code);
        if (!$row) {
            return null;
        }

        $ahead = Registration::query()
            ->whereDate('visit_date', $row->visit_date)
            ->where('queue_number', '<', $row->queue_number)
            ->whereIn('status', ['WAITING', 'CALLED', 'IN_EXAMINATION'])
            ->count();
        $current = Registration::query()
            ->whereDate('visit_date', $row->visit_date)
            ->whereIn('status', ['CALLED', 'IN_EXAMINATION'])
            ->orderBy('queue_number')
            ->value('queue_number');
        $weekday = CarbonImmutable::parse($row->visit_date, 'Asia/Jakarta')->dayOfWeek;
        $schedule = WeeklySchedule::query()->find($weekday);
        $profile = Profile::query()->findOrFail(1);

        return [
            'id' => $row->id,
            'code' => $this->crypto->decrypt($row->public_code_encrypted),
            'name' => $this->crypto->decrypt($row->patient_name_encrypted),
            'type' => $row->visit_type,
            'date' => $row->visit_date->toDateString(),
            'appointmentTime' => $row->appointment_time ? substr($row->appointment_time, 0, 5) : null,
            'queue' => $row->queue_number,
            'status' => $row->status,
            'ahead' => $ahead,
            'currentlyCalled' => $current,
            'estimatedTime' => $row->appointment_time
                ? substr($row->appointment_time, 0, 5)
                : $this->schedule->estimateTime($ahead, $profile->duration, $schedule?->open_time, $row->visit_date->toDateString()),
        ];
    }
}