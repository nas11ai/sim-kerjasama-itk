# Staging QA di VM Gratis: Rencana Implementasi

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or
> superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** tumpukan staging (Caddy + php-fpm + Postgres + antrean + Reverb + scheduler) yang dijalankan pemilik di satu
VM Oracle Always Free, dengan skrip deploy/reset dan panduan dari akun kosong.

**Architecture:** berkas compose terpisah me-mount checkout VM ke container (pola `docker-compose.yml` lokal). Caddy
melayani `public/`, meneruskan PHP ke php-fpm lewat FastCGI (Caddy mengirim `HTTPS=on`, jadi Laravel tidak butuh
trusted proxy) dan `/app` ke Reverb. Basic Auth Caddy menutup seluruh situs.

**Tech Stack:** Docker Compose, Caddy 2, `php:8.4-fpm`, `postgres:18-alpine`, Laravel Reverb, bash.

**Spec:** `docs/design/2026-11-14-staging-vm-design.md`

## Global Constraints

- Tidak ada perubahan kode aplikasi (`app/`, `resources/`, `config/`, `routes/`) dan tidak ada dependency baru.
- `docker-compose.yml` lokal tidak disentuh.
- `APP_ENV=staging`, `APP_DEBUG=false`, `MAIL_MAILER=log`, `SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE=database`.
- Dependency Composer dipasang lengkap (Faker dipakai `QaDataSeeder`).
- Image harus jalan di arm64 (Ampere A1) dan amd64 (verifikasi lokal): hanya image resmi multi-arch.
- Teks panduan berbahasa Indonesia; tidak ada "—" di teks yang dilihat pengguna aplikasi (berkas di sini tidak
  tampil di UI, aturan itu tidak berlaku untuk dokumen).
- Verifikasi lokal memakai project compose sendiri (`-p simpas-staging`) dan volume sendiri: database dev
  `simpas_test` tidak boleh tersentuh.

## Review Focus

- Basic Auth dan WebSocket: browser harus tetap tersambung ke `/app` di balik Basic Auth (dicek di Task 3 lewat
  Playwright `realtime.spec.ts` dengan `httpCredentials`).
- Ekspor lewat antrean: job dikerjakan container `queue`, berkas dibaca container `app` dari mount yang sama
  (dicek di Task 3: ekspor Excel terunduh, bukan 410).
- Izin berkas: container web (www-data) dan worker harus bisa menulis `storage/` dan `bootstrap/cache` yang dibuat
  `deploy.sh` (dicek di Task 3: unggah berkas pengajuan lalu unduh).
- `reset-data.sh` dijalankan tanpa sengaja: harus menolak tanpa konfirmasi nama host yang persis (dicek Task 2).
- Deploy ulang saat worker hidup: worker harus memakai kode baru (`queue:restart` + restart container, dicek Task 2
  dengan menjalankan `deploy.sh` dua kali).

---

### Task 1: Image dan compose staging

**Files:**
- Create: `docker/staging/Dockerfile`
- Create: `docker/staging/Caddyfile`
- Create: `docker-compose.staging.yml`
- Create: `.env.staging.example`

**Interfaces:**
- Produces: service `app` (php-fpm :9000), `caddy` (:80/:443 di VM; `${HTTP_PORT:-80}`/`${HTTPS_PORT:-443}`),
  `postgres`, `queue`, `reverb` (:8080 internal), `scheduler`. Variabel `.env`: `SITE_HOST` (mis.
  `152.67.1.2.sslip.io` atau `localhost`), `BASIC_AUTH_USER`, `BASIC_AUTH_HASH` (keluaran `caddy hash-password`),
  plus variabel Laravel biasa. `APP_URL=https://${SITE_HOST}`, `VITE_REVERB_HOST=${SITE_HOST}`,
  `VITE_REVERB_PORT=${HTTPS_PORT}`, `VITE_REVERB_SCHEME=https`, `REVERB_HOST=reverb`, `REVERB_PORT=8080`,
  `REVERB_SCHEME=http`, `DB_HOST=postgres`, `DB_PORT=5432`.

