# Gelombang 3 — ReviewEvaluationFormController keluar dari daftar legacy

**Status:** selesai — `ReviewEvaluationFormController` keluar dari `quality-legacy.txt` (plafon 101 → 100).
Diukur pada `be2e73a`.

## Pengukuran awal

445 baris; panjang method (baris fisik) `index()` 52, `store()` 73, `update()` 99; blok muat-field
tersalin tiga kali.

## Temuan — diverifikasi di kode

**F1 — Jawaban reviewer terhapus diam-diam.** `update()` menghapus keras field yang tak dikirim
ulang, dan `review_form_field_responses.review_form_field_id` memakai `ON DELETE CASCADE`. Jawaban
yang sudah disubmit (BR-FE-05) ikut hilang tanpa jejak. Tipe field yang sudah dijawab juga bisa diganti.

**F2 — Field dan opsi milik form lain bisa dibajak.** `fields.*.id`/`options.*.id` hanya dicek
`exists`, lalu `find()` memindahkannya ke form yang disunting (sama dengan F1 FormController).

**F3 — Form evaluasi bisa dipasang ke detail yang tidak direview** dan dipindah ke detail lain
padahal sudah ditugaskan.

**F4 — Tanpa audit** (BR-RPT-09) untuk buat, ubah, hapus, duplikasi, ubah urutan.

**F5 — Cacat kecil.** `description` yang tak dikirim → "Undefined array key"; filter non-angka →
galat bigint 500; `update-order` membalas JSON ke `useForm` Inertia; jawaban `locked` terhitung belum selesai.

**F6 — Dialog Ubah di layar tahap formulir tak pernah bisa menyimpan.** Ia tak mengirim
`form_phase_detail_id` (ditolak `required`); seandainya lolos, ketiadaan `fields` dulu menghapus semua field.

## Keputusan

- **K1** — `Services/Form/ReviewFormFieldWriter` menyamakan field/opsi; id bukan milik form/field itu
  ditolak; field yang sudah dijawab tak bisa dibuang atau berganti tipe (cermin BR-FE-23).
- **K2** — Action beraudit di `Actions/Review/`: `CreateReviewEvaluationForm`, `ReviseReviewEvaluationForm`,
  `DeleteReviewEvaluationForm`, `DuplicateReviewEvaluationForm`, `ReorderReviewEvaluationForms`;
  penjaga `AssertReviewedPhaseDetail`. Snapshot di `ReviewEvaluationForm::auditSnapshot()`.
- **K3** — Baca lewat `Queries/Review/ReviewEvaluationFormDirectory`, `ReviewEvaluationFormChoices`
  (hanya detail `needs_review`), dan `EvaluationFormUsage` (selesai = `submitted_at` terisi).

- **K4** — Pada Ubah, detail yang tak dikirim berarti tetap. Galat `id` hapus dan galat field
  ditampilkan lewat toast di tiga layar Vue yang memanggilnya; sebelumnya tak terlihat sama sekali.

- **K5** — Cermin BR-FE-24: `review_form_field_options` mendapat `deleted_at`. Opsi yang dibuang
  berhenti ditawarkan; `FieldResponseFormatter` membaca opsi `withTrashed` sehingga jawaban lama tetap
  berlabel. Salinan dari Duplikasi hanya membawa opsi yang masih aktif.

Test: `tests/Feature/Review/EvaluationFormCrudTest.php`.
