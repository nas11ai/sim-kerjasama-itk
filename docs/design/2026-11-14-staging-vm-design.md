# Staging QA di satu VM gratis

**Status:** rancangan, menunggu persetujuan pemilik (2026-11-14).
**Konteks:** langkah 4 K1 (`docs/plans/2026-11-13-paket-a-masukan-pemilik.md`), menutup B27.

## Maksud

3–4 QA menguji bersamaan atas data yang sama, tanpa biaya, tanpa menyiapkan apa pun di laptop
masing-masing: cukup membuka satu URL. Data uji berasal dari `QaDataSeeder` (qa1–qa4 + [Latar]).

## Tujuan, bukan tujuan, anti-tujuan

- Tujuan: satu URL HTTPS berisi aplikasi lengkap (web, antrean, Reverb, scheduler) yang perilakunya sama
  dengan lokal; pasang ulang dan reset data uji dengan satu perintah masing-masing; panduan langkah demi
  langkah yang bisa diikuti pemilik dari akun Oracle kosong.
- Bukan tujuan: produksi, cadangan data (data uji bisa di-seed ulang), email sungguhan (`MAIL_MAILER=log`),
  CI/CD otomatis, domain berbayar.
- Anti-tujuan: aplikasi yang bisa dimasuki orang asing. Kata sandi akun uji = alamat email dan tertulis
  di `docs/qa/data-uji.md`, jadi URL terbuka tanpa gerbang sama dengan membagikan akun Admin.
  Mengubah kode aplikasi demi staging (aturan 6 AGENTS.md).

## Pendekatan

| Pilihan | Untung | Rugi |
| --- | --- | --- |
| **A. Satu host bersama (dipilih pemilik)** | Disk `local`, antrean `database`, dan Reverb tetap benar karena semua proses satu mesin; B27 selesai tanpa perubahan kode; QA tidak menyiapkan apa pun | Satu kali menyiapkan server |
| B. Tiap laptop + Neon + R2 | Tanpa server | Adapter S3 belum terpasang (dependency baru); ekspor, pengumuman, dan unggahan privat harus pindah disk; job antrean dan siaran Reverb nyasar antar laptop; 4 scheduler; tiap laptop memegang kredensial dan bisa migrate |

B27 ditutup dengan keputusan "staging dijalankan sebagai satu host bersama" (opsi kedua di catatan bug).

## Yang sudah diputuskan

- Host: VM **Oracle Cloud Always Free**, shape Ampere A1 (arm64), Ubuntu 24.04. Kuota A1 dipotong
  menjadi 2 OCPU / 12 GB per tenancy (2026), masih jauh di atas kebutuhan tumpukan ini. Region dekat
  (Singapore atau Batam, `ap-batam-1`) dipilih saat mendaftar dan tidak bisa diganti; panduan meminta
  pemilik memastikan A1 tersedia di region itu.
- Postgres jalan di VM (container), bukan Neon.
- Kode sampai ke VM lewat `git clone` dari GitHub dengan deploy key read-only. Cabang yang dilacak
  VM harus sudah di-push.

## Panggilan saya (boleh dikoreksi)

- **Server web:** Caddy (HTTPS otomatis Let's Encrypt) di depan `php:8.4-fpm`; Caddy juga meneruskan
  `/app` (WebSocket) ke Reverb, jadi browser cukup satu origin di port 443.
- **Nama host tanpa domain:** `<ip-publik>.sslip.io` (gratis, didukung Let's Encrypt). Kalau pemilik
  punya domain, cukup ganti satu variabel.
- **Gerbang:** HTTP Basic Auth di Caddy untuk seluruh situs, satu pasang username/kata sandi untuk
  semua QA, ditambah `X-Robots-Tag: noindex`. Akun aplikasi tetap dari `QaDataSeeder`.
- **Bentuk deploy:** checkout di VM di-mount ke container (seperti `docker-compose.yml` lokal), tanpa
  registry image. Berkas compose terpisah `docker-compose.staging.yml`; `docker-compose.yml` lokal tidak
  disentuh. Aset dibangun sekali per deploy (`npm ci && npm run build`), tanpa Vite dev server.
- **Dependency Composer:** dipasang lengkap termasuk `require-dev`, karena `QaDataSeeder` memakai Faker.
  Telescope tetap mati karena hanya menyala di `APP_ENV=local`.
- **Lingkungan:** `APP_ENV=staging` (`QaDataSeeder` menolak `production`), `APP_DEBUG=false`,
  `MAIL_MAILER=log`, `SESSION_DRIVER/QUEUE_CONNECTION/CACHE_STORE=database` seperti lokal. Dragonfly dan
  MinIO tidak ikut.
- **Skrip di repo** (`scripts/staging/`): `deploy.sh` (pull, composer install, build aset, migrate
  `--force`, cache config/route, restart worker) dan `reset-data.sh` (`migrate:fresh --seed` +
  `QaDataSeeder`, minta konfirmasi ketik nama host). Keduanya dijalankan pemilik di VM.
- **Panduan:** `docs/qa/staging.md`: daftar akun Oracle, buat VM, buka port 80/443 di Security List
  **dan** iptables image Ubuntu Oracle (yang menolak 80/443 secara bawaan), pasang Docker, deploy key,
  isi `.env`, jalankan skrip. Termasuk risiko: VM Always Free bisa direklamasi bila menganggur 7 hari
  (CPU, jaringan, memori < 20%) dan A1 sering "out of capacity" saat dibuat (coba ulang/region lain).
- **Verifikasi tanpa VM:** tumpukan staging dijalankan di mesin lokal (amd64) dengan host `localhost`
  dan sertifikat internal Caddy, lalu dicek: halaman masuk, unggah dan unduh berkas pengajuan, ekspor
  Excel lewat antrean, dan koneksi WebSocket Reverb.

## Diserahkan ke pelaksana

Isi persis Caddyfile dan konfigurasi php-fpm (jumlah worker), urutan langkah di `deploy.sh`, dan cara
`reset-data.sh` meminta konfirmasi.

## Butuh tindakan pemilik

Membuat akun Oracle (kartu kredit untuk verifikasi), membuat VM mengikuti panduan, dan push cabang yang
akan dilacak VM (`feat/init-be-development` belum ada di origin; `origin/staging` adalah cabang lama
Mei 2026 dan tidak boleh ditimpa paksa).