- [ ] **Step 1:** `docker/staging/Dockerfile` dari `php:8.4-fpm`: paket sistem dan ekstensi sama dengan `Dockerfile`
  lokal (`pdo_pgsql mbstring exif pcntl bcmath gd`, plus `zip`/`intl` hanya bila `composer install` memintanya),
  Composer dari `composer:2`, Node + npm dari apt. `upload_max_filesize`/`post_max_size` minimal sama dengan batas
  unggah terbesar di FormRequest (cari `max:` kilobyte di `app/Http/Requests`).
- [ ] **Step 2:** `Caddyfile`: situs `{$SITE_HOST}`; `basic_auth` untuk semua path; `header X-Robots-Tag "noindex, nofollow"`;
  `handle /app*` → `reverse_proxy reverb:8080`; sisanya `root * /var/www/html/public`, `php_fastcgi app:9000`,
  `file_server`; `encode zstd gzip`. Untuk `SITE_HOST=localhost` Caddy otomatis memakai CA internal.
- [ ] **Step 3:** `docker-compose.staging.yml`: semua service PHP memakai image dari Step 1 dan mount `.:/var/www/html`;
  `queue` = `queue:work --tries=1` (bukan `listen`), `reverb` = `reverb:start --host=0.0.0.0 --port=8080`,
  `scheduler` = `schedule:work`; `restart: unless-stopped`; Postgres tanpa port terbuka ke host; volume
  `caddy_data`, `caddy_config`, `postgres_data`. Variabel Laravel dibaca dari `.env` (bukan blok `environment`).
- [ ] **Step 4:** `.env.staging.example` berisi semua variabel di atas dengan komentar singkat, `APP_KEY=` kosong.
- [ ] **Step 5:** `docker compose -f docker-compose.staging.yml --env-file .env.staging.example config -q` → keluar 0.
- [ ] **Step 6: Commit** `feat(staging): image dan compose staging QA`

### Task 2: Skrip deploy dan reset

**Files:**
- Create: `scripts/staging/deploy.sh`
- Create: `scripts/staging/reset-data.sh`

**Interfaces:**
- Consumes: service dan `.env` dari Task 1. Kedua skrip dijalankan dari akar checkout, memakai
  `docker compose -f docker-compose.staging.yml` (nama project bisa ditimpa `COMPOSE_PROJECT_NAME`).

- [ ] **Step 1:** `deploy.sh` (`set -euo pipefail`): tolak bila `.env` tidak ada; `git pull --ff-only` kecuali
  `SKIP_PULL=1`; `docker compose build`; `up -d postgres app`; `composer install --no-interaction` dan
  `npm ci && npm run build` di container `app`; `key:generate` hanya bila `APP_KEY` kosong; `migrate --force`;
  `storage:link` (idempoten); `optimize`; `chown -R www-data storage bootstrap/cache`; `up -d` semua;
  `queue:restart`; `restart queue reverb scheduler`.
- [ ] **Step 2:** `reset-data.sh`: cetak `SITE_HOST` dari `.env`, minta ketik ulang persis; selain itu keluar 1
  tanpa menyentuh apa pun. Lalu `migrate:fresh --seed --force`, `db:seed --class=QaDataSeeder --force`, kosongkan
  `storage/app/private/*` dan `storage/app/public/*` yang lama (berkas dari seed sebelumnya tidak lagi dirujuk).
- [ ] **Step 3:** `docker run --rm -v "$PWD":/mnt koalaman/shellcheck:stable scripts/staging/*.sh` → tanpa temuan.
- [ ] **Step 4:** `echo salah | scripts/staging/reset-data.sh` → keluar 1, pesan "dibatalkan", database utuh.
- [ ] **Step 5: Commit** `feat(staging): skrip deploy dan reset data uji`

### Task 3: Verifikasi tumpukan di mesin lokal

Tidak ada berkas baru kecuali bila verifikasi menemukan bug di Task 1–2 (perbaiki di berkas itu).

