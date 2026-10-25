<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('profiles', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('name');
            $table->string('title', 80)->default('');
            $table->string('motto', 200)->default('');
            $table->text('description');
            $table->string('photo_url', 500)->default('');
            $table->string('phone', 24)->default('');
            $table->string('address', 300);
            $table->string('maps_url', 500)->default('');
            $table->unsignedSmallInteger('duration')->default(20);
            $table->unsignedSmallInteger('capacity')->default(20);
        });

        Schema::create('weekly_schedules', function (Blueprint $table): void {
            $table->unsignedTinyInteger('day_of_week')->primary();
            $table->boolean('is_open')->default(false);
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
        });

        Schema::create('special_closures', function (Blueprint $table): void {
            $table->id();
            $table->date('date')->unique();
            $table->string('reason', 200);
            $table->timestamps();
        });

        Schema::create('visit_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('description')->default('');
            $table->boolean('is_active')->default(true);
        });

        Schema::create('system_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->text('value');
        });

        Schema::create('registrations', function (Blueprint $table): void {
            $table->id();
            $table->char('public_code_hash', 64)->unique();
            $table->text('public_code_encrypted');
            $table->text('patient_name_encrypted');
            $table->text('phone_encrypted');
            $table->string('visit_type', 80);
            $table->date('visit_date');
            $table->text('complaint_encrypted');
            $table->unsignedInteger('queue_number');
            $table->enum('status', ['WAITING', 'CALLED', 'IN_EXAMINATION', 'COMPLETED', 'CANCELLED', 'NO_SHOW'])->default('WAITING');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->timestamps();
            $table->unique(['visit_date', 'queue_number']);
            $table->index(['visit_date', 'status', 'queue_number'], 'registrations_date_status_queue_idx');
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('entity_type');
            $table->string('entity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('registrations');
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('visit_types');
        Schema::dropIfExists('special_closures');
        Schema::dropIfExists('weekly_schedules');
        Schema::dropIfExists('profiles');
        Schema::dropIfExists('users');
    }
};