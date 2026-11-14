#!/usr/bin/env bash
# Hapus SEMUA data staging lalu isi ulang dengan data uji QA (docs/qa/data-uji.md).
# Jalankan dari akar checkout di VM sesudah deploy.sh:
#   scripts/staging/reset-data.sh
set -euo pipefail

cd "$(dirname "$0")/../.."

site_host=$(grep -E '^SITE_HOST=' .env | tail -n 1 | cut -d= -f2- | sed -E "s/^(['\"])(.*)\1$/\2/")

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

artisan() {
    compose exec -T app php artisan "$@"
}

# Penguji yang sedang membuka situs melihat halaman pemeliharaan, bukan galat, selama reset berjalan.
# Worker berhenti supaya tidak ada job lama yang berjalan atas skema yang sedang dibuat ulang.
# Keduanya dipulihkan juga bila reset gagal di tengah jalan.
artisan down
compose stop queue scheduler
trap 'compose start queue scheduler; artisan up' EXIT

# Berkas dari data sebelumnya tidak lagi dirujuk database yang baru. `.gitignore` di folder itu dipertahankan.
compose exec -T app find storage/app/private storage/app/public -mindepth 1 ! -name .gitignore -delete

artisan migrate:fresh --seed --force
artisan db:seed --class=QaDataSeeder --force

echo "Data uji siap. Akun dan kata sandi: docs/qa/data-uji.md"
