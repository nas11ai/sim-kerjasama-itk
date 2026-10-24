# Gelombang 3 — UserFormController keluar dari daftar legacy

**Status:** selesai — `UserFormController` keluar dari `quality-legacy.txt` (plafon 98 → 97, `max.mixed`
20 → 19). Diukur pada `94b35ba`.

## Pengukuran awal

579 baris, tujuh aksi. `dashboard()` ~125 loc dengan N+1 per detail fase, `submitForm()` dan `saveDraft()`
menyalin rantai penjaga yang sama, `persistResponses()` membawa satu-satunya `mixed` yang dicatat plafon.

## Temuan — diverifikasi di kode

**F1 — Unggahan berkas wizard tidak pernah tersimpan.** `hasFile('responses')` hanya memeriksa elemen
tingkat pertama, padahal tiap elemen berbentuk `['value' => UploadedFile]`. Cabang penyimpanan berkas tak
pernah jalan dan `input()` tidak memuat berkas, jadi jawaban field berkas hilang diam-diam.

**F2 — Dasbor dan layar tahap membaca pengajuan lintas periode.** Keduanya mencari per `form_id` +
`submitted_by` dengan `first()`. Form yang dipakai ulang di periode baru menandai fasenya sudah selesai, dan
wizard periode baru membuka pengajuan periode lalu — sementara simpan menulis ke baris periode yang benar.

**F3 — Fase di URL tidak dicocokkan dengan periodenya.** `/submission-period/{period}/form-phase/{phase}`
menerima pasangan apa saja.

**F4 — Jawaban untuk field form lain diterima.** Validator hanya `exists:form_fields,id`; berlaku juga di
biodata.

**F5 — Statistik review dasbor peneliti membantah dasbor reviewer.** Dihitung dari `ReviewSummary`
(termasuk ulir penugasan yang sudah diganti, BR-REV-10), bukan dari penugasan seperti
`BuildReviewerDashboard`. Kartu "Ditolak" menghitung `status = 'closed'`, status yang menurut
`02_ubiquitous_language.md` tidak pernah ada — selalu 0.

**F6 — Berkas disimpan sebelum penjaga.** Kiriman yang ditolak 403/409 meninggalkan berkas yatim; batas
10MB hanya ada di jalur submit.

**F7 — Kode mati.** Route `submissions.index` tanpa prefix menggandakan `reviewer.submissions.index` dengan
filter status ulir; `user.form-submission.data` tak dipanggil layar mana pun; `EmailNotificationService`
disuntik tapi tak dipakai; saringan permission/organisasi kedua di dasbor mengulang `accessibleBy()`; form
yang terbuka lewat dua kontrol akses (ADR 0013) terhitung dua kali, padahal wizard menampilkannya sekali.

## Keputusan

- **K1** — Controller jadi cangkang: FormRequest → DTO → Action/Query → respons.
- **K2** — Baca lewat `Queries/Submission/`: `ResearcherDashboardPeriods`, `ResearcherFormPhaseSteps`,
  `AccessibleFormPhaseDetail`, `ResearcherStudyProgram` (diekstrak dari `ResearcherPeriodOverview`).
- **K3** — Pengajuan yang ditampilkan dicari seperti `ResolveWizardSubmission`: induk, form, periode,
  peneliti, id terbesar. Fase harus terpasang di periode URL lewat pivot.
- **K4** — Simpan lewat `SaveSubmissionDraft` beraudit; submit memanggilnya lalu `SubmitFormSubmission`.
  Penjaga 403/409/fase/lock di satu tempat, berkas disimpan setelah semuanya lolos.
- **K5** — `FieldAnswerWriter` (Services) dan trait `ReadsFieldAnswers` dipakai wizard dan biodata; penulis
  menolak field milik form lain. Batas 10MB lewat `after()` untuk simpan dan submit.
- **K6** — Statistik review dasbor peneliti dari `BuildReviewerDashboard`; kartu "Ditolak" dicabut. Dua
  route mati dicabut beserta entri Ziggy-nya.

## Utang yang tersisa

`User/FormPhase.vue` tidak mengisi ulang jawaban tersimpan (`user_submission.responses` dikirim tapi tak
dibaca), sedangkan simpan menggantikan seluruh jawaban. Membuka ulang wizard lalu menyimpan menghapus
jawaban sebelumnya. Tidak diperbaiki di sini — layar ini diganti layar SIMPAS v2.

Test: `tests/Feature/Submission/ResearcherWizardPagesTest.php`.
