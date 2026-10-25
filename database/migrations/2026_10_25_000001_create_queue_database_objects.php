<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            CREATE FUNCTION hash_public_code(p_code VARCHAR(32))
            RETURNS CHAR(64)
            DETERMINISTIC
            NO SQL
            RETURN LOWER(SHA2(UPPER(TRIM(p_code)), 256))
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE TRIGGER registrations_before_update
            BEFORE UPDATE ON registrations
            FOR EACH ROW
            BEGIN
                IF NEW.status <> OLD.status AND NOT (
                    (OLD.status = 'WAITING' AND NEW.status IN ('CALLED', 'CANCELLED', 'NO_SHOW')) OR
                    (OLD.status = 'CALLED' AND NEW.status IN ('CALLED', 'IN_EXAMINATION', 'CANCELLED', 'NO_SHOW')) OR
                    (OLD.status = 'IN_EXAMINATION' AND NEW.status = 'COMPLETED')
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Invalid registration status transition';
                END IF;

                IF NEW.status = 'CALLED' AND NEW.called_at IS NULL THEN
                    SET NEW.called_at = CURRENT_TIMESTAMP;
                ELSEIF NEW.status = 'IN_EXAMINATION' AND NEW.started_at IS NULL THEN
                    SET NEW.started_at = CURRENT_TIMESTAMP;
                ELSEIF NEW.status = 'COMPLETED' AND NEW.completed_at IS NULL THEN
                    SET NEW.completed_at = CURRENT_TIMESTAMP;
                ELSEIF NEW.status = 'NO_SHOW' AND NEW.no_show_at IS NULL THEN
                    SET NEW.no_show_at = CURRENT_TIMESTAMP;
                END IF;
            END
        SQL);

        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE create_registration(
                IN p_public_code VARCHAR(32),
                IN p_public_code_encrypted TEXT,
                IN p_patient_name_encrypted TEXT,
                IN p_phone_encrypted TEXT,
                IN p_visit_type VARCHAR(80),
                IN p_visit_date DATE,
                IN p_complaint_encrypted TEXT,
                OUT p_registration_id BIGINT UNSIGNED
            )
            BEGIN
                DECLARE v_capacity INT UNSIGNED;
                DECLARE v_duration INT UNSIGNED;
                DECLARE v_queue_number INT UNSIGNED;
                DECLARE v_total_count INT UNSIGNED;
                DECLARE v_active_count INT UNSIGNED;
                DECLARE v_practice_open VARCHAR(5);
                DECLARE v_schedule_open BOOLEAN;
                DECLARE v_open_time TIME;
                DECLARE v_close_time TIME;
                DECLARE v_start_time TIME;

                DECLARE EXIT HANDLER FOR SQLEXCEPTION
                BEGIN
                    ROLLBACK;
                    RESIGNAL;
                END;

                START TRANSACTION;

                SELECT capacity, duration INTO v_capacity, v_duration
                FROM profiles WHERE id = 1 FOR UPDATE;

                SELECT value INTO v_practice_open
                FROM system_settings WHERE `key` = 'practice_open';

                IF v_practice_open <> 'true' THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Practice is not accepting registrations';
                END IF;

                IF p_visit_date < CURRENT_DATE THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Visit date cannot be in the past';
                END IF;

                SELECT is_open, open_time, close_time
                INTO v_schedule_open, v_open_time, v_close_time
                FROM weekly_schedules
                WHERE day_of_week = DAYOFWEEK(p_visit_date) - 1;

                IF v_schedule_open = FALSE OR EXISTS (
                    SELECT 1 FROM special_closures WHERE `date` = p_visit_date
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Practice is closed on the selected date';
                END IF;

                SELECT COUNT(*) INTO v_total_count
                FROM registrations
                WHERE visit_date = p_visit_date
                  AND status NOT IN ('CANCELLED', 'NO_SHOW');

                SELECT COUNT(*) INTO v_active_count
                FROM registrations
                WHERE visit_date = p_visit_date
                  AND status IN ('WAITING', 'CALLED', 'IN_EXAMINATION');

                SET v_start_time = IF(
                    p_visit_date = CURRENT_DATE,
                    GREATEST(v_open_time, CURRENT_TIME),
                    v_open_time
                );

                IF v_total_count >= v_capacity OR ADDTIME(
                    v_start_time,
                    SEC_TO_TIME((v_active_count + 1) * v_duration * 60)
                ) > v_close_time THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No registration slots remain for the selected date';
                END IF;

                SET v_queue_number = COALESCE((
                    SELECT MAX(queue_number) FROM registrations WHERE visit_date = p_visit_date
                ), 0) + 1;

                INSERT INTO registrations (
                    public_code_hash, public_code_encrypted, patient_name_encrypted,
                    phone_encrypted, visit_type, visit_date, complaint_encrypted,
                    queue_number, status
                ) VALUES (
                    hash_public_code(p_public_code), p_public_code_encrypted,
                    p_patient_name_encrypted, p_phone_encrypted, p_visit_type,
                    p_visit_date, p_complaint_encrypted, v_queue_number, 'WAITING'
                );

                SET p_registration_id = LAST_INSERT_ID();
                COMMIT;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS create_registration');
        DB::unprepared('DROP TRIGGER IF EXISTS registrations_before_update');
        DB::unprepared('DROP FUNCTION IF EXISTS hash_public_code');
    }
};