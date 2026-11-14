# Image staging QA (docs/qa/staging.md). Versi PHP wajib sinkron dengan Dockerfile lokal dan CI (ADR 0003).
# Multi-arch: dibangun di VM Ampere A1 (arm64) dan di laptop (amd64) dari Dockerfile yang sama.
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libpng-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        nodejs \
        npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Unggahan terbesar: 10 MB per berkas, hingga 5 lampiran penilaian sekaligus (ReviewController).
RUN { \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=512M'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
    } > /usr/local/etc/php/conf.d/staging.ini

WORKDIR /var/www/html
