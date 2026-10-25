<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Registration;
use App\Models\SpecialClosure;
use App\Models\WeeklySchedule;
use App\Services\PracticeSchedule;
use App\Support\PatientCrypto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    private const TRANSITIONS = [
        'WAITING' => ['CALLED', 'CANCELLED', 'NO_SHOW'],
        'CALLED' => ['CALLED', 'IN_EXAMINATION', 'CANCELLED', 'NO_SHOW'],
        'IN_EXAMINATION' => ['COMPLETED'],
        'COMPLETED' => [],
        'CANCELLED' => [],
        'NO_SHOW' => [],
    ];

    public function __construct(private readonly PatientCrypto $crypto) {}

    public function login()
    {
        if (Auth::check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function authenticate(Request $request)
    {
        $values = $request->validate([
            'email' => ['required', 'email', 'max:254'],
            'password' => ['required', 'string', 'max:200'],
        ]);

        if (!Auth::attempt(['email' => strtolower($values['email']), 'password' => $values['password']])) {
            $user = DB::table('users')->where('email', strtolower($values['email']))->first();
            $this->audit('LOGIN_FAILED', 'user', $user?->id, $user?->id);

            return back()->withErrors(['email' => 'Email atau kata sandi tidak sesuai.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        $this->audit('ADMIN_LOGIN', 'user', Auth::id());

        return redirect()->route('admin.dashboard');
    }

    public function dashboard(Request $request, PracticeSchedule $schedule)
    {
        $days = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
        $registrations = Registration::query()
            ->orderByDesc('visit_date')
            ->orderByDesc('queue_number')
            ->limit(500)
            ->get()
            ->map(fn (Registration $row): array => $this->registrationData($row, true))
            ->all();

        return view('admin.dashboard', [
            'tab' => $request->query('tab', 'queue'),
            'profile' => Profile::query()->findOrFail(1),
            'open' => $schedule->isOpen(),
            'schedules' => WeeklySchedule::query()->orderBy('day_of_week')->get()->map(fn (WeeklySchedule $item): array => [
                'day' => $item->day_of_week,
                'dayName' => $days[$item->day_of_week],
                'isOpen' => $item->is_open,
                'openTime' => $item->open_time ? substr($item->open_time, 0, 5) : '',
                'closeTime' => $item->close_time ? substr($item->close_time, 0, 5) : '',
            ])->all(),
            'registrations' => $registrations,
            'closures' => SpecialClosure::query()->orderBy('date')->get(),
            'today' => now('Asia/Jakarta')->toDateString(),
        ]);
    }

    public function logout(Request $request)
    {
        $userId = Auth::id();
        $this->audit('ADMIN_LOGOUT', 'user', $userId, $userId);
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function updateRegistrationStatus(Request $request, int $id)
    {
        $values = $request->validate(['status' => ['required', 'in:WAITING,CALLED,IN_EXAMINATION,COMPLETED,CANCELLED,NO_SHOW']]);
        $registration = Registration::query()->findOrFail($id);
        if (!in_array($values['status'], self::TRANSITIONS[$registration->status] ?? [], true)) {
            throw ValidationException::withMessages(['status' => "Status {$registration->status} tidak dapat diubah menjadi {$values['status']}."]);
        }

        $timestamp = [
            'CALLED' => 'called_at',
            'IN_EXAMINATION' => 'started_at',
            'COMPLETED' => 'completed_at',
            'NO_SHOW' => 'no_show_at',
        ][$values['status']] ?? null;
        $registration->status = $values['status'];
        if ($timestamp) {
            $registration->{$timestamp} = now();
        }
        $registration->save();
        $this->audit('QUEUE_'.$values['status'], 'registration', $registration->id);

        return back()->with('notice', 'Status antrean diperbarui.');
    }

    public function updateProfile(Request $request)
    {
        $values = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'title' => ['nullable', 'string', 'max:80'],
            'motto' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'],
            'photo_url' => ['nullable', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && !str_starts_with(strtolower($value), 'https://')) {
                    $fail('Tautan foto harus menggunakan HTTPS.');
                }
            }],
            'phone' => ['nullable', 'string', 'max:24'],
            'address' => ['required', 'string', 'min:5', 'max:300'],
            'maps_url' => ['nullable', 'url', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                if ($value && !str_starts_with(strtolower($value), 'https://')) {
                    $fail('Tautan peta harus menggunakan HTTPS.');
                }
            }],
            'duration' => ['required', 'integer', 'min:5', 'max:180'],
            'capacity' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        Profile::query()->findOrFail(1)->update([
            'name' => trim($values['name']),
            'title' => $values['title'] ?? '',
            'motto' => $values['motto'] ?? '',
            'description' => $values['description'] ?? '',
            'photo_url' => $values['photo_url'] ?? '',
            'phone' => $values['phone'] ?? '',
            'address' => trim($values['address']),
            'maps_url' => $values['maps_url'] ?? '',
            'duration' => $values['duration'],
            'capacity' => $values['capacity'],
        ]);
        $this->audit('UPDATE_PROFILE', 'profile', 1);

        return back()->with('notice', 'Profil praktik tersimpan.');
    }

    public function updatePracticeStatus(Request $request)
    {
        $values = $request->validate(['is_open' => ['required', 'boolean']]);
        if ((bool) $values['is_open'] && !WeeklySchedule::query()->where('is_open', true)->exists()) {
            throw ValidationException::withMessages(['is_open' => 'Atur minimal satu jadwal praktik sebelum membuka pendaftaran.']);
        }

        DB::table('system_settings')->updateOrInsert(
            ['key' => 'practice_open'],
            ['value' => $values['is_open'] ? 'true' : 'false'],
        );
        $this->audit($values['is_open'] ? 'OPEN_PRACTICE' : 'CLOSE_PRACTICE', 'practice', 1);

        return back()->with('notice', 'Status pendaftaran diperbarui.');
    }

    public function updateSchedules(Request $request)
    {
        $values = $request->validate([
            'schedules' => ['required', 'array', 'size:7'],
            'schedules.*.day' => ['required', 'integer', 'between:0,6', 'distinct'],
            'schedules.*.is_open' => ['nullable', 'boolean'],
            'schedules.*.open_time' => ['nullable', 'date_format:H:i', 'required_if:schedules.*.is_open,1'],
            'schedules.*.close_time' => ['nullable', 'date_format:H:i', 'required_if:schedules.*.is_open,1'],
        ]);

        foreach ($values['schedules'] as $item) {
            $isOpen = (bool) ($item['is_open'] ?? false);
            if ($isOpen && (empty($item['open_time']) || empty($item['close_time']) || $item['open_time'] >= $item['close_time'])) {
                throw ValidationException::withMessages(['schedules' => 'Jam buka dan tutup harus lengkap dan berurutan.']);
            }
        }

        DB::transaction(function () use ($values): void {
            foreach ($values['schedules'] as $item) {
                $isOpen = (bool) ($item['is_open'] ?? false);
                WeeklySchedule::query()->where('day_of_week', $item['day'])->update([
                    'is_open' => $isOpen,
                    'open_time' => $isOpen ? $item['open_time'] : null,
                    'close_time' => $isOpen ? $item['close_time'] : null,
                ]);
            }
        });
        $this->audit('UPDATE_SCHEDULE', 'weekly_schedule');

        return back()->with('notice', 'Jadwal mingguan tersimpan.');
    }

    public function storeClosure(Request $request)
    {
        $values = $request->validate([
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'unique:special_closures,date'],
            'reason' => ['required', 'string', 'min:2', 'max:200'],
        ], ['date.unique' => 'Tanggal ini sudah tercatat sebagai hari libur.']);

        $closure = SpecialClosure::query()->create($values);
        $this->audit('ADD_SPECIAL_CLOSURE', 'special_closure', $closure->id);

        return back()->with('notice', 'Hari libur tersimpan.');
    }

    public function deleteClosure(int $id)
    {
        $closure = SpecialClosure::query()->findOrFail($id);
        $closure->delete();
        $this->audit('DELETE_SPECIAL_CLOSURE', 'special_closure', $id);

        return back()->with('notice', 'Hari libur dihapus.');
    }

    private function registrationData(Registration $row, bool $includePrivate): array
    {
        $data = [
            'id' => $row->id,
            'code' => $this->crypto->decrypt($row->public_code_encrypted),
            'name' => $this->crypto->decrypt($row->patient_name_encrypted),
            'type' => $row->visit_type,
            'date' => $row->visit_date->toDateString(),
            'appointmentTime' => $row->appointment_time ? substr($row->appointment_time, 0, 5) : null,
            'queue' => $row->queue_number,
            'status' => $row->status,
            'createdAt' => $row->created_at?->toDateTimeString(),
        ];

        if ($includePrivate) {
            $data['phone'] = $this->crypto->decrypt($row->phone_encrypted);
            $data['complaint'] = $this->crypto->decrypt($row->complaint_encrypted);
        }

        return $data;
    }

    private function audit(string $action, string $entityType, int|string|null $entityId = null, int|string|null $userId = null): void
    {
        DB::table('audit_logs')->insert([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId === null ? null : (string) $entityId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}