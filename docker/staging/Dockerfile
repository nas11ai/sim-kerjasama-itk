# Image staging QA (docs/qa/staging.md). Versi PHP wajib sinkron dengan Dockerfile lokal dan CI (ADR 0003).
# Multi-arch: dibangun di VM Ampere A1 (arm64) dan di laptop (amd64) dari Dockerfile yang sama.
FROM php:8.4-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        curl \
        unzip \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libzip-dev \
        libonig-dev \
        libxml2-dev \
        libpq-dev \
        nodejs \
        npm \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Sama dengan image CI (simpas-ci:8.4): zip untuk phpspreadsheet (ekspor Excel), GD dengan JPEG untuk
# InlineImageEncoder (gambar pengumuman).
RUN docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo pdo_pgsql mbstring exif pcntl bcmath gd zip opcache

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Unggahan terbesar: 10 MB per berkas, hingga 5 lampiran penilaian sekaligus (ReviewController).
RUN { \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=64M'; \
        echo 'memory_limit=512M'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=1'; \
    } > /usr/local/etc/php/conf.d/staging.ini

# Bawaan image 5 worker; 3–4 penguji dengan polling, unggahan besar, dan PDF butuh lebih (VM 12 GB).
RUN { \
        echo '[www]'; \
        echo 'pm = dynamic'; \
        echo 'pm.max_children = 16'; \
        echo 'pm.start_servers = 4'; \
        echo 'pm.min_spare_servers = 2'; \
        echo 'pm.max_spare_servers = 6'; \
    } > /usr/local/etc/php-fpm.d/zz-staging.conf

WORKDIR /var/www/html
