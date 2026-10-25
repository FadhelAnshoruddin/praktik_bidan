<?php

namespace App\Console\Commands;

use App\Models\Registration;
use App\Models\User;
use App\Support\PatientCrypto;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportSqlite extends Command
{
    protected $signature = 'bidan:import-sqlite {path=data/praktik-bidan.sqlite}';

    protected $description = 'Salin data SQLite lama ke MySQL tanpa mengubah database sumber';

    public function handle(PatientCrypto $crypto): int
    {
        $path = base_path($this->argument('path'));
        if (!is_file($path)) {
            $this->error("Database SQLite tidak ditemukan: {$path}");

            return self::FAILURE;
        }

        if (!$this->mysqlIsFresh()) {
            $this->error('MySQL bukan instalasi awal yang masih kosong. Import dihentikan agar data praktik tidak tertimpa.');

            return self::FAILURE;
        }

        try {
            $sqlite = new PDO('sqlite:'.$path, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $sqlite->exec('PRAGMA query_only = ON');
            $sqlite->beginTransaction();
            $read = static fn (string $table): array => $sqlite->query("SELECT * FROM {$table}")->fetchAll(PDO::FETCH_ASSOC);
            $profiles = $read('profiles');
            $schedules = $read('weekly_schedules');
            $closures = $read('special_closures');
            $visitTypes = $read('visit_types');
            $settings = $read('system_settings');
            $users = $read('users');
            $registrations = $read('registrations');
            $auditLogs = $read('audit_logs');

            foreach ($registrations as $row) {
                $publicCode = $crypto->decrypt($row['public_code_encrypted']);
                if (!hash_equals($row['public_code_hash'], $crypto->hashCode($publicCode))) {
                    throw new \RuntimeException('Hash kode pendaftaran tidak cocok; import dihentikan.');
                }
                foreach (['public_code_encrypted', 'patient_name_encrypted', 'phone_encrypted', 'complaint_encrypted'] as $column) {
                    $crypto->decrypt($row[$column]);
                }
            }
            $sqlite->commit();

            DB::transaction(function () use ($profiles, $schedules, $closures, $visitTypes, $settings, $users, $registrations, $auditLogs): void {
                foreach ($profiles as $row) {
                    $this->put('profiles', ['id' => $row['id']], $row);
                }
                foreach ($schedules as $row) {
                    $this->put('weekly_schedules', ['day_of_week' => $row['day_of_week']], $row);
                }
                foreach ($closures as $row) {
                    $this->put('special_closures', ['id' => $row['id']], $row);
                }
                foreach ($visitTypes as $row) {
                    $this->put('visit_types', ['id' => $row['id']], $row);
                }
                foreach ($settings as $row) {
                    $this->put('system_settings', ['key' => $row['key']], $row);
                }
                foreach ($users as $row) {
                    $row['password'] = $row['password_hash'];
                    if (str_starts_with($row['password'], '$2b$')) {
                        $row['password'] = '$2y$'.substr($row['password'], 4);
                    }
                    unset($row['password_hash']);
                    $row['updated_at'] = $row['created_at'] ?? now();
                    $this->put('users', ['id' => $row['id']], $row);
                }
                foreach ($registrations as $row) {
                    if (!array_key_exists('appointment_time', $row)) {
                        $day = CarbonImmutable::parse($row['visit_date'], 'Asia/Jakarta')->dayOfWeek;
                        $opening = DB::table('weekly_schedules')->where('day_of_week', $day)->value('open_time');
                        if ($opening) {
                            [$hours, $minutes] = array_map('intval', explode(':', substr($opening, 0, 5)));
                            $appointmentMinutes = $hours * 60 + $minutes + ((int) $row['queue_number'] - 1) * (int) DB::table('profiles')->where('id', 1)->value('duration');
                            $row['appointment_time'] = sprintf('%02d:%02d:00', intdiv($appointmentMinutes, 60), $appointmentMinutes % 60);
                        } else {
                            $row['appointment_time'] = null;
                        }
                    }
                    $row['updated_at'] = $row['created_at'] ?? now();
                    $this->put('registrations', ['id' => $row['id']], $row);
                }
                foreach ($auditLogs as $row) {
                    $row['updated_at'] = $row['created_at'] ?? now();
                    $this->put('audit_logs', ['id' => $row['id']], $row);
                }
            });
        } catch (\Throwable $exception) {
            $this->error('Import dibatalkan: '.$exception->getMessage());

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Import selesai: %d pendaftaran, %d akun, %d hari libur. Database SQLite sumber tidak diubah.',
            count($registrations),
            count($users),
            count($closures),
        ));

        return self::SUCCESS;
    }

    private function mysqlIsFresh(): bool
    {
        if (Registration::query()->exists() || User::query()->exists() || DB::table('audit_logs')->exists() || DB::table('special_closures')->exists()) {
            return false;
        }

        $profile = DB::table('profiles')->where('id', 1)->first();
        $expectedProfile = [
            'name' => 'Bidan Mila',
            'title' => '',
            'motto' => '',
            'description' => '',
            'photo_url' => '',
            'phone' => '',
            'address' => 'Unnamed Road, Cangkuang Wetan, Kec. Dayeuhkolot, Kabupaten Bandung, Jawa Barat 40238',
            'maps_url' => 'https://maps.app.goo.gl/rssdPfhDPcTZTPo69',
            'duration' => 20,
            'capacity' => 20,
        ];
        if (!$profile || (int) $profile->id !== 1) {
            return false;
        }
        foreach ($expectedProfile as $key => $expected) {
            $actual = $profile->{$key};
            if (is_int($expected) ? (int) $actual !== $expected : (string) $actual !== $expected) {
                return false;
            }
        }

        $scheduleCount = DB::table('weekly_schedules')->count();
        if ($scheduleCount !== 7 || DB::table('weekly_schedules')->where('is_open', true)->exists()) {
            return false;
        }

        $setting = DB::table('system_settings')->where('key', 'practice_open')->value('value');
        if ($setting !== 'false' || DB::table('system_settings')->count() !== 1) {
            return false;
        }

        $types = DB::table('visit_types')->orderBy('name')->pluck('name')->all();
        $expectedTypes = ['Imunisasi', 'Konsultasi', 'Kontrol', 'Pemeriksaan bayi', 'Pemeriksaan ibu', 'Pemeriksaan kehamilan'];

        return $types === $expectedTypes;
    }

    private function put(string $table, array $keys, array $row): void
    {
        if ($table === 'special_closures') {
            $createdAt = $row['created_at'] ?? now();
            $row['created_at'] = $createdAt;
            $row['updated_at'] = $createdAt;
        }

        DB::table($table)->updateOrInsert($keys, $row);
    }
}