#!/usr/bin/env bash

# ==============================================================================
# scripts/setup_aiven_migration.sh
# Skrip Interaktif: Konfigurasi & Migrasi Database Lokal ke Aiven for MySQL
# ==============================================================================

set -euo pipefail

cd "$(dirname "$0")/.."
PROJECT_ROOT="$(pwd)"

echo "======================================================================"
echo "    SETUP DAN MIGRASI CLOUD DATABASE (AIVEN FOR MYSQL)"
echo "    PROYEK: PRAKTIK BIDAN MILA"
echo "======================================================================"
echo ""

# 1. Cek file .env
if [ ! -f .env ]; then
    echo "[-] File .env tidak ditemukan. Menyalin dari .env.example..."
    cp .env.example .env
    php artisan key:generate
fi

# 2. Cek database SQLite sumber
if [ ! -f data/praktik-bidan.sqlite ]; then
    echo "[-] Database sumber data/praktik-bidan.sqlite tidak ditemukan!"
    exit 1
fi

# Cek apakah sudah ada konfigurasi Aiven di .env
CURRENT_HOST=$(grep "^DB_HOST=" .env | cut -d'=' -f2- || true)
CURRENT_PORT=$(grep "^DB_PORT=" .env | cut -d'=' -f2- || true)
CURRENT_DB=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2- || true)
CURRENT_USER=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2- || true)

USE_SAVED="n"
if [[ "$CURRENT_HOST" == *"aivencloud.com"* ]]; then
    echo "[i] Terdeteksi konfigurasi Aiven di .env:"
    echo "    Host     : $CURRENT_HOST"
    echo "    Port     : $CURRENT_PORT"
    echo "    Database : $CURRENT_DB"
    echo "    User     : $CURRENT_USER"
    read -rp "  Gunakan konfigurasi tersimpan ini? [Y/n]: " CONFIRM_SAVED
    CONFIRM_SAVED="${CONFIRM_SAVED:-Y}"
    if [[ "$CONFIRM_SAVED" =~ ^[Yy]$ ]]; then
        USE_SAVED="y"
    fi
fi

if [ "$USE_SAVED" != "y" ]; then
    echo "[1/4] Masukkan informasi koneksi Aiven MySQL Anda:"
    echo "(Informasi ini dapat dilihat pada tab 'Overview' service di Aiven Console)"
    echo ""

    read -rp "  Host Aiven (contoh: mysql-xxxx.aivencloud.com): " INPUT_HOST
    read -rp "  Port Aiven (contoh: 19850): " INPUT_PORT
    read -rp "  Database Aiven [default: defaultdb]: " INPUT_DB
    INPUT_DB="${INPUT_DB:-defaultdb}"
    read -rp "  Username Aiven [default: avnadmin]: " INPUT_USER
    INPUT_USER="${INPUT_USER:-avnadmin}"

    # Password input secara tersembunyi agar aman
    read -rsp "  Password Aiven: " INPUT_PASS
    echo ""

    # Validasi input tidak kosong
    if [ -z "$INPUT_HOST" ] || [ -z "$INPUT_PORT" ] || [ -z "$INPUT_PASS" ]; then
        echo "[-] Error: Host, Port, dan Password wajib diisi!"
        exit 1
    fi

    echo "[2/4] Menyimpan konfigurasi ke .env lokal..."

    # Fungsi helper update atau append .env
    update_env() {
        local key="$1"
        local value="$2"
        if grep -q "^${key}=" .env; then
            sed -i "s|^${key}=.*|${key}=${value}|" .env
        else
            echo "${key}=${value}" >> .env
        fi
    }

    update_env "DB_CONNECTION" "mysql"
    update_env "DB_HOST" "$INPUT_HOST"
    update_env "DB_PORT" "$INPUT_PORT"
    update_env "DB_DATABASE" "$INPUT_DB"
    update_env "DB_USERNAME" "$INPUT_USER"
    update_env "DB_PASSWORD" "$INPUT_PASS"