- [ ] **Step 1:** `free -m` ≥ 3 GB tersedia; checkout bersih kedua di `$CLAUDE_JOB_DIR/tmp/staging-check` (git
  worktree dari cabang ini), `.env` dari `.env.staging.example` dengan `SITE_HOST=localhost`, `HTTPS_PORT=8443`,
  `HTTP_PORT=8081`, `COMPOSE_PROJECT_NAME=simpas-staging`, hash Basic Auth dari
  `docker run --rm caddy:2 caddy hash-password --plaintext qa`.
- [ ] **Step 2:** `SKIP_PULL=1 scripts/staging/deploy.sh` → selesai tanpa galat; `reset-data.sh` dengan konfirmasi
  `localhost` → seed selesai.
- [ ] **Step 3:** `curl -k -o /dev/null -w '%{http_code}' https://localhost:8443/` → `401`; dengan `-u qa:qa` → `200`
  dan header `x-robots-tag`.
- [ ] **Step 4:** Playwright `tests/e2e/smoke.spec.ts` dan `realtime.spec.ts` dengan `baseURL`
  `https://localhost:8443`, `ignoreHTTPSErrors`, `httpCredentials {qa, qa}` (konfigurasi sementara di job tmp,
  bukan mengubah `playwright.config.ts`) → lulus. Bila spec bergantung pada fixture E2E lokal, catat dan uji
  manual yang setara: masuk sebagai akun qa1, unggah berkas pengajuan lalu unduh, minta ekspor Excel lalu unduh,
  dan WebSocket `wss://localhost:8443/app/...` berstatus 101 di log Caddy.
- [ ] **Step 5:** `SKIP_PULL=1 scripts/staging/deploy.sh` kedua kali → idempoten, worker hidup lagi.
- [ ] **Step 6:** `docker compose -p simpas-staging -f docker-compose.staging.yml down -v`, hapus worktree tmp.

### Task 4: Panduan dan catatan

**Files:**
- Create: `docs/qa/staging.md`
- Modify: `docs/qa/data-uji.md` (tautan ke staging, Basic Auth)
- Modify: `docs/plans/2026-11-11-fase-reuse-catatan-bug.md` (B27 → Selesai, dengan commit)
- Modify: `docs/plans/2026-11-13-paket-a-masukan-pemilik.md` (status langkah 4 K1)

- [ ] **Step 1:** `staging.md` urut: daftar Oracle (region rumah tak bisa diganti, cek A1 tersedia), buat VM A1
  2 OCPU/12 GB Ubuntu 24.04 + kunci SSH, buka TCP 80/443 di Security List, aturan iptables Ubuntu Oracle
  (`iptables -I INPUT 6 -p tcp --dport 80 -j ACCEPT` dan 443, lalu `netfilter-persistent save`), pasang Docker
  Engine + plugin compose, deploy key GitHub read-only, clone + checkout cabang, isi `.env` (SITE_HOST
  `<ip>.sslip.io`, hash Basic Auth), `deploy.sh`, `reset-data.sh`, membagikan URL + Basic Auth ke QA; bagian
  "Masalah umum": out of capacity, reklamasi VM menganggur 7 hari, sertifikat gagal karena port 80 tertutup,
  deploy ulang sesudah push.
- [ ] **Step 2:** catatan bug dan status rencana diperbarui.
- [ ] **Step 3:** `npx prettier --check docs/qa/staging.md` tidak wajib (prettier repo hanya `resources/js`); cukup
  baca ulang tautan dan perintah.
- [ ] **Step 4: Commit** `docs(staging): panduan VM Oracle dan tutup B27`

### Task 5: Gerbang dan merge lokal

- [ ] **Step 1:** tidak ada kode aplikasi berubah (`git diff --stat d716fb2a -- app resources config routes` kosong),
  jadi gerbang PHP/JS tidak berubah hasilnya; tetap jalankan `npm run lint` dan `vendor/bin/pint --test` (murah)
  sebagai bukti. Pest/PHPStan tidak dijalankan ulang, dan itu disebut di laporan.
- [ ] **Step 2:** fast-forward `feat/init-be-development` ke cabang worktree (permintaan pemilik), tanpa push.
