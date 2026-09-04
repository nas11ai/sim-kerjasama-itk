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

## Kosakata langkah

Frasa di bawah sudah terbentuk sendiri selama Wave 1a dan **dibakukan** — pakai ini
apa adanya, jangan bikin varian. Tanpa daftar ini, "Given waktu sekarang", "Given saat
ini", dan "Given tanggal hari ini" akan hidup berdampingan dan masing-masing menuntut
fixture-nya sendiri.

| Frasa baku | Dipakai untuk |
| ---------- | ------------- |
| `Given waktu sekarang "<datetime>"` | Menetapkan jam sistem |
| `Given submission "<id>" berstatus <STATUS>` | Menyiapkan submission pada status tertentu |
| `Given submission "<id>" milik "<orang>"` | Menetapkan `submitted_by` |
| `Given "<orang>" berorganisasi "<org>" dan punya permission "<perm>"` | Menyiapkan aktor beserta akses |
| `Given skema "<nama>" dengan <atribut>` | Menyiapkan skema beserta rule-nya |
| `When "<orang>" membuka fase "<fase>"` | Aksi membuka satu FormPhaseDetail |
| `When "<orang>" membuka daftar Form` | Aksi melihat Form yang bisa diakses |
| `When Operator <aksi> "<id>"` | Aksi operator atas sebuah submission |
| `Then akses ditolak dengan <kode>` | Penolakan berkode HTTP |
| `Then penyimpanan ditolak dengan <kode>` | Penolakan saat menyimpan |
| `Then status "<id>" menjadi <STATUS>` | Perubahan status yang teramati |
| `Then <aktor> menerima notifikasi <jenis>` | Efek notifikasi |

**Aturannya:** kalau langkah yang kamu butuhkan tidak ada di sini, boleh menambah — tapi
tambahkan barisnya ke tabel ini dalam PR yang sama. Frasa yang dipakai sekali dan tidak
pernah lagi adalah tanda skenarionya terlalu spesifik, atau kosakatanya belum ketemu.

Nilai di dalam tanda kutip adalah parameter; sisanya harus persis. Istilah domain
mengikuti [ubiquitous language](../ddd/02_ubiquitous_language.md) — `NEEDS_REVISION`,
bukan "perlu revisi".

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
