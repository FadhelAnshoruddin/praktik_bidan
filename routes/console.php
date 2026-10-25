<?php

use Illuminate\Support\Facades\Artisan;

Artisan::command('about', function (): void {
    $this->comment('Praktik Bidan Mila berjalan di Laravel 13.');
})->purpose('Tampilkan informasi aplikasi');