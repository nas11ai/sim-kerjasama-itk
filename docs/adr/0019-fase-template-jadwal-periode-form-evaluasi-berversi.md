# 0019 — Fase sebagai template, jadwal milik periode, Form Evaluasi berversi per angkatan

**Status:** Diterima
**Tanggal:** 2026-11-10
**Mengubah:** [ADR 0014](0014-time-boxed-access-single-override-mechanism.md) (sumbu "kapan" dibaca dari kalender periode)
**Desain:** [`docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md`](../design/2026-11-10-fase-reuse-dan-versi-form-design.md)
**Peta irisan:** [`docs/plans/2026-11-10-fase-reuse-roadmap.md`](../plans/2026-11-10-fase-reuse-roadmap.md)

## Konteks

Keluhan pemilik produk dari sudut pandang Admin/Operator LPPM:

1. **Tidak ada halaman fase mandiri.** Fase hanya bisa dicapai lewat sebuah Form
   (`/forms/{form}/phases`), padahal `form_phases` tidak punya `form_id`.
2. **Fase tidak bisa dipakai lagi di periode lain.** Satu fase memang bisa ditautkan ke
   banyak periode, tapi tenggat melekat di langkah
   (`form_phase_details.submission_date_id`, `evaluation_date_id`, `revision_date_id`),
   dan setiap `SubmissionDate` milik satu periode. Memakai fase di periode baru berarti
   memindahkan tenggatnya, sehingga angkatan lama kehilangan tanggalnya. Contoh nyata:
   Monev II angkatan 2025 berjalan di 2026 bersamaan dengan pengajuan 2026 pada fase
   yang sama, padahal hanya ada satu set tanggal.
3. **Form Evaluasi tidak bisa dipakai ulang.** `review_evaluation_forms` ber-FK ke satu
   langkah; lembar yang sama dibuat ulang untuk setiap fase dan setiap tahun.
4. **Satu fase bisa memuat beberapa jenis pengajuan.** Siklus monev dihitung sebagai
   "semua langkah sesudah langkah induk di fase yang sama", jadi pengajuan Penelitian
   menganggap langkah Pengmas sebagai siklus monevnya.
5. **Operator bisa salah setup.** Edit Form berlaku seketika, tanpa pratinjau, ringkasan
   dampak, atau jalan pulih.

Masalah 2 akan menghantam pada pergantian periode pertama di produksi.

## Keputusan

**Fase, Form, dan Form Evaluasi adalah pustaka tanpa tanggal; periode memegang kalender;
angkatan (Periode × Fase) memaku versi yang dipakainya.**

Keputusan pemilik (2026-11-10):

1. Satu fase hanya berisi satu jenis pengajuan; Penelitian dan Pengmas berada di fase
   berbeda. Yang dihitung adalah Form pengajuan, bukan baris langkah.
2. Satu periode punya banyak fase; satu fase bisa dipakai di banyak periode.
3. Form Evaluasi dan form monev bisa dipakai di lebih dari satu fase.
4. Form Evaluasi memakai **versi**, bukan "kunci lalu duplikat" — operator bisa lupa
   mengganti rujukan, dan perubahan yang diam-diam tidak berlaku adalah anti-tujuan.
5. Satu angkatan dinilai dengan satu versi Form Evaluasi; ada aksi eksplisit untuk
   menerapkan versi baru ke angkatan berjalan (pengecualian BR-REV-08).
6. Approve dan tolak adalah keputusan Operator; skor reviewer adalah masukan
   ([ADR 0018](0018-review-outcome-at-round-end-operator-approves.md)).
7. Alur **Draf → Pratinjau → Terbitkan** untuk Form, Form Evaluasi, dan Fase; "waktu
   berubah" = waktu diterbitkan.
8. **D1 — satu kalender tenggat per periode** untuk semua fase di dalamnya. Jadwal
   berbeda = periode berbeda.
9. **D2 — struktur fase berversi**, dipaku per angkatan saat pengajuan pertama angkatan
   itu dibuat; tidak ada aksi menerapkan struktur baru ke angkatan berjalan.

Keputusan bentuk (peta irisan K1–K6):

| # | Keputusan |
| --- | --- |
| K1 | Versi = **salin baris** (struktur fase, isi Form Evaluasi), bukan snapshot JSON: FK pengajuan dan jawaban lama tetap benar dan skor lama dihitung ulang dari baris yang sama. |
| K2 | Draf = satu tabel JSON polimorfik `configuration_drafts`; Terbitkan mewujudkan payload menjadi baris, sehingga BR-FE-11/12/18 tidak perlu diubah. |
| K3 | Angkatan tidak punya tabel sendiri: (`submission_period_id`, `form_phase_id`). Pemakuan disimpan di tabel pemakuan masing-masing. Tidak ada state "buka periode". |
| K4 | Langkah fase menyebut **`deadline_type` + `deadline_cycle`**; tanggal dibaca dari kalender periode pengajuan lewat satu pintu, `SubmissionPeriod::deadlineFor()`. |
| K5 | Jenis langkah tanpa kolom sendiri: pengajuan = `submit` tanpa siklus, laporan monev n = `submit` siklus n, Upload Luaran = `output`. |
| K6 | ADR ini ditulis sekali untuk seluruh desain; irisan berikutnya hanya memperbarui "Status implementasi". |

