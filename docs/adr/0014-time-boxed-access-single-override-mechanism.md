# 0014 — Akses berbatas waktu: tiga sumbu kendali, satu mekanisme pengecualian

**Status:** Diterima
**Tanggal:** 2026-09-04
**Melengkapi:** BR-FE-08, BR-FE-15, BR-RO-01..07

> **Catatan 2026-11-10 ([ADR 0019](0019-fase-template-jadwal-periode-form-evaluasi-berversi.md)).**
> Sumbu "kapan" kini dibaca dari **kalender periode milik pengajuan**: langkah fase
> menyebut jenis tenggat + siklus, bukan menunjuk `SubmissionDate`. Substansi ADR ini
> tetap — pengecualian waktu tetap satu mekanisme, `FormSubmissionOverride` per
> pengajuan + langkah.

## Konteks

Muncul kebutuhan berulang: setelah periode tutup, sebuah data kadang **tetap perlu bisa
diakses** — contohnya luaran pengajuan yang terlambat diunggah. Pertanyaannya, apakah
itu diselesaikan dengan permission ("beri user hak khusus") atau dengan batas waktu
("buka aksesnya sampai tanggal tertentu").

Menelusuri seluruh BR, sistem ini ternyata **sudah** memakai tiga cara mengendalikan
akses, dan ketiganya menjawab pertanyaan yang berbeda:

| Sumbu | Pertanyaan | Ditegakkan oleh |
| ----- | ---------- | --------------- |
| **Siapa** | Siapa yang boleh | Spatie permission, `FormAccessControl` (BR-FE-02), BR-RO-07, BR-SM-04 |
| **Kapan** | Dalam rentang waktu mana | `SubmissionDate` + BR-FE-08, `is_force_closed` (BR-SCH-07), rentang aktif reviewer (BR-REV-09) |
| **Keadaan** | Pada status apa record-nya | BR-BUD-02 (anggaran terkunci di status terminal), BR-SM-15 (target terkunci di REJECTED/WITHDRAWN) |

Ketiganya **bukan alternatif satu sama lain** — mereka sumbu yang saling tegak lurus.
Sebuah aksi baru boleh berjalan kalau lolos ketiganya. Memperlakukannya sebagai pilihan
("pakai permission *atau* batas waktu") adalah akar dari dua kesalahan yang mahal:

**Kesalahan pertama — pengecualian waktu diberikan sebagai permission.** Kalau
keterlambatan diselesaikan dengan memberi user permission "boleh edit setelah deadline",
permission itu **tidak pernah kedaluwarsa**. Enam bulan kemudian tidak ada yang ingat
kenapa ia ada, dan mencabutnya terasa berisiko. Hak istimewa menumpuk diam-diam.

**Kesalahan kedua — tiap domain membangun mekanisme waktunya sendiri.** Sistem ini sudah
punya tiga penjaga waktu (deadline fase, force-close periode, rentang aktif reviewer).
Menambah yang keempat khusus luaran, kelima khusus monev, dan seterusnya, berarti lima
tempat berbeda yang harus diperiksa saat sebuah akses ditolak tanpa alasan jelas.

**Celah yang ditemukan.** Tabel Access Control di
[`supporting/03_research_output.md`](../requirement-analysis/ddd/supporting/03_research_output.md)
hanya memuat sumbu *siapa*. BR-RO-01..07 tidak menyebut waktu sama sekali — padahal
lifecycle monev menempatkan "Upload Luaran" sebagai `FormPhaseDetail` ke-7 dengan
deadline `Batas Akhir`. Dua dokumen menggambarkan aturan yang berbeda untuk data yang sama.

## Keputusan

**1. Ketiga sumbu dinyatakan eksplisit dan dijaga tetap tegak lurus.** Setiap aturan
akses baru harus jelas menjawab sumbu yang mana. Aturan yang mencampur dua sumbu dalam
satu kalimat dipecah.

**2. Pengecualian terhadap batas waktu memakai satu mekanisme: `FormSubmissionOverride`.**
Bentuknya sudah benar dan tidak perlu diganti:

| Sifat | Kenapa penting |
| ----- | -------------- |
| Ber-scope (`form_submission_id` + `form_phase_detail_id`) | Membuka satu pintu, bukan seluruh gedung (BR-FE-10) |
| Punya `expires_at` | Pengecualian ikut kedaluwarsa — tidak ada hak abadi |
| `reason` wajib | Ada jejak kenapa dibuka (BR-FE-09) |
| Bisa `revoke()` | Bisa ditutup sebelum waktunya habis |

**Dilarang memberi permission permanen sebagai jalan keluar keterlambatan.** Permission
menjawab "siapa", bukan "sampai kapan"; memakainya untuk urusan waktu menghasilkan hak
yang tidak pernah mati.

**3. Sumber daya yang tunduk waktu digantungkan pada `FormPhaseDetail`.** Dengan begitu
ia otomatis mewarisi BR-FE-08 (blok setelah deadline), BR-FE-15 (tetap terbaca
read-only), dan mekanisme override — tanpa satu baris aturan baru. Luaran sudah memenuhi
syarat ini: ia Detail ke-7 dalam lifecycle.

**4. Membaca setelah deadline tidak butuh override.** BR-FE-15 sudah menjamin fase yang
lewat tetap tampil read-only. Yang butuh override hanya **menulis**. Ini memperkecil
jumlah override yang perlu diterbitkan Operator secara drastis — sebagian besar keluhan
"tidak bisa diakses" sebenarnya soal membaca.

