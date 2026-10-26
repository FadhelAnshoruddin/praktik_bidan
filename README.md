# Praktik Bidan Mila

Aplikasi pendaftaran dan sistem antrean pasien mandiri berbasis **Laravel 13**, **Blade**, **Vite (CSS murni & Vanilla JS)**, dan **MySQL**. Dirancang untuk berjalan dalam container **Docker** dan siap di-deploy ke **Render** dengan cloud database **Aiven for MySQL**.

---

## 1. Arsitektur & Teknologi

- **Backend:** Laravel 13, PHP 8.3-FPM (didukung PHP 8.3 - 8.5)
- **Frontend:** Blade Templates, Vanilla JS, CSS murni dengan Vite 7.x (`src/styles.css`, `src/app.js`)
- **Web Server:** Nginx 1.22+ (reverse proxy ke PHP-FPM di port 9000)
- **Database:** MySQL 8.x (didukung fitur Stored Procedure, Generated Column `STORED`, dan Unique Index)
- **Enkripsi Data Pasien:** AES-256-GCM via `PatientCrypto` menggunakan `DATA_ENCRYPTION_KEY`
- **Session:** Database driver (`sessions` table) agar session pasien dan bidan tetap persisten
- **Containerization:** Docker multi-toolchain (PHP 8.3-FPM Bookworm + Nginx + Node.js 22 + Composer 2)
- **Deployment Target:** Render Web Service (Docker Runtime) + Aiven for MySQL (Free Tier)

---

## 2. Persyaratan Sistem

- PHP 8.3 atau lebih baru dengan ekstensi: `pdo_mysql`, `pdo_sqlite`, `openssl`, `mbstring`, `intl`, `gd`, `zip`.
- Composer 2.
- Node.js 22+ dan npm.
- Docker Engine dan Docker Compose (jika menjalankan via container).
- Git.

---

## 3. Menjalankan Proyek Secara Lokal (PHP CLI)

### A. Instalasi Dependensi

```bash
composer install
npm ci
npm run build
```

### B. Konfigurasi Environment

Jika belum memiliki `.env`, salin dari `.env.example`:

```bash
cp .env.example .env
php artisan key:generate
```

> **PERINGATAN PENTING**: Jika Anda memiliki data pasien lama di SQLite (`data/praktik-bidan.sqlite`), pastikan nilai `DATA_ENCRYPTION_KEY` di `.env` adalah kunci yang sama dengan database tersebut. Jangan generate kunci baru untuk data lama!

Untuk instalasi baru tanpa data lama:
```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```
Salin hasilnya ke baris `DATA_ENCRYPTION_KEY` di `.env`.

### C. Migrasi Database & Seeder

```bash
php artisan migrate --seed
```

Jika ingin memindahkan data dari SQLite lama ke MySQL:
```bash
php artisan bidan:import-sqlite
```

### D. Buat Akun Admin (Panel Bidan)

Jika database belum memiliki akun pengelola:
```bash
php artisan bidan:create-admin
```

### E. Jalankan Server Lokal

```bash
composer run dev
```
Akses website di `http://localhost:8000` dan panel bidan di `http://localhost:8000/bidan`.

---

## 4. Menjalankan dengan Docker & Docker Compose

Proyek ini telah dilengkapi `Dockerfile` mandiri dan `docker-compose.yml`.

### A. Konfigurasi `.env`

Pastikan variabel `DB_*` di `.env` mengarah ke cloud database (misalnya Aiven MySQL) atau MySQL host:

```env
DB_CONNECTION=mysql
DB_HOST=<HOST_DATABASE>
DB_PORT=<PORT_DATABASE>
DB_DATABASE=<NAMA_DATABASE>
DB_USERNAME=<USERNAME_DATABASE>
DB_PASSWORD=<PASSWORD_DATABASE>
DATA_ENCRYPTION_KEY=<KUNCI_ENKRIPSI_PASIEN>
```

### B. Perintah Operasional Docker

```bash
# Build dan jalankan container di background
docker compose up -d --build

# Periksa status container
docker compose ps

# Lihat log aplikasi (Nginx & PHP-FPM)
docker compose logs -f app

# Hentikan container
docker compose stop

# Jalankan kembali
docker compose start

# Hapus container (data cloud database tetap aman)
docker compose down

# Masuk ke terminal container
docker compose exec app sh
```

Aplikasi dapat diakses di browser melalui: `http://localhost:10001`.

---

## 5. Cloud Database: Aiven for MySQL (Free Tier)

### Alasan Pemilihan

Proyek ini menggunakan fitur MySQL khusus:
1. **Stored Procedure** (`create_registration`) untuk reservasi antrean berintegritas tinggi.
2. **Generated Column STORED** (`active_appointment_time`) untuk memastikan keunikan slot waktu aktif.

*Catatan evaluasi*: **TiDB Cloud Serverless** tidak mendukung stored procedures, sedangkan **Render Managed Database** hanya menyediakan PostgreSQL gratis selama 30 hari. **Aiven for MySQL Free Tier** menyediakan MySQL 8 murni secara permanen ($0/bulan) tanpa kartu kredit dan mendukung semua fitur tersebut.

### Langkah Setup Aiven MySQL:

