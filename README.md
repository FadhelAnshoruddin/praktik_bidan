# Praktik Bidan Mila

Aplikasi pendaftaran dan antrean berbasis Laravel 13, Blade, dan MySQL. Frontend React serta API Express tidak lagi dipakai; stylesheet dan alur publik/admin yang ada dipertahankan. Database MySQL baru dimulai dengan profil praktik dan jenis layanan, tanpa pasien atau akun contoh.

## Persyaratan

- PHP 8.3 atau lebih baru dengan ekstensi `pdo_mysql`, `openssl`, dan `mbstring`.
- Ekstensi `pdo_sqlite` juga diperlukan jika akan mengimpor database lama.
- Composer 2.
- MySQL 8 atau lebih baru.
- Node.js 22.12 atau lebih baru dan npm untuk Vite.

Buat database MySQL, lalu pasang dependensi:

```sql
CREATE DATABASE pendaftaran_bidan CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```sh
composer install
if [ ! -f .env ]; then cp .env.example .env; fi
php artisan key:generate
npm install
```

Isi kredensial `DB_*` di `.env`. Untuk proyek ini, jangan menimpa `.env` yang sudah ada: tambahkan pengaturan Laravel/MySQL dari `.env.example` ke file tersebut dan pertahankan `DATA_ENCRYPTION_KEY` lama sebelum menjalankan `key:generate`. Untuk instalasi baru, buat `DATA_ENCRYPTION_KEY` dengan:

```sh
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

Simpan hasilnya di `.env` sebagai `DATA_ENCRYPTION_KEY`. Jalankan migrasi dan data awal. Untuk instalasi baru tanpa SQLite, lanjut buat akun pengelola; jika memindahkan SQLite, impor dulu dan ikuti urutan pada bagian berikutnya.

```sh
php artisan migrate --seed
npm run build
composer run dev
```

Buat akun baru dengan `php artisan bidan:create-admin` setelah migrasi (atau setelah impor SQLite untuk data lama). Buka `http://localhost:8000`; panel bidan ada di `/bidan`. `composer run dev` menjalankan server Laravel dan Vite. Periksa koneksi database di `/health`.

## Memindahkan data SQLite lama

Database lama di `data/praktik-bidan.sqlite` tidak dihapus atau diubah. Sebelum impor, salin nilai `DATA_ENCRYPTION_KEY` dari `.env` lama ke `.env` Laravel. Jangan membuat kunci baru untuk data lama: nama, nomor telepon, keluhan, dan kode pendaftaran dienkripsi dengan kunci tersebut.

Setelah membuat database MySQL dan menjalankan `php artisan migrate --seed`, impor data SQLite sebelum membuat akun admin:

```sh
php artisan bidan:import-sqlite
```

Perintah ini membaca SQLite dalam mode hanya-baca, memvalidasi ciphertext dengan kunci yang dikonfigurasi, dan menyalin profil, jadwal, hari libur, jenis layanan, akun, pendaftaran, pengaturan, serta audit. Sesi login lama tidak dipindahkan. Impor hanya berjalan pada MySQL yang masih berisi data seed awal; konfigurasi praktik yang sudah diubah, akun, pasien, hari libur, atau log audit akan membuat impor berhenti. Buat backup MySQL sebelum impor.

Jika migrasi dilakukan dengan akun dan file SQLite yang sama, jalankan `php artisan bidan:create-admin` hanya bila database lama belum memiliki akun pengelola.

## Slot pemeriksaan

Form hanya menampilkan jadwal untuk hari ini menurut waktu Bandung (`Asia/Jakarta`); tanggalnya tidak dapat dipilih. Jam pemeriksaan ditawarkan pada kelipatan satu jam selama jam praktik dan cukup waktu untuk durasi pemeriksaan. Slot yang sudah lewat, dipesan, atau bertabrakan dengan durasi janji lain ditampilkan nonaktif. Waktu kunjungan disimpan pada pendaftaran, dicek ulang di dalam transaksi, dan dilindungi unique index MySQL agar dua pasien tidak mengambil slot yang sama. Status dibatalkan atau tidak hadir melepaskan slot.

## Keamanan

`APP_KEY` melindungi session Laravel; `DATA_ENCRYPTION_KEY` terpisah dan digunakan untuk AES-256-GCM pada data pasien. Simpan keduanya di secret manager saat deployment dan buat backup terenkripsi yang dapat dipulihkan. Login memakai hash bcrypt, cookie session bersifat HTTP-only, dan endpoint pendaftaran/login dibatasi lajunya. Aktifkan HTTPS serta `SESSION_SECURE_COOKIE=true` di production.