## Audit tiga sumbu per bounded context

Prinsip ini diuji ke seluruh BC, bukan hanya ke luaran. Hasilnya menemukan tiga lubang
lain yang bentuknya sama: satu sumbu tidak pernah dinyatakan, lalu diisi tebakan saat
implementasi.

| Bounded context | Siapa | Kapan | Keadaan | Temuan |
| --------------- | ----- | ----- | ------- | ------ |
| Submission | BR-SM-04, BR-SM-07 | *diwarisi* BR-FE-08 | BR-SM-05 | Sumbu waktu tidak pernah dinyatakan di BC-nya sendiri — diwarisi diam-diam dari Form Engine |
| Budget | **kosong → BR-BUD-09** | *diwarisi* | BR-BUD-02, BR-BUD-03 | **Tidak ada satu pun aturan siapa** yang boleh menyunting anggaran — padahal ini data paling sensitif secara finansial |
| Target Luaran | BR-SM-15 | **sengaja tidak ada** | BR-SM-15 | Pengecualian yang disengaja: target menyesuaikan kegiatan berjalan, jadi ia tidak dibatasi deadline |
| Review | BR-REV-02, BR-REV-11/12 | BR-REV-09 + deadline detail | BR-REV-08 | Lengkap |
| Monev | diwarisi | BR-MON-05 | BR-MON-02, BR-MON-08 | Lengkap |
| Research Output | BR-RO-07 | **kosong → BR-RO-08** | BR-RO-01, BR-RO-06 | Temuan awal yang memicu ADR ini |
| Identity | BR-IAM-09 | BR-IAM-06 | BR-IAM-04, BR-IAM-05 | Lengkap |
| Reporting | BR-RPT-05 | BR-RPT-03 | BR-RPT-04 | Lengkap |
| System Config | *implisit admin* | tidak relevan | BR-SC-01, BR-SC-02 | Sumbu siapa implisit — risiko rendah, tidak diubah |

### Lubang paling besar: tidak ada waktu setelah submit

Contoh lifecycle di [`supporting/02_monev.md`](../requirement-analysis/ddd/supporting/02_monev.md)
memberi Detail 1 (Pengajuan) **dan** Detail 2 (Evaluasi Pengajuan) deadline yang sama,
`"Batas Submit"`. Dibaca bersama BR-FE-08, akibatnya:

- **Reviewer tidak punya waktu kerja.** Evaluasi baru mulai setelah pengajuan ditutup,
  padahal detail evaluasinya kedaluwarsa pada tanggal penutupan itu juga.
- **Revisi mustahil.** BR-BUD-03 menyatakan anggaran tetap bisa disunting saat
  NEEDS_REVISION, tapi status itu baru tercapai setelah review selesai — jauh setelah
  `"Batas Submit"` lewat. Detail-nya sudah terkunci.

Keempat label `SubmissionDate` yang dikenal dokumen ini — `Batas Submit`, `Batas Monev I`,
`Batas Monev II`, `Batas Akhir` — memang tidak memuat satu pun tanggal untuk evaluasi
maupun revisi.

Ini **bukan cacat model**: BR-FE-07 sudah mewajibkan setiap `FormPhaseDetail` punya
`submission_date_id` sendiri. Yang keliru adalah contoh yang memakai ulang satu tanggal
untuk dua detail dengan aktor berbeda. **BR-FE-17** ditambahkan supaya kekeliruan itu
tidak menular ke konfigurasi sungguhan.

Berapa lama jendela revisi seharusnya tetap **belum diputuskan** — itu kebijakan LPPM,
bukan turunan aturan. Terdaftar sebagai [register #12](../requirement-analysis/bdd/open-questions.md).

---

## Konsekuensi

- **BR-RO-08** ditambahkan: penambahan dan penyuntingan Research Output tunduk pada
  deadline `FormPhaseDetail` tempatnya bergantung.
- Layar `RESEARCHER-06-*` harus menampilkan keadaan read-only setelah deadline, sejajar
  dengan yang sudah dilakukan layar anggaran.
- Operator mendapat satu alur untuk semua keterlambatan — `OPERATOR-02-03` Perpanjang
  Tenggat — bukan satu alur per domain.
- Rentang aktif reviewer (BR-REV-09) **tetap terpisah** dan tidak dilebur ke override:
  ia membatasi keanggotaan seseorang sebagai reviewer, bukan membuka akses ke satu
  submission. Sumbu "siapa", bukan "kapan".

## Alternatif yang ditolak

**Override polimorfik (bisa menunjuk entitas apa pun).** Terlihat lebih fleksibel, tapi
menghilangkan kemampuan menjawab "akses apa saja yang sedang terbuka untuk pengajuan
ini" dengan satu query, dan membuat audit lebih sulit. Selama setiap sumber daya
berwaktu bisa digantungkan ke `FormPhaseDetail`, keluwesan itu tidak dibutuhkan.

**Kolom deadline sendiri di tiap tabel domain.** Menyebar kebenaran waktu ke banyak
tempat; memperpanjang tenggat jadi harus menyentuh beberapa tabel, dan pasti ada yang
terlewat.

**Permission bertanggal kedaluwarsa (`expires_at` pada permission).** Menggabungkan
sumbu "siapa" dan "kapan" ke dalam satu mekanisme. Terdengar rapi, tapi membuat
pertanyaan sederhana "boleh apa user ini" jadi bergantung waktu — dan seluruh
`getAllPermissions()` Spatie harus dibungkus ulang. Ongkosnya jauh melebihi manfaatnya.
