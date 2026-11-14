#!/usr/bin/env bash
# Pasang atau perbarui staging QA (docs/qa/staging.md). Jalankan dari akar checkout di VM:
#   scripts/staging/deploy.sh
# SKIP_PULL=1 melewati `git pull` (dipakai saat menguji checkout lokal).
set -euo pipefail

cd "$(dirname "$0")/../.."

if [[ ! -f .env ]]; then
    echo "Berkas .env belum ada. Salin dulu: cp .env.staging.example .env, lalu isi yang bertanda WAJIB." >&2
    exit 1
fi

compose() {
    docker compose -f docker-compose.staging.yml "$@"
}

# Sebagai www-data (pengguna default service PHP), supaya berkas yang dibuat artisan bisa ditulis worker.
artisan() {
    compose exec -T app php artisan "$@"
}

as_root() {
    compose exec -T -u 0 -e COMPOSER_ALLOW_SUPERUSER=1 app "$@"
}

if [[ "${SKIP_PULL:-0}" != 1 ]]; then
    git pull --ff-only
fi

compose build
compose up -d postgres app

as_root composer install --no-interaction --prefer-dist --optimize-autoloader
# VITE_* dibaca dari .env saat build, jadi aset dibangun ulang setiap deploy.
as_root sh -c 'npm ci --no-audit --no-fund && npm run build'
as_root chown -R 33:33 storage bootstrap/cache

if grep -qE '^APP_KEY=$' .env; then
    # Sebagai root: .env milik pengguna VM, bukan www-data.
    as_root php artisan key:generate --force
fi

artisan migrate --force
if [[ ! -L public/storage ]]; then
    as_root php artisan storage:link
fi
artisan optimize

compose up -d
# Worker yang sudah hidup memegang kode lama di memori.
artisan queue:restart
compose restart queue reverb scheduler

echo "Selesai. Buka https://$(grep -E '^SITE_HOST=' .env | cut -d= -f2-)"
