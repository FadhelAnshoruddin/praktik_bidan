<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS create_registration');
        DB::unprepared(<<<'SQL'
            CREATE PROCEDURE create_registration(
                IN p_public_code VARCHAR(32),
                IN p_public_code_encrypted TEXT,
                IN p_patient_name_encrypted TEXT,
                IN p_phone_encrypted TEXT,
                IN p_visit_type VARCHAR(80),
                IN p_visit_date DATE,
                IN p_appointment_time TIME,
                IN p_complaint_encrypted TEXT,
                OUT p_registration_id BIGINT UNSIGNED
            )
            BEGIN
                DECLARE v_capacity INT UNSIGNED;
                DECLARE v_duration INT UNSIGNED;
                DECLARE v_queue_number INT UNSIGNED;
                DECLARE v_total_count INT UNSIGNED;
                DECLARE v_practice_open VARCHAR(5);
                DECLARE v_schedule_open BOOLEAN;
                DECLARE v_open_time TIME;
                DECLARE v_close_time TIME;

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

                IF p_visit_date < CURRENT_DATE OR p_appointment_time IS NULL THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Visit date or time is invalid';
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

                IF p_appointment_time < v_open_time OR ADDTIME(
                    p_appointment_time,
                    SEC_TO_TIME(v_duration * 60)
                ) > v_close_time OR (
                    p_visit_date = CURRENT_DATE AND p_appointment_time < CURRENT_TIME
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appointment time is outside available hours';
                END IF;

                SELECT COUNT(*) INTO v_total_count
                FROM registrations
                WHERE visit_date = p_visit_date
                  AND status NOT IN ('CANCELLED', 'NO_SHOW');

                IF v_total_count >= v_capacity OR EXISTS (
                    SELECT 1 FROM registrations
                    WHERE visit_date = p_visit_date
                      AND appointment_time = p_appointment_time
                      AND status NOT IN ('CANCELLED', 'NO_SHOW')
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Appointment slot is no longer available';
                END IF;

                SET v_queue_number = COALESCE((
                    SELECT MAX(queue_number) FROM registrations WHERE visit_date = p_visit_date
                ), 0) + 1;

                INSERT INTO registrations (
                    public_code_hash, public_code_encrypted, patient_name_encrypted,
                    phone_encrypted, visit_type, visit_date, appointment_time,
                    complaint_encrypted, queue_number, status
                ) VALUES (
                    hash_public_code(p_public_code), p_public_code_encrypted,
                    p_patient_name_encrypted, p_phone_encrypted, p_visit_type,
                    p_visit_date, p_appointment_time, p_complaint_encrypted,
                    v_queue_number, 'WAITING'
                );

                SET p_registration_id = LAST_INSERT_ID();
                COMMIT;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP PROCEDURE IF EXISTS create_registration');
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
                FROM weekly_schedules WHERE day_of_week = DAYOFWEEK(p_visit_date) - 1;
                IF v_schedule_open = FALSE OR EXISTS (
                    SELECT 1 FROM special_closures WHERE `date` = p_visit_date
                ) THEN
                    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Practice is closed on the selected date';
                END IF;

                SELECT COUNT(*) INTO v_total_count FROM registrations
                WHERE visit_date = p_visit_date AND status NOT IN ('CANCELLED', 'NO_SHOW');
                SELECT COUNT(*) INTO v_active_count FROM registrations
                WHERE visit_date = p_visit_date AND status IN ('WAITING', 'CALLED', 'IN_EXAMINATION');
                SET v_start_time = IF(p_visit_date = CURRENT_DATE, GREATEST(v_open_time, CURRENT_TIME), v_open_time);

                IF v_total_count >= v_capacity OR ADDTIME(
                    v_start_time, SEC_TO_TIME((v_active_count + 1) * v_duration * 60)
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
};