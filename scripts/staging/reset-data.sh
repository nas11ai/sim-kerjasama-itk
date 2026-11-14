#!/usr/bin/env bash
# Hapus SEMUA data staging lalu isi ulang dengan data uji QA (docs/qa/data-uji.md).
# Jalankan dari akar checkout di VM sesudah deploy.sh:
#   scripts/staging/reset-data.sh
set -euo pipefail

cd "$(dirname "$0")/../.."

site_host=$(grep -E '^SITE_HOST=' .env | cut -d= -f2-)

if [[ -z "$site_host" ]]; then
    echo "SITE_HOST di .env kosong; isi dulu sebelum reset." >&2
    exit 1
fi

echo "Semua pengguna, pengajuan, dan berkas unggahan di $site_host akan dihapus."
read -r -p "Ketik ulang nama host untuk melanjutkan: " answer

if [[ "$answer" != "$site_host" ]]; then
    echo "Nama host tidak cocok; dibatalkan, tidak ada yang diubah." >&2
    exit 1
fi

compose() {
    docker compose -f docker-compose.staging.yml "$@"
}

# Worker berhenti dulu supaya tidak ada job lama yang berjalan atas skema yang sedang dibuat ulang.
compose stop queue scheduler

# Berkas dari data sebelumnya tidak lagi dirujuk database yang baru. `.gitignore` di folder itu dipertahankan.
compose exec -T app find storage/app/private storage/app/public -mindepth 1 ! -name .gitignore -delete

compose exec -T app php artisan migrate:fresh --seed --force
compose exec -T app php artisan db:seed --class=QaDataSeeder --force

compose start queue scheduler
echo "Data uji siap. Akun dan kata sandi: docs/qa/data-uji.md"
