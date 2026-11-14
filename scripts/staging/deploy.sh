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

# Nilai kunci .env tanpa petik pembungkus.
env_value() {
    grep -E "^$1=" .env | tail -n 1 | cut -d= -f2- | sed -E "s/^(['\"])(.*)\1$/\2/"
}

# Kunci WAJIB yang kosong tidak menggagalkan deploy, tetapi merusak situs sesudahnya: Caddy berputar
# restart tanpa hash Basic Auth, dan aset tanpa kunci Reverb tidak pernah membuka WebSocket.
missing=()
for key in SITE_HOST BASIC_AUTH_USER BASIC_AUTH_HASH DB_PASSWORD REVERB_APP_ID REVERB_APP_KEY REVERB_APP_SECRET; do
    if [[ -z "$(env_value "$key")" ]]; then
        missing+=("$key")
    fi
done
if (( ${#missing[@]} > 0 )); then
    echo "Isi dulu di .env: ${missing[*]}" >&2
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

if [[ -z "$(env_value APP_KEY)" ]]; then
    # Sebagai root: .env milik pengguna VM, bukan www-data.
    as_root php artisan key:generate --force
fi

artisan migrate --force
if [[ ! -L public/storage ]]; then
    as_root php artisan storage:link
fi
artisan optimize

compose up -d
# Worker yang sudah hidup memegang kode lama di memori; Caddy membaca Caddyfile hanya saat mulai.
artisan queue:restart
compose restart queue reverb scheduler caddy

sleep 5
not_running=$(compose ps --all --format '{{.Service}} {{.State}}' | awk '$2 != "running" {print $1}')
if [[ -n "$not_running" ]]; then
    echo "Service tidak berjalan: $not_running. Lihat: docker compose -f docker-compose.staging.yml logs $not_running" >&2
    exit 1
fi

site_host=$(env_value SITE_HOST)
https_port=$(env_value HTTPS_PORT)
if [[ -n "$https_port" && "$https_port" != 443 ]]; then
    site_host="$site_host:$https_port"
fi
echo "Selesai. Buka https://$site_host"
