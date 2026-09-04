# BDD — Skenario Bisnis SIMPAS v2

Spesifikasi perilaku sistem dalam format Gherkin. Berdampingan dengan `../ddd/`
(aturan domain) dan `../ux/` (bentuk antarmuka).

**Rancangan lengkap dan alasannya:** `docs/design/bdd-gherkin-baseline.md`.

## Aturan singkat

- Keyword Gherkin **English**, prosa **Bahasa Indonesia**, istilah domain mengikuti `../ddd/02_ubiquitous_language.md`.
- Skenario **deklaratif** — bicara aturan bisnis, bukan klik. Frame wireframe ikut sebagai tag, tidak sebagai langkah.
- Setiap skenario berjudul `[AREA-TOPIK-NN] Kalimat penjelas`. ID **permanen**: skenario yang dihapus tidak melepaskan nomornya untuk dipakai ulang.

## Tag wajib

| Tag | Kardinalitas |
| --- | ------------ |
| `@BR-XX-NN` | ≥ 1, dan BR-nya harus benar-benar terdefinisi sebagai baris tabel di `../ddd/` |
| `@M0`–`@M5` | tepat 1 |
| `@happy` / `@boundary` / `@denial` | tepat 1. Kelas menggambarkan **hasil aksi aktor**, bukan sikap aturannya: `@happy` aksi berhasil, `@denial` aksi ditolak, `@boundary` kasus di tepi aturan (dipakai saat justru batasnya yang jadi pokok skenario, terlepas dari hasilnya). Laporan coverage memakai ini untuk menunjukkan BR mana yang belum punya jalur gagal. |
| `@<FRAME-ID>` | 0..n |

## Menambah skenario

1. Tulis skenario di file `.feature` yang temanya cocok. Kalau file melewati ~25 skenario, temanya dua — pecah.
2. Tambahkan ID-nya ke `pending.txt` dan naikkan `# max:` di sana.
3. Kalau ini skenario pertama untuk sebuah BR, hapus BR itu dari `uncovered.txt` dan turunkan `# max:`-nya. Assertion 9 menolak BR yang sudah punya skenario tapi masih tercatat di sana.
4. Jalankan `composer run quality`.

Saat test Pest-nya ditulis, sebutkan ID di deskripsi test:

```php
it('[FE-TEMPORAL-01] field yang lahir setelah submission tidak diwajibkan', function () {
```

lalu hapus ID itu dari `pending.txt` dan turunkan plafonnya.

## Daftar berplafon

`pending.txt` dan `uncovered.txt` adalah utang yang terlihat. Mesin menjaga plafonnya;
review PR menjaga arahnya **hanya turun** — pembagian kerja yang sama dengan
`excludePaths` di `phpstan-strict.neon`.
