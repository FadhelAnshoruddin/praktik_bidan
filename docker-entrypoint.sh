#!/bin/sh

# =================================================================
# docker-entrypoint.sh — Startup script untuk Praktik Bidan di Docker/Render
# =================================================================
# - Mendukung PORT dinamis dari environment (penting untuk Render)
# - Menyiapkan direktori dan permission Laravel
# - Menjalankan php-fpm dan nginx
# =================================================================

set -eu

# ---------------------------------------------------------------
# 1. Pindah ke root aplikasi
# ---------------------------------------------------------------
cd /var/www/html

# ---------------------------------------------------------------
# 2. Siapkan direktori Laravel dan permission
# ---------------------------------------------------------------
mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ---------------------------------------------------------------
# 3. Konfigurasi port dinamis untuk Nginx
#    Render menyuntikkan PORT environment variable (default: 10000)
# ---------------------------------------------------------------
APP_PORT="${PORT:-10000}"

# Tulis konfigurasi Nginx dengan port yang benar
cat > /etc/nginx/sites-available/default << NGINX_CONF
server {
    listen ${APP_PORT};
    listen [::]:${APP_PORT};
    server_name _;
    root /var/www/html/public;
    index index.php index.html;

    # Aset frontend (Vite build)
    location /build/ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        try_files \$uri =404;
    }

    # Laravel routing
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP-FPM
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$document_root;
        fastcgi_read_timeout 60;
    }

    # Sembunyikan file tersembunyi
    location ~ /\. {
        deny all;
    }

    # Batasi akses ke file sensitif
    location ~* \.(env|log|sh|sql|bak)$ {
        deny all;
    }
}
NGINX_CONF

# ---------------------------------------------------------------
# 4. Bersihkan config cache dan optimalkan Laravel
# ---------------------------------------------------------------
php artisan config:clear || true
php artisan optimize --force 2>/dev/null || php artisan config:cache 2>/dev/null || true

# ---------------------------------------------------------------
# 5. Jalankan PHP-FPM sebagai daemon
# ---------------------------------------------------------------
php-fpm -D

# ---------------------------------------------------------------
# 6. Jalankan Nginx di foreground (proses utama container)
# ---------------------------------------------------------------
exec nginx -g 'daemon off;'
