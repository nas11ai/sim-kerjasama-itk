# 0003 — PHP 8.4 diseragamkan di semua environment

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Tiga environment memakai tiga versi PHP yang berbeda, dan hanya satu yang
memenuhi `composer.json`:

| Environment       | PHP     | Status                                    |
| ----------------- | ------- | ----------------------------------------- |
| `Dockerfile`      | 8.3     | melanggar `"php": "^8.4"` di composer.json |
| `ci.yml`          | 8.4     | satu-satunya yang benar                    |
| Mesin developer   | 8.5     | `composer install` gagal                   |

Developer yang memakai Docker menjalankan aplikasi di environment yang tidak
memenuhi syarat dependency-nya sendiri — akar klasik "kok di gue jalan tapi CI
merah".

PHP 8.5 juga belum bisa dipakai: `phpoffice/phpspreadsheet` 1.30.5 (transitif
dari `maatwebsite/excel`) membatasi `php >=7.4.0 <8.5.0`, sehingga
`composer install` menolak jalan.

## Keputusan

PHP **8.4** di semua environment. `Dockerfile` dinaikkan dari `php:8.3-cli` ke
`php:8.4-cli`, dengan komentar yang menyebut ketiga tempat yang harus tetap
sinkron: `Dockerfile`, `ci.yml`, dan constraint `php` di `composer.json`.

Menaikkan ke 8.5 diblokir sampai `maatwebsite/excel` mendukungnya.

Konsekuensi praktis untuk tooling: karena resolusi dependency harus terjadi di
PHP 8.4, penambahan paket dijalankan di dalam container 8.4, bukan di host —
lockfile yang di-resolve di 8.5 bisa berbeda dari yang akan CI hasilkan.

## Konsekuensi

- Satu versi runtime untuk semua orang; kelas bug "jalan di lokal, gagal di CI"
  hilang.
- Developer dengan PHP host bukan 8.4 harus memakai container untuk menjalankan
  Pest dan Composer. Caranya didokumentasikan di `.github/CODING_STANDARDS.md`
  dan `AGENTS.md`.
- `phpoffice/phpspreadsheet` jadi penghalang upgrade PHP di masa depan. Paket ini
  juga menyumbang tiga advisory keamanan *high* — dua alasan terpisah untuk
  meninjau ulang ketergantungan pada `maatwebsite/excel`.

## Alternatif yang ditolak

**Turunkan `composer.json` ke `^8.3`** supaya cocok dengan Dockerfile. Mundur ke
versi yang lebih tua sementara CI sudah berjalan baik di 8.4.

**Naikkan semuanya ke 8.5.** Diblokir `phpspreadsheet`. Bisa ditinjau ulang
setelah paket itu mendukung 8.5.
