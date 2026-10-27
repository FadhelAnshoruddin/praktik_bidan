<?php

// ==============================================================================
// api/index.php — Serverless Entry Point untuk Hosting di Vercel
// ==============================================================================
// Vercel menggunakan filesystem read-only kecuali folder /tmp.
// Script ini memastikan direktori cache & views yang dibutuhkan Laravel
// tersedia di /tmp sebelum menangani request.
// ==============================================================================

$tmpDirs = [
    '/tmp/storage/framework/views',
    '/tmp/storage/framework/cache/data',
    '/tmp/storage/framework/sessions',
    '/tmp/storage/logs',
    '/tmp/bootstrap/cache',
];

foreach ($tmpDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// Teruskan eksekusi ke front controller Laravel
require __DIR__ . '/../public/index.php';