Aturan tenggat yang berlaku sejak R1 (BR-FE-26 versi baru):

- `submit`/`output` dibaca dari kalender periode pengajuan pada (`deadline_type`,
  `deadline_cycle`) langkahnya; Batas Evaluasi pada (`evaluation`, `deadline_cycle`);
  Batas Revisi pada (`revision`, `deadline_cycle`), dan bila tak ada kembali ke tanggal
  kerja langkah.
- Tanggal kerja yang **tidak ada** di kalender **menutup** langkah itu. Batas Evaluasi
  yang tidak ada berarti tak bertenggat. Override (BR-FE-27) tetap bisa membuka; force
  close (BR-SCH-07) tidak.
- Laporan monev dan Upload Luaran memakai periode pengajuan induknya.
- Setiap pengajuan bertahap wajib berperiode
  (`form_phase_detail_id IS NULL OR submission_period_id IS NOT NULL`).

## Konsekuensi

- **DDD** — `generic/01_form_engine.md`: `FormPhaseDetail` kehilangan tiga FK tanggal;
  BR-FE-07, 17, 21, 26, 27 ditulis ulang terhadap kalender periode (R1); aturan baru
  untuk satu jenis pengajuan per fase, struktur fase berversi, draf/terbit, rekaman teks
  saat submit, cek kesiapan (irisan berikutnya). `core/02_review.md`: pemakuan versi per
  angkatan dan pengecualian BR-REV-08 (R3/R4). `supporting/02_monev.md`: tenggat siklus
  dibaca dari kalender periode. `supporting/04_scheme.md`: BR-SCH-07 dibaca terhadap
  periode pengajuan, substansinya tetap. `02_ubiquitous_language.md`: Angkatan, Kalender
  periode, Langkah, Versi Form Evaluasi, Terbitkan.
- **ADR 0014** tetap: satu mekanisme pengecualian waktu (`FormSubmissionOverride`, per
  pengajuan + langkah). Yang berubah hanya sumber tanggal sumbu "kapan".
- Fase yang ditautkan ke periode tanpa tanggal yang dibutuhkan langkahnya tidak menerima
  pengajuan di sana; operator melihat celah jadwal di kartu fase (R1) dan daftar cek
  kesiapan per angkatan (R8).
- **Tanpa migrasi data** (pemilik 2026-11-10: aplikasi belum produksi). Setiap irisan
  hanya mengubah skema; DB dev di-reset `migrate:fresh --seed`, dan `EndToEndFlowSeeder`
  mengikuti setiap perubahan skema. Spec §5 tidak berlaku.
- Isi tanggal periode baru dari periode sebelumnya + 1 tahun (spec §4.9) dibuang.

## Alternatif yang ditolak

**Tanggal tetap di langkah, dipindah saat fase dipakai periode baru** (keadaan K9b
sebelumnya). Angkatan lama kehilangan tanggalnya — persis masalah 2.

**Kalender per angkatan (Periode × Fase).** Fleksibel, tapi setiap fase baru di periode
yang sama menuntut operator mengisi ulang tanggal yang sama. Pemilik memilih D1: jadwal
berbeda cukup dijawab dengan periode berbeda.

**Kunci lalu duplikat untuk Form Evaluasi.** Operator yang lupa mengganti rujukan
menghasilkan perubahan yang diam-diam tidak berlaku.

**Snapshot JSON untuk versi.** Jawaban reviewer dan pengajuan sudah ber-FK ke baris
field/langkah; snapshot memaksa migrasi jawaban dan pembacaan ganda.

**Tabel angkatan sendiri.** Menambah state "buka periode" yang tidak diminta dan harus
dijaga sinkron dengan `submission_period_phases`.

## Status implementasi

| Irisan | Isi | Status |
| --- | --- | --- |
| R1 | Jadwal milik periode | Selesai — kode s.d. `e7828f84` (contract: rujukan tanggal di langkah dihapus) |
| R2 | Pustaka Fase, satu jenis pengajuan per fase | Belum |
| R3 | Form Evaluasi mandiri & berversi | Belum |
| R4 | Terapkan versi baru ke angkatan | Belum |
| R5 | Struktur fase berversi, dipaku per angkatan | Belum |
| R6 | Draf → Pratinjau → Terbitkan untuk Form & Fase | Belum |
| R7 | Rekam teks saat submit | Belum |
| R8 | Cek kesiapan angkatan | Belum |
