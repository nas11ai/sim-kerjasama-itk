# 0010 — Migrasi data legacy ditunda ke spec terpisah

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

Tersedia `database/simpas.sql` — dump SIMPAS v1 berukuran 113 MB dengan skema
yang berbeda total dari v2 (MySQL, ~80 tabel, penamaan campur Indonesia-Inggris:
`judul_penelitian`, `abstrak`, `total_dana`).

Pemetaannya tidak sepele. Contoh: `submissions` legacy harus pecah menjadi
`form_submissions` + `form_field_responses` + `research_members` +
`budget_line_items`, sementara enam tabel luaran (`articles`, `books`,
`intellectual_properties`, `prototypes`, `pks`, `meeting_histories`) harus
menyatu ke `research_outputs` ber-JSONB.

## Keputusan

Migrasi tidak dikerjakan sekarang. Backend dibangun sampai fungsional memakai
seeder dan factory. ETL jadi proyek sendiri dengan spec sendiri, setelah skema v2
stabil.

Dump-nya tetap dipakai **sebagai referensi skema** selama desain, untuk
memastikan skema v2 mampu menampung data lama. Referensi itu sudah membuahkan
hasil konkret: penemuan bahwa `submissions` legacy punya `scheme_id`,
`submission_period_id`, dan `level_of_technology_readiness_id` sebagai kolom FK
sungguhan adalah bukti yang menentukan di
[0005](0005-scheme-selector-writes-to-column.md).

`/database/*.sql` masuk `.gitignore` — 113 MB melebihi batas 100 MB GitHub, dan
sebuah `git add .` yang tidak sengaja akan menggagalkan PR dengan cara yang
membingungkan.

## Konsekuensi

- Skema v2 masih boleh berubah selama M0–M2 tanpa membatalkan pekerjaan ETL.
- Keputusan skema tetap wajib mempertimbangkan kemampuan menampung data legacy —
  itu kriteria desain yang mengikat, bukan sekadar catatan.
- Dump harus dibagikan lewat jalur di luar git (object storage atau transfer
  langsung), dan berisi data pribadi sungguhan sehingga tidak boleh masuk
  environment yang bisa diakses publik.

## Alternatif yang ditolak

**Rancang ETL paralel dengan backend.** Data produksi ikut lebih cepat, tapi
mengunci skema v2 lebih awal — padahal keputusan seperti
[0004](0004-form-phase-detail-id-on-submissions.md) dan
[0005](0005-scheme-selector-writes-to-column.md) baru matang setelah desain
berjalan. ETL yang ditulis sekarang akan ditulis ulang.

**Abaikan dump sepenuhnya.** Kehilangan bukti empiris tentang bentuk data yang
sebenarnya. Keputusan 0005 kemungkinan besar akan salah arah tanpa itu.