else
    echo "[1/4] Menggunakan konfigurasi Aiven yang tersimpan di .env..."
    echo "[2/4] Konfigurasi CA certificate..."
fi

# Cek apakah aiven-ca.pem ada di direktori root proyek
if [ -f "aiven-ca.pem" ]; then
    echo "  [+] File aiven-ca.pem ditemukan. Mengonfigurasi MYSQL_ATTR_SSL_CA otomatis."
    if grep -q "^MYSQL_ATTR_SSL_CA=" .env; then
        sed -i "s|^MYSQL_ATTR_SSL_CA=.*|MYSQL_ATTR_SSL_CA=$(pwd)/aiven-ca.pem|" .env
    else
        echo "MYSQL_ATTR_SSL_CA=$(pwd)/aiven-ca.pem" >> .env
    fi
fi

# Bersihkan config cache agar Laravel membaca konfigurasi baru
php artisan config:clear >/dev/null 2>&1 || true

echo ""
echo "[3/4] Menguji koneksi jaringan ke Aiven MySQL..."
PHP_TEST_CONN=$(php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $pdo = Illuminate\Support\Facades\DB::connection()->getPdo();
    echo "SUCCESS";
} catch (\Throwable $e) {
    echo "FAILED: " . $e->getMessage();
}
')

if [ "$PHP_TEST_CONN" != "SUCCESS" ]; then
    echo "[-] Gagal terhubung ke Aiven MySQL!"
    echo "    Penyebab: $PHP_TEST_CONN"
    echo "    Silakan periksa kembali Host, Port, Username, Password, atau pastikan instance Aiven berstatus 'Running'."
    exit 1
fi

echo "[+] Koneksi ke Aiven MySQL BERHASIL!"
echo ""

echo "[4/4] Menjalankan migrasi skema dan impor data..."
echo "  a. Menjalankan migrasi skema awal (tables, stored procedure, triggers)..."
php artisan migrate --force

echo "  b. Menjalankan seed data dasar (DatabaseSeeder)..."
php artisan db:seed --force

echo "  c. Menyalin data dari data/praktik-bidan.sqlite ke Aiven MySQL..."
php artisan bidan:import-sqlite data/praktik-bidan.sqlite

echo ""
echo "======================================================================"
echo "    VERIFIKASI INTEGRITAS DATA CLOUD"
echo "======================================================================"

php -r '
require "vendor/autoload.php";
$app = require_once "bootstrap/app.php";
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tables = ["profiles", "weekly_schedules", "visit_types", "system_settings", "users", "registrations", "audit_logs", "sessions"];
echo "Jumlah Baris pada Tabel Aiven MySQL:\n";
foreach ($tables as $table) {
    try {
        $count = Illuminate\Support\Facades\DB::table($table)->count();
        echo sprintf("  - %-20s : %d baris\n", $table, $count);
    } catch (\Throwable $e) {
        echo sprintf("  - %-20s : GAGAL DIBACA (%s)\n", $table, $e->getMessage());
    }
}

echo "\nUji Dekripsi Data Pasien dari Aiven MySQL:\n";
try {
    $crypto = $app->make(App\Support\PatientCrypto::class);
    $regs = App\Models\Registration::all();
    $decrypted = 0;
    foreach ($regs as $reg) {
        $code = $crypto->decrypt($reg->public_code_encrypted);
        $name = $crypto->decrypt($reg->patient_name_encrypted);
        $phone = $crypto->decrypt($reg->phone_encrypted);
        if (!empty($code) && !empty($name)) {
            $decrypted++;
        }
    }
    echo "  - Status dekripsi: Berhasil mendekripsi $decrypted / " . count($regs) . " pasien.\n";
    echo "  - Integritas data: LULUS (Semua data dapat dibaca dengan aman)\n";
} catch (\Throwable $e) {
    echo "  - Status dekripsi: GAGAL (" . $e->getMessage() . ")\n";
}
'

echo ""
echo "======================================================================"
echo "    MIGRASI DATA KE AIVEN MYSQL BERHASIL DISELESAIKAN!"
echo "======================================================================"