1. Daftar akun di [Aiven Console](https://console.aiven.io/).
2. Buat service baru: pilih **MySQL**, pilih cloud provider dan region terdekat (misal: Singapore / `ap-southeast-1`), dan pilih plan **Free**.
3. Di tab **Overview**, catat:
   - **Host**
   - **Port**
   - **User**
   - **Password**
   - **Database Name** (default: `defaultdb`)
   - Unduh **CA Certificate** (`ca.pem`) jika ingin verifikasi TLS penuh.
4. Uji koneksi dari komputer pengembang:
   ```bash
   mysql -h <HOST_AIVEN> -P <PORT_AIVEN> -u <USER_AIVEN> -p <NAMA_DATABASE>
   ```
5. Jalankan migrasi dan impor data:
   ```bash
   # Di komputer lokal dengan .env mengarah ke Aiven:
   php artisan migrate --seed --force
   php artisan bidan:import-sqlite
   ```

---

## 6. Prosedur Backup & Pemulihan Database

### Backup MySQL
```bash
mysqldump -h <DB_HOST> -P <DB_PORT> -u <DB_USERNAME> -p \
  --single-transaction --quick --routines --triggers \
  <DB_NAME> > backup_praktik_bidan_$(date +%Y%m%d_%H%M%S).sql
```

> **Catatan Keamanan**: Jangan pernah commit file `.sql` ke Git. File backup telah dikecualikan di `.gitignore`.

### Pemulihan (Restore)
```bash
mysql -h <DB_HOST> -P <DB_PORT> -u <DB_USERNAME> -p <DB_NAME> < backup_praktik_bidan_YYYYMMDD.sql
```

---

## 7. Deployment ke Render (Docker Web Service)

Render menjalankan aplikasi sebagai container Docker yang menerima koneksi HTTP.

### Karakteristik Paket Gratis Render:
- **Spin-down otomatis**: Service akan tidur setelah 15 menit tanpa trafik. Permintaan pertama akan memakan waktu sekitar 50 detik (cold start).
- **Ephemeral Filesystem**: File yang disimpan di disk lokal container akan hilang saat restart/deploy. Aplikasi ini sudah siap karena session dan data tersimpan di database MySQL, serta aset frontend ter-bundle di dalam image.
- **Port Otomatis**: Render menyuntikkan environment variable `$PORT` (default 10000). File `docker-entrypoint.sh` secara otomatis mengonfigurasi Nginx untuk mendengarkan pada port tersebut.

### Langkah Deployment di Dashboard Render:

1. Buat akun di [Render](https://render.com) dan hubungkan akun GitHub Anda.
2. Klik **New +** → **Web Service**.
3. Pilih repository `Praktik_bidan` (branch: `main`).
4. Konfigurasi service:
   - **Name**: `praktik-bidan`
   - **Runtime**: `Docker`
   - **Dockerfile Path**: `./Dockerfile`
   - **Instance Type**: `Free`
5. Buka tab **Environment** dan tambahkan variabel berikut:

| Key | Value / Catatan |
|---|---|
| `APP_NAME` | `Praktik Bidan Mila` |
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` |
| `APP_KEY` | *(Salin dari `php artisan key:generate --show` lokal)* |
| `APP_URL` | `https://<nama-service>.onrender.com` |
| `LOG_CHANNEL` | `stderr` |
| `LOG_LEVEL` | `error` |
| `CACHE_STORE` | `file` |
| `DB_CONNECTION` | `mysql` |
| `DB_HOST` | Host Aiven MySQL Anda |
| `DB_PORT` | Port Aiven MySQL Anda |
| `DB_DATABASE` | Nama database Aiven |
| `DB_USERNAME` | Username Aiven |
| `DB_PASSWORD` | Password Aiven (gunakan opsi Secret) |
| `DB_TIMEZONE` | `+07:00` |
| `SESSION_DRIVER` | `database` |
| `SESSION_LIFETIME` | `720` |
| `SESSION_ENCRYPT` | `true` |
| `SESSION_SECURE_COOKIE` | `true` |
| `DATA_ENCRYPTION_KEY` | Kunci enkripsi data pasien (gunakan opsi Secret) |

6. Klik **Deploy Web Service**.
7. Pantau build log dan runtime log di dashboard Render.
8. Setelah status **Live**, akses URL `https://<nama-service>.onrender.com`.

---

## 8. Verifikasi & Checklist Pengujian

| Pengujian | Endpoint / Lokasi | Kriteria Keberhasilan |
|---|---|---|
| Healthcheck Ringan | `GET /up` | HTTP 200 OK |
| Healthcheck Database | `GET /health` | HTTP 200 `{"status":"ok","database":"mysql"}` |
| Tampilan Beranda | `GET /` | CSS ter-load, font DM Sans/Manrope tampil, navigasi responsif |
| Pendaftaran Janji | `GET /daftar` | Slot jam hari ini muncul otomatis via JS (`/api/public/today-slots`) |
| Pengiriman Formulir | `POST /daftar` | Berhasil redirect ke `/berhasil` dengan kode `BID-YYYYMMDD-XXXXXXXXXX` |
| Cek Antrean | `GET /cek` & `POST /cek` | Status antrean dan estimasi waktu tampil |
| Login Panel Bidan | `GET /bidan` | Form login aman, proteksi rate-limit aktif |
| Dashboard Bidan | `GET /bidan/panel` | Tab antrean, pendaftaran pasien, profil, dan hari libur berfungsi |
| Keamanan Data | Database | Kolom nama, telepon, dan keluhan terenkripsi AES-256-GCM |

---

## 9. Keamanan & Kebijakan Rahasia

- Jangan pernah menambahkan file `.env`, file `.sql`, atau file kunci ke Git.
- File `.gitignore` dan `.dockerignore` telah dikonfigurasi untuk mencegah kebocoran secret.
- Semua secret produksi wajib dimasukkan secara eksklusif melalui dashboard Render.
