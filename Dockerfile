# ==================================================
# 1. PHP BASE IMAGE
# ==================================================
FROM php:8.3-fpm-bookworm

# ==================================================
# 2. SYSTEM DEPENDENCIES AND PHP EXTENSIONS
# ==================================================
RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    git \
    unzip \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    libsqlite3-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        pdo_sqlite \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache \
        intl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ==================================================
# 3. INSTALL COMPOSER
# ==================================================
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# ==================================================
# 4. INSTALL NODE.JS AND NPM
# ==================================================
COPY --from=node:22-bookworm-slim /usr/local/bin/node /usr/local/bin/node
COPY --from=node:22-bookworm-slim /usr/local/lib/node_modules /usr/local/lib/node_modules

RUN ln -sf /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -sf /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx

# ==================================================
# 5. SET WORKING DIRECTORY
# ==================================================
WORKDIR /var/www/html

# ==================================================
# 6. COPY PROJECT FILES
# IMPORTANT: .dockerignore must exclude .env and other secrets.
# ==================================================
COPY . .

# ==================================================
# 7. INSTALL PHP DEPENDENCIES
# ==================================================
RUN composer install \
    --no-dev \
    --prefer-dist \
    --optimize-autoloader \
    --no-interaction

# ==================================================
# 8. BUILD FRONTEND ASSETS
# ==================================================
RUN npm ci \
    && npm run build \
    && rm -rf node_modules

# ==================================================
# 9. PREPARE LARAVEL DIRECTORIES AND PERMISSIONS
# ==================================================
RUN mkdir -p \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

# ==================================================
# 10. KONFIGURASI NGINX (placeholder)
# Port sesungguhnya dikonfigurasi oleh docker-entrypoint.sh
# saat startup agar bisa mengikuti environment variable $PORT dari Render.
# ==================================================
RUN printf '%s\n' \
    'server {' \
    '    listen 10000;' \
    '    server_name _;' \
    '    root /var/www/html/public;' \
    '    index index.php index.html;' \
    '    location / {' \
    '        try_files $uri $uri/ /index.php?$query_string;' \
    '    }' \
    '    location ~ \.php$ {' \
    '        include fastcgi_params;' \
    '        fastcgi_pass 127.0.0.1:9000;' \
    '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' \
    '        fastcgi_param DOCUMENT_ROOT $document_root;' \
    '    }' \
    '    location ~ /\. {' \
    '        deny all;' \
    '    }' \
    '}' > /etc/nginx/sites-available/default

# (Konfigurasi final ditulis ulang oleh docker-entrypoint.sh menggunakan $PORT)

# ==================================================
# 11. COPY STARTUP SCRIPT
# ==================================================
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ==================================================
# 12. EXPOSE RENDER WEB PORT
# ==================================================
EXPOSE 10000

# ==================================================
# 13. START APPLICATION
# ==================================================
CMD ["/usr/local/bin/docker-entrypoint.sh"]
