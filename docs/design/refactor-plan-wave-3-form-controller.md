# Gelombang 3 — FormController keluar dari daftar legacy

**Status:** selesai — `FormController` keluar dari `quality-legacy.txt` (plafon 102 → 101).
Diukur pada `f7b8294`.

## Pengukuran awal

Ukuran berkas, prosa komentar; `index()` loc 52; `store()` loc 52, cc 9; `update()` loc 97, cc 12.

## Temuan — diverifikasi di kode

**F1 — Field dan opsi milik form lain bisa dibajak.** `update()` memvalidasi `fields.*.id` dan
`options.*.id` hanya dengan `exists`, lalu `FormField::find()` menulis `form_id` form yang disunting.
Field form lain berpindah diam-diam; opsi pun berpindah antarfield.

**F2 — Hapus massal sebagian.** Tanpa transaksi; penjaga BR-FE-19 menolak Form yang dipakai di
tengah jalan, tapi Form sebelumnya sudah terhapus.

**F3 — Tanpa audit.** Buat, ubah, hapus, duplikasi tak tercatat (BR-RPT-09), padahal
`NotifyFormChange` menyebut perubahan Form "sudah dicatat di jalur penyuntingannya".

**F4 — Parameter daftar mentah.** `sort_by`/`sort_order`/`per_page` langsung ke query: arah
tak dikenal → 500; `form_type` bukan angka → galat bigint PostgreSQL.

## Keputusan

- **K1** — `Services/Form/FormFieldWriter` menyamakan field/opsi; id yang bukan milik form/field
  itu ditolak sebagai galat validasi. Field yang dibuang di-soft-delete per model (BR-FE-12).
  Kiriman tanpa kunci `fields` = tidak ada perubahan field (sejalan FE-CHANGE-14 untuk opsi).
- **K2** — Action beraudit `CreateForm`, `ReviseForm`, `DeleteForm`, `DeleteFormsInBulk`
  (semua-atau-tidak, alasan menyebut judul form), `DuplicateForm`. Snapshot di `Form::auditSnapshot()`.
- **K3** — Daftar lewat `Queries/Form/FormDirectory` dengan whitelist; normalisasi urutan, halaman,
  dan filter aktif dibagi dengan `FormPhaseDirectory` lewat `Queries/Concerns/NormalisesListFilters`.
- **K4** — `actor()` yang tersalin di tiga controller menjadi trait `Controllers/Concerns/ResolvesActor`.

Test: `tests/Feature/FormEngine/FormCrudTest.php`.
