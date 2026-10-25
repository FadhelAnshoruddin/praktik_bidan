<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE registrations ADD appointment_time TIME NULL AFTER visit_date');

        DB::statement(<<<'SQL'
            UPDATE registrations AS registration
            INNER JOIN profiles AS profile ON profile.id = 1
            INNER JOIN weekly_schedules AS schedule
                ON schedule.day_of_week = DAYOFWEEK(registration.visit_date) - 1
            SET registration.appointment_time = ADDTIME(
                schedule.open_time,
                SEC_TO_TIME((registration.queue_number - 1) * profile.duration * 60)
            )
            WHERE registration.appointment_time IS NULL
              AND schedule.open_time IS NOT NULL
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE registrations
            ADD active_appointment_time TIME
            GENERATED ALWAYS AS (
                CASE
                    WHEN status IN ('CANCELLED', 'NO_SHOW') THEN NULL
                    ELSE appointment_time
                END
            ) STORED
        SQL);

        DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX registrations_active_appointment_time_unique
            ON registrations (visit_date, active_appointment_time)
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX registrations_active_appointment_time_unique ON registrations');
        DB::statement('ALTER TABLE registrations DROP COLUMN active_appointment_time');
        DB::statement('ALTER TABLE registrations DROP COLUMN appointment_time');
    }
};