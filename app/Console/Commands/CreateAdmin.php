<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'bidan:create-admin';

    protected $description = 'Buat akun pengelola praktik secara interaktif';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->error('Akun pengelola sudah tersedia.');

            return self::FAILURE;
        }

        $email = strtolower(trim((string) $this->ask('Email pengelola')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Format email tidak valid.');

            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi (minimal 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi kata sandi');
        if (strlen($password) < 12 || !hash_equals($password, $confirmation)) {
            $this->error('Kata sandi harus minimal 12 karakter dan kedua input harus sama.');

            return self::FAILURE;
        }

        User::query()->create(['email' => $email, 'password' => $password]);
        $this->info('Akun pengelola berhasil dibuat.');

        return self::SUCCESS;
    }
}