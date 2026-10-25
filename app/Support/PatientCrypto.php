<?php

namespace App\Support;

use RuntimeException;

class PatientCrypto
{
    public function encrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $iv = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($ciphertext === false) {
            throw new RuntimeException('Data pasien gagal dienkripsi.');
        }

        return implode('.', array_map([$this, 'encode'], [$iv, $tag, $ciphertext]));
    }

    public function decrypt(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $parts = explode('.', $value);
        if (count($parts) !== 3) {
            throw new RuntimeException('Format data pasien terenkripsi tidak valid.');
        }

        [$iv, $tag, $ciphertext] = array_map([$this, 'decode'], $parts);
        $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $this->key(), OPENSSL_RAW_DATA, $iv, $tag);

        if ($plaintext === false) {
            throw new RuntimeException('Data pasien tidak dapat dibuka. Pastikan DATA_ENCRYPTION_KEY sama dengan kunci lama.');
        }

        return $plaintext;
    }

    public function hashCode(string $code): string
    {
        return hash('sha256', strtoupper(trim($code)));
    }

    public function createCode(string $date): string
    {
        return 'BID-'.str_replace('-', '', $date).'-'.strtoupper(bin2hex(random_bytes(5)));
    }

    private function key(): string
    {
        $key = base64_decode((string) config('data.encryption_key'), true);
        if ($key === false || strlen($key) !== 32) {
            throw new RuntimeException('DATA_ENCRYPTION_KEY harus berisi kunci base64 32-byte.');
        }

        return $key;
    }

    private function encode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function decode(string $value): string
    {
        $base64 = strtr($value, '-_', '+/');
        $decoded = base64_decode($base64.str_repeat('=', (4 - strlen($base64) % 4) % 4), true);
        if ($decoded === false) {
            throw new RuntimeException('Format data pasien terenkripsi tidak valid.');
        }

        return $decoded;
    }
}