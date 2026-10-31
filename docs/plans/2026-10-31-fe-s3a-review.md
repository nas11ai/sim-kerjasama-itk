# FE v2 — Irisan S3a: Review oleh Reviewer — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Reviewer kembali bisa bekerja dari UI v2: melihat penugasannya (REVIEWER-03-01), mengisi dan mengirim form evaluasi di samping isi pengajuan (03-03), membuka/membalas/menutup ulir ringkasan dengan syarat keputusan yang terbaca (03-04), dan membaca riwayat evaluasinya (03-02). Menutup W11.

**Architecture:** Semua layar reviewer memakai satu controller tipis `App\Http\Controllers\Review\ReviewerAssignmentController` di grup route `reviewer.` dengan nama `reviewer.assignments.*` (supaya `activePattern` menu "Penugasan Review" menjangkau halaman evaluasi dan ringkasan), dijaga `can:reviewers.evaluate` (§4.4 K1). Penugasan dibaca per `SubmissionReviewer` (satu kartu per pengajuan), bukan per `ReviewerFormAssignment` seperti legacy. Form evaluasi tetap disimpan lewat route `reviewer.evaluation-form.*` yang sudah ada; field evaluasi dikirim sebagai `WizardFieldData` sehingga `WizardFieldInput` S2b dipakai ulang. Nama route legacy yang masih dirujuk halaman legacy lain dialihkan (redirect), bukan dihapus.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest.

**Spec:** `docs/design/fe-v2-design.md` (§4.1–§4.6, §6, baris S3a di §8, W11 di §9). Frame: `../simpas-wireframe/screens/REVIEWER-03-0{1,2,3,4}/screen.js`; `FRAME-AUDIT.md`/`STATE-COVERAGE.md` bagian yang sama. BR: BR-SM-07, BR-REV-05/06/08/10/11/14/15, BR-FE-05/06/17, BR-MON-06.

**Dua session** (dipecah di §8 sebelum dikerjakan, §6): **S3a-1** = Task 1–5 (penugasan + evaluasi, legacy evaluasi dihapus), **S3a-2** = Task 6–9 (ringkasan & diskusi, riwayat, sisa legacy). `ReviewSystem.vue`/`ReviewScorePanel.vue` masih dipakai `Submissions/ShowSubmission` operator, jadi keduanya pindah ke kolom legacy S4; `Reviewer/DashboardPage` milik D1.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris; tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy (halaman `Reviewer/*` jangan disalin — tulis dari wireframe).
- Baris pertama `<script setup>` halaman baru: `// Frame: REVIEWER-03-0N`.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris; kosakata `02_ubiquitous_language.md` ("Ulir", "Ringkasan Review", "Reviewer Pengganti").
- TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- Otorisasi tombol dibaca dari payload backend (`canEdit`, `canCreateThread`, …, ADR 0015); frontend tidak menyusun ulang aturan.
- Mutasi lewat `router`/`useForm`, tanpa `axios`; tanpa dependency baru.
- Test yang mengikat skenario BDD menyebut ID-nya; skenarionya sudah dicakup Pest, jadi `pending.txt`/`uncovered.txt` tidak berubah.
- Gate: `composer run quality` + `npm run quality`. Pest di container:
  `docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Reviewer yang sudah digantikan membuka URL evaluasi/ringkasan lamanya** → 403, bukan halaman kosong (BR-REV-10). → Task 1 test `[SM-ACCESS-06] reviewer pengganti… / reviewer yang digantikan ditolak di halaman evaluasi`.
2. **Form yang belum dimulai** tidak punya `ReviewerFormAssignment`, jadi tidak punya id untuk disimpan; tab-nya harus menawarkan "Mulai Evaluasi" dan sesudahnya kembali ke tab yang sama. → Task 1 test `memulai form kembali ke tab form itu`, Task 2 Vitest `form belum dimulai menawarkan Mulai Evaluasi`.
3. **Tenggat evaluasi lewat**: layar menutup isian **dan** backend menolak simpan/kirim (FE-WINDOW-02). → Task 1 test `[FE-WINDOW-02] simpan draft ditolak setelah tenggat evaluasi lewat`.
4. **Submit dengan field wajib kosong** kembali ke halaman yang sama dengan pesan dari `errors.responses`, isian peramban tidak hilang. → Task 2 Vitest `kesalahan kiriman ditampilkan`.
5. **Reviewer eksternal** tidak pernah menerima skor rekan, bahkan setelah semua selesai (REV-VIS-02) — dijaga payload, bukan CSS. → Task 6 test `[REV-VIS-02] …`.

---

## S3a-1

### Task 1: Read model halaman evaluasi + tenggat evaluasi (BE)

**Files:**
- Create: `app/Http/Controllers/Review/ReviewerAssignmentController.php`, `app/Queries/Review/ReviewerEvaluation.php`, `app/Data/View/Review/{EvaluationSubmissionData,EvaluationFormData}.php`
- Modify: `routes/web.php`, `app/Policies/ReviewerFormAssignmentPolicy.php`, `app/Http/Controllers/ReviewFormResponseController.php`, `tests/Feature/Review/EvaluationResponseTest.php`, `tests/Feature/Review/ReplacedReviewerAccessTest.php`
- Test: `tests/Feature/Review/ReviewerEvaluationPageTest.php`

**Interfaces:**
- Produces: route `GET /reviewer/assignments/{submission}/evaluation` → `reviewer.assignments.evaluation` (middleware `can:reviewers.evaluate`), query opsional `form` (id `ReviewEvaluationForm`).
- Produces: `EvaluationSubmissionData(int $id, string $title, ?string $scheme, ?string $period, string $leadName, int $memberCount, SubmissionStatusData $status)`.
- Produces: `EvaluationFormData(int $formId, ?int $assignmentId, string $title, ?string $description, bool $required, string $state, ?string $dueDate, ?string $submittedAt, ?string $finalNotes, bool $canEdit, list<WizardFieldData> $fields)` — `state` ∈ `not_started|draft|submitted|locked|overdue`; `overdue` = draft/belum dimulai yang `isOverdue()`; `canEdit` = `$viewer->can('evaluate', $assignment)` dan respons masih draft (form belum dimulai: `true` selama tidak `overdue`). Field: `type` = nama `FieldType` huruf kecil, `options` = `{value: option.value, label: option.label}` urut `order`, `value` = jawaban tersimpan, `fileName`/`downloadUrl` null.
- Produces: `ReviewerEvaluation::run(User $viewer, SubmissionReviewer $assignment, ?int $activeFormId): array{submission: EvaluationSubmissionData, answers: list<SubmissionAnswerData>, forms: list<EvaluationFormData>, activeFormId: int|null, evaluationSettled: bool}` — daftar form dari `ReviewerEvaluationChecklist::run($assignment, $submission->formPhaseDetail)`; `answers` dari `ReadableSubmissionAnswers::run`; `activeFormId` jatuh ke form pertama yang belum `submitted` bila argumen null/tak dikenal; `evaluationSettled` = `$assignment->hasSettledEvaluation()`.
- Produces: `ReviewerAssignmentController::evaluation(Request, FormSubmission)` dan helper privat `assignmentOf(Request, FormSubmission): SubmissionReviewer` (Reviewer milik user → `ActiveReviewerAssignment::run`, 403 "Anda tidak ditugaskan sebagai reviewer pengajuan ini." bila null — dipakai lagi oleh Task 3 dan 6).
- Policy: `evaluate` juga menolak `$assignment->isOverdue()` — `// BR-FE-17 — tenggat evaluasi mengikat reviewer sendiri; lewat tenggat, isian tertutup (FE-WINDOW-02).`
- `ReviewFormResponseController`: `showOrCreate` → redirect `reviewer.assignments.evaluation` `[submission, form => form id]`; `submit` → `back()`; `show` dan `showSubmitted` → setelah `viewer()` redirect ke halaman evaluasi dengan `form` yang sama (nama route dipertahankan karena `ReviewSystem.vue` operator masih menautnya sampai S4); `loadSheet` dihapus.

- [ ] **Step 1: Tulis test yang gagal.** `ReviewerEvaluationPageTest.php`: `[SM-ACCESS-05] reviewer yang ditugaskan membuka Review/EvaluationPage` (`component`, `has('forms', 2)`, `where('forms.0.state', 'not_started')`, `where('forms.0.assignmentId', null)`, `has('answers')`, `where('submission.leadName', …)`); `form draft membawa field dan jawaban tersimpan` (`forms.1.fields.0.value`, `forms.1.fields.0.options.0.label`, `forms.1.canEdit` true); `form yang sudah disubmit terkunci` (`state` `submitted`, `canEdit` false); `[SM-ACCESS-06] reviewer yang tidak ditugaskan ditolak` (403); `[SM-ACCESS-06] reviewer yang digantikan ditolak di halaman evaluasi` (403); `pengguna tanpa reviewers.evaluate ditolak` (403); `memulai form kembali ke tab form itu` (POST `reviewer.evaluation-form.start` → `assertRedirect(route('reviewer.assignments.evaluation', [$submission, 'form' => $form->id]))`); `[FE-WINDOW-02] simpan draft ditolak setelah tenggat evaluasi lewat` (`due_date` kemarin → POST `save-draft` 403; payload `state` `overdue`, `canEdit` false); `[FE-WINDOW-01] tenggat yang belum lewat tetap bisa disimpan`. `EvaluationResponseTest`: test `show` legacy → `assertRedirect` ke halaman evaluasi; submit → `assertRedirect()` ke URL asal (`from(...)`); `submitted` → redirect. `ReplacedReviewerAccessTest:195` tetap 403.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Review/ReviewerEvaluationPageTest.php tests/Feature/Review/EvaluationResponseTest.php tests/Feature/Review/ReplacedReviewerAccessTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Controller `// BR-SM-07, BR-REV-10 — hanya penugasan yang masih berlaku membuka layar ini.`
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Feature/FormEngine` + `tests/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model halaman evaluasi reviewer`

### Task 2: `Review/EvaluationPage` (FE)

**Files:**
- Create: `resources/js/types/review.ts`, `resources/js/Pages/Review/EvaluationPage.vue`, `resources/js/Components/Review/{EvaluationSubmissionPanel,EvaluationFormPanel}.vue`, `resources/js/Components/Submission/AnswerList.vue`
- Modify: `resources/js/Components/Submission/InfoSection.vue` (pakai `AnswerList`)
- Test: `tests/frontend/components/Review/EvaluationFormPanel.spec.ts`, `tests/frontend/pages/EvaluationPage.spec.ts`

**Interfaces:**
- Consumes: payload Task 1; `WizardField` (`types/submission-wizard.ts`), `SubmissionAnswer`, `SubmissionStatus` (`types/submission-page.ts`); `WizardFieldInput`, `FilesSection`, `InfoGrid`, `AppBanner`, `ConfirmDialog`, `PageHeader`, `StatusBadge`, `SectionCard`, `Tabs`.
- Produces (TS, cermin Task 1): `EvaluationSubmission`, `EvaluationFormState`, `EvaluationForm`, `EvaluationPageProps { submission; answers: SubmissionAnswer[]; forms: EvaluationForm[]; activeFormId: number | null; evaluationSettled: boolean }`.
- `AnswerList` props `{ answers: SubmissionAnswer[] }`: hanya jawaban non-berkas, blok `<dl>` yang sekarang ada di `InfoSection` (Extract Component).
- `EvaluationSubmissionPanel` props `{ submission: EvaluationSubmission; answers: SubmissionAnswer[] }`: `InfoGrid` (Judul, Ketua, Skema, Periode, Anggota "{n} orang"), `AnswerList`, `FilesSection`, tautan "Buka detail lengkap" → `user.submissions.show` (SM-ACCESS-05).
- `EvaluationFormPanel` props `{ submissionId: number; form: EvaluationForm }`:
  - `not_started` + `canEdit` → tombol "Mulai Evaluasi" → `router.post(route('reviewer.evaluation-form.start'), { submission_id, review_evaluation_form_id: form.formId })`.
  - `overdue` → `AppBanner` lock "Batas waktu evaluasi sudah lewat. Hubungi Operator LPPM bila membutuhkan perpanjangan." (`<!-- BR-FE-17 -->`), tanpa isian.
  - `submitted`/`locked` → `AppBanner` lock "Penilaian sudah dikirim dan tidak dapat diubah. Sampaikan koreksi lewat catatan diskusi." (`<!-- BR-REV-08 -->`) + jawaban baca-saja (`InfoGrid` label/nilai, opsi ditampilkan label-nya) + "Disubmit {formatDate}".
  - `draft` + `canEdit` → `useForm({ responses: Record<string, string | null>, final_notes })` diisi dari `fields[].value`; `WizardFieldInput` per field (`v-model` ke `responses[field.id]`; nilai `File` tidak mungkin karena tak ada field berkas, abaikan); `Textarea` "Catatan Kualitatif" → `final_notes`; "Simpan Draft" → `form.post(route('reviewer.evaluation-form.save-draft', assignmentId), { preserveScroll: true })`; "Submit Evaluasi" lewat `ConfirmDialog` → `form.post(route('reviewer.evaluation-form.submit', assignmentId), { preserveScroll: true })`; `form.errors.responses` tampil sebagai `AppBanner` tone danger. Footnote frame "Setelah evaluasi disubmit, isian tidak dapat diubah lagi — pastikan skor dan catatan sudah final."
  - Komponen dikunci per form: halaman memberi `:key="form.formId + form.state"`.
- `EvaluationPage`: `PageHeader` "Form Evaluasi", subjudul judul pengajuan, back "Penugasan Review" → `reviewer.assignments.index`, badge `StatusBadge`; dua kolom `lg:grid-cols-2`: `EvaluationSubmissionPanel` dan `Tabs` (default `String(activeFormId)`) berisi `EvaluationFormPanel`; tanpa form → `EmptyState` "Tahap ini tidak memiliki form evaluasi aktif."; `evaluationSettled` → `AppBanner` info "Evaluasi Anda selesai. Lanjutkan ke ringkasan dan diskusi." bertaut ke `reviewer.submissions.show` (S3a-2 menggantinya dengan `reviewer.assignments.summary`).

- [ ] **Step 1: Tulis Vitest yang gagal.** `EvaluationFormPanel`: `form belum dimulai menawarkan Mulai Evaluasi` (POST `reviewer.evaluation-form.start` dengan dua id); `[REV-EVAL-01] draft disimpan lewat save-draft dengan jawaban field`; `[REV-EVAL-03] submit evaluasi lewat dialog konfirmasi`; `[REV-EVAL-02] evaluasi terkirim tampil terkunci tanpa tombol simpan`; `[FE-WINDOW-02] tenggat lewat menutup isian`; `kesalahan kiriman ditampilkan`. `EvaluationPage`: `tab awal mengikuti activeFormId`, `tanpa form evaluasi menampilkan keadaan kosong`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Review tests/frontend/pages/EvaluationPage.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman evaluasi reviewer`

### Task 3: Read model halaman penugasan (BE)

**Files:**
- Create: `app/Queries/Review/ReviewerAssignmentCards.php`, `app/Data/View/Review/AssignmentCardData.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Review/ReviewerAssignmentController.php`, `app/Http/Controllers/ReviewerFormAssignmentController.php` (hapus `getMyAssignments`), `app/Http/Controllers/SubmissionViewController.php` (hapus `reviewerSubmissions`), `tests/Feature/Review/ReplacedReviewerAccessTest.php`
- Delete: `app/Queries/Review/ReviewerAssignmentList.php`, `app/Data/Review/ReviewerAssignmentListing.php`, `app/Queries/Review/ReviewerSubmissionList.php`, `tests/Feature/Review/ReviewerAssignmentListTest.php` (aturan scope-nya pindah ke test Task 3)
- Test: `tests/Feature/Review/ReviewerAssignmentIndexTest.php`

**Interfaces:**
- Produces: route `GET /reviewer/assignments` tetap bernama `reviewer.assignments.index` (kini `ReviewerAssignmentController::index`, `can:reviewers.evaluate`), query `filter` ∈ `action|all` (bawaan `action`). `reviewer.submissions.index` menjadi `Route::redirect` ke `/reviewer/assignments` (masih dirujuk `AppSidebar.vue` dan `User/DashboardPage` legacy).
- Produces: `AssignmentCardData(int $submissionId, string $title, string $leadName, ?string $scheme, ?string $dueDate, string $state, string $stateLabel)` — `state` ∈ `evaluation_pending` ("Evaluasi Pending") | `discussion_open` ("Butuh Respon Diskusi") | `done` ("Selesai"). `discussion_open` = evaluasi beres dan ada `ReviewSummary` `open` ber-`reviewer_id` reviewer ini. `dueDate` = `due_date` terdekat di antara form yang belum `submitted`, ISO.
- Produces: `ReviewerAssignmentCards::run(Reviewer $reviewer): array{needsAction: list<AssignmentCardData>, done: list<AssignmentCardData>}` — hanya `SubmissionReviewer` `status = active` (BR-SM-07, BR-REV-10), terbaru dulu; eager-load tanpa N+1.
- Props halaman `Review/AssignmentIndexPage`: `{ needsAction, done, filter: 'action'|'all' }`; `done` dikirim kosong bila `filter = action`.

- [ ] **Step 1: Tulis test yang gagal.** `ReviewerAssignmentIndexTest.php`: `reviewer membuka Review/AssignmentIndexPage dengan penugasan perlu tindakan` (`has('needsAction', 1)`, `where('needsAction.0.state', 'evaluation_pending')`, `where('done', [])`); `ulir terbuka milik reviewer menjadikan kartu butuh respon diskusi`; `filter all menyertakan penugasan selesai`; `[REV-REASSIGN-03] penugasan yang digantikan tidak terdaftar, penggantinya terdaftar`; `penugasan reviewer lain tidak terdaftar`; `pengguna tanpa reviewers.evaluate ditolak` (403); `reviewer.submissions.index dialihkan ke penugasan`. `ReplacedReviewerAccessTest` 250/270/293 disesuaikan ke payload baru (redirect untuk `reviewer.submissions.index`).
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Review/ReviewerAssignmentIndexTest.php tests/Feature/Review/ReplacedReviewerAccessTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Feature/Reviewer` + `tests/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model penugasan reviewer`

### Task 4: `Review/AssignmentIndexPage` (FE)

**Files:**
- Create: `resources/js/Pages/Review/AssignmentIndexPage.vue`, `resources/js/Components/Review/AssignmentCard.vue`
- Modify: `resources/js/types/review.ts` (`AssignmentCard`, `AssignmentIndexPageProps`), `resources/js/lib/navigation.ts` ("Penugasan Review" `activePattern: 'reviewer.assignments.*'`)
- Test: `tests/frontend/components/Review/AssignmentCard.spec.ts`, `tests/frontend/pages/AssignmentIndexPage.spec.ts`, `tests/frontend/lib/navigation.spec.ts` (tambah)

**Interfaces:**
- `AssignmentCard` props `{ card: AssignmentCard }`: judul, "{leadName} · {scheme}", "Deadline evaluasi: {formatDate(dueDate)}" (tanpa tanggal → baris tidak dirender), badge `stateLabel`, aksi per `state`: `evaluation_pending` → "Mulai Evaluasi" `reviewer.assignments.evaluation`; `discussion_open` → "Buka Diskusi", `done` → "Lihat Summary", keduanya ke `reviewer.submissions.show` (S3a-2 → `reviewer.assignments.summary`).
- Halaman: `PageHeader` "Penugasan Review" / "Submission yang ditugaskan kepada Anda untuk dievaluasi."; dua tombol segmen "Perlu Tindakan"/"Semua" → `router.get(route('reviewer.assignments.index'), { filter }, { preserveState: true, replace: true })`; seksi "Perlu Tindakan" dan (bila `filter === 'all'`) "Selesai"; seksi kosong → `EmptyState`.

- [ ] **Step 1: Tulis Vitest yang gagal.** `AssignmentCard`: `evaluasi pending menaut ke halaman evaluasi`, `ulir terbuka menawarkan Buka Diskusi`. `AssignmentIndexPage`: `segmen Semua meminta filter all`, `tanpa penugasan perlu tindakan menampilkan keadaan kosong`. `navigation.spec`: `Penugasan Review aktif di halaman evaluasi`.
- [ ] **Step 2: Jalankan** → FAIL. **Step 3: Implementasi.** **Step 4:** `npm run quality` → PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman penugasan reviewer`

### Task 5: Tutup S3a-1

**Files:**
- Delete: `resources/js/Pages/Reviewer/Assignments/IndexPage.vue`, `Reviewer/EvaluationForm/ShowPage.vue`, `Reviewer/EvaluationForm/SubmittedPage.vue`, `Reviewer/Submissions/IndexPage.vue`
- Modify: `quality-legacy.txt` (empat baris), `quality-budget.txt` (`max.legacy.files` −4), `resources/js/ziggy.js` bila di-commit, `docs/design/fe-v2-design.md` (§8, §9), memory `fe-v2-s1-carryover.md`

- [ ] **Step 1: Periksa di browser** (resep memory `fe-v2-s1-carryover`): reviewer → Penugasan Review → Mulai Evaluasi → Mulai form → isi → Simpan Draft → Submit → form terkunci, banner ringkasan; penugasan berpindah dari "Perlu Tindakan". Laporkan yang dicek dan yang tidak.
- [ ] **Step 2: Gate penuh** `composer run quality` (container) + `npm run quality` → hijau; laporkan apa adanya.
- [ ] **Step 3: `fe-v2-design.md`.** §8: baris S3a dipecah — S3a-1 `☑` (`Review/AssignmentIndexPage`, `EvaluationPage`; legacy empat halaman di atas), S3a-2 `☐` (`SummaryPage`, `HistoryPage`; legacy `Reviewer/Submissions/ShowPage`); `ReviewSystem`, `ReviewScorePanel` pindah ke kolom legacy S4. §9: W20–W22 (di bawah).
- [ ] **Step 4: Memory** — S3a-1 selesai, butir S3a di "Still to carry" diganti S3a-2.
- [ ] **Step 5: Commit** `feat(fe): irisan S3a-1 penugasan dan evaluasi reviewer selesai`

## S3a-2

### Task 6: Read model ringkasan & diskusi (BE)

**Files:**
- Create: `app/Queries/Review/ReviewerSummary.php`, `app/Data/View/Review/OutcomeConditionsData.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Review/ReviewerAssignmentController.php`, `app/Http/Controllers/SubmissionViewController.php` (hapus `reviewerShowSubmission`, `reviewerOf`), `tests/Feature/Review/ReviewThreadAuthorPayloadTest.php`
- Delete: `app/Queries/Review/ReviewerSubmissionView.php`, `tests/Feature/Review/ReviewerSubmissionPageTest.php` (ikatannya pindah ke test Task 6)
- Test: `tests/Feature/Review/ReviewerSummaryPageTest.php`

**Interfaces:**
- Produces: route `GET /reviewer/assignments/{submission}/summary` → `reviewer.assignments.summary`; `reviewer.submissions.show` menjadi redirect ke sana (dirujuk `Reviewer/DashboardPage` sampai D1).
- Produces: `OutcomeConditionsData(int $reviewerCount, int $unsettledCount, int $openThreadCount)` — hanya hitungan, tanpa nama reviewer lain.
- Produces: `ReviewerSummary::run(User $viewer, SubmissionReviewer $assignment): array{submission: EvaluationSubmissionData, threads: list<ReviewThreadData>, canCreateThread: bool, scores: array{visible: bool, reason: string|null, scores: list<Score>}, outcome: OutcomeConditionsData}` — `threads` = `ReviewThreads::run` dibatasi `reviewer_id` penugasan ini (ulir yang diwarisi ikut, BR-REV-15); `canCreateThread` = `$assignment->hasSettledEvaluation()` (BR-FE-06, satu definisi dengan `ReviewSummaryGuard`); `scores` = `VisibleReviewerScores::run` (BR-REV-11).

- [ ] **Step 1: Tulis test yang gagal.** `[REV-EVAL-04] evaluasi pending menutup pembuatan ringkasan` (`canCreateThread` false); `[REV-EVAL-06] not_required membuka pembuatan ringkasan`; `[REV-DISCUSS-06] ulir reviewer yang digantikan tampil bagi penggantinya`; `[REV-VIS-02] reviewer eksternal tidak menerima skor rekan meski semua selesai` (`scores.visible` false, `scores.scores` []); `[REV-VIS-03] skor rekan tertutup selama ada yang belum selesai`; `[REV-OUTCOME-02] syarat keputusan menghitung reviewer yang belum selesai`; `ulir reviewer lain tidak dikirim`; `[SM-ACCESS-06] reviewer yang digantikan ditolak` (403). `ReviewThreadAuthorPayloadTest:47` pindah ke route ringkasan.
- [ ] **Step 2–4:** jalankan → FAIL, implementasi, `tests/Feature/Review` + `tests/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model ringkasan dan diskusi reviewer`

### Task 7: `Review/SummaryPage` (FE)

**Files:**
- Create: `resources/js/Pages/Review/SummaryPage.vue`, `resources/js/Components/Review/{NewThreadForm,OutcomeConditions}.vue`
- Modify: `resources/js/Components/Submission/Revision/ReviewThreadCard.vue` (prop opsional `canResolve`), `resources/js/types/review.ts`, `resources/js/Components/Review/AssignmentCard.vue` + `EvaluationPage.vue` (tautan ke `reviewer.assignments.summary`)
- Test: `tests/frontend/components/Review/{NewThreadForm,OutcomeConditions}.spec.ts`, `tests/frontend/components/Submission/Revision/ReviewThreadCard.spec.ts` (tambah), `tests/frontend/pages/SummaryPage.spec.ts`

**Interfaces:**
- `ReviewThreadCard` + `canResolve?: boolean`: ulir terbuka + `canResolve` → tombol "Tandai Selesai" (`ConfirmDialog`) → `router.post(route('review-summaries.resolve', thread.id), {}, { preserveScroll: true })`, banner lock peneliti tidak dirender.
- `NewThreadForm` props `{ submissionId: number; canCreate: boolean }`: `canCreate` false → `AppBanner` lock "Selesaikan evaluasi Anda sebelum membuat catatan ringkasan." + tombol nonaktif (`<!-- BR-FE-06 -->`); selain itu `useForm({ summary_notes: '', attachments: File[] })` → `post(route('reviewer.review-threads.store', submissionId), { forceFormData: true, preserveScroll: true, onSuccess: reset })`.
- `OutcomeConditions` props `{ outcome: OutcomeConditions; status: SubmissionStatus }`: "{settled}/{reviewerCount} evaluasi selesai", "{openThreadCount} ulir terbuka", kalimat syarat yang menahan (REV-OUTCOME-02/03/06) dan banner lock "Penolakan tidak tersedia di layar reviewer. Hanya Operator yang dapat menolak, lewat aksi tertulis." (`<!-- BR-REV-07, BR-REV-14 -->`).
- `SummaryPage`: `PageHeader` "Review Summary & Diskusi", back "Penugasan Review"; `NewThreadForm`, daftar `ReviewThreadCard :can-reply="true" :can-resolve="true"`, `ScoreSection :scores`, `OutcomeConditions`, banner info frame "Jika seluruh catatan telah diselesaikan dan semua evaluasi masuk, pengajuan disetujui secara otomatis. Peneliti dapat melihat ringkasan ini setelah statusnya menjadi Perlu Revisi."

- [ ] **Step 1: Vitest gagal:** `[REV-EVAL-04] ringkasan tertutup selama evaluasi pending`, `[REV-DISCUSS-01] membuka ulir lewat review-threads.store`, `[REV-DISCUSS-03] reviewer menandai ulir selesai`, `[REV-OUTCOME-03] ulir terbuka disebut sebagai penahan`, `[REV-OUTCOME-08] tidak ada jalur tolak di layar reviewer`.
- [ ] **Step 2–4:** FAIL → implementasi → `npm run quality` PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman ringkasan dan diskusi reviewer`

### Task 8: Riwayat review (BE + FE)

**Files:**
- Create: `app/Queries/Review/ReviewerHistory.php`, `app/Data/View/Review/ReviewHistoryRowData.php`, `resources/js/Pages/Review/HistoryPage.vue`
- Modify: `routes/web.php`, `ReviewerAssignmentController` (`history`), `resources/js/lib/navigation.ts` ("Riwayat Review" `routeName: 'reviewer.history.index'`), `resources/js/types/review.ts`
- Test: `tests/Feature/Review/ReviewerHistoryPageTest.php`, `tests/frontend/pages/HistoryPage.spec.ts`

**Interfaces:**
- Route `GET /reviewer/history` → `reviewer.history.index` (`can:reviewers.evaluate`), query `search`, `year`.
- `ReviewHistoryRowData(int $id, string $title, ?string $scheme, ?string $context, ?string $completedAt, string $status, string $statusLabel, ?string $summaryUrl)` — satu baris per `SubmissionReviewer` reviewer ini, **semua** status; `context` = judul fase + nama periode; `completedAt` = `submitted_at` terakhir; `status` ∈ `completed|pending|replaced` ("Selesai"/"Belum selesai"/"Digantikan"); `summaryUrl` null untuk `replaced` (BR-REV-10 menutup bacaan form penugasan lama).
- `ReviewerHistory::run(Reviewer $reviewer, ?string $search, ?int $year): LengthAwarePaginator<int, ReviewHistoryRowData>` — `whereLike` judul form, tahun lewat `whereYear('created_at', …)` (PostgreSQL aman), 15 per halaman.
- Halaman: `DataTable` + `PaginationBar` + pencarian & `FilterSelect` tahun lewat `useFilters`.

- [ ] **Step 1: Test gagal:** Pest `[REV-REASSIGN-02] penugasan yang digantikan tetap terdaftar sebagai riwayat`, `[MON-REVIEW-04] siklus monev berbeda tampil sebagai dua baris`, `riwayat reviewer lain tidak terdaftar`, `pencarian judul tidak peka huruf besar`; Vitest `baris digantikan tanpa tautan`, `pencarian memakai useFilters`.
- [ ] **Step 2–4:** FAIL → implementasi → PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman riwayat review`

### Task 9: Tutup S3a-2

- Delete: `resources/js/Pages/Reviewer/Submissions/ShowPage.vue`; baris `quality-legacy.txt`, `max.legacy.files` −1.
- Browser: buka ringkasan → buat ulir (setelah evaluasi selesai) → balas → tandai selesai → status pengajuan berubah; riwayat dengan baris digantikan.
- Gate penuh; §8 S3a-2 `☑`; §9 entri baru; memory; commit `feat(fe): irisan S3a-2 ringkasan dan riwayat reviewer selesai`; sarankan session baru untuk S4.

## Selisih untuk §9 (ditulis di Task 5/9)

- **W20** — FE-WINDOW-02 hanya terikat di `FormPhaseDetail::isWithinDeadline`; simpan/kirim evaluasi lewat tenggat diterima backend (`LockEvaluationForm` tak pernah dipanggil). Penulis rencana S3a: Policy `evaluate` menolak penugasan yang `isOverdue()`; tenggatnya `ReviewerFormAssignment.due_date`. Penugasan yang dimulai sendiri mendapat tanggal terakhir periode (`EvaluationDueDate`), jadi periode tanpa "Batas Evaluasi" (BR-FE-17) menutup evaluasi begitu batas submit lewat — data periode harus mematuhi BR-FE-17.
- **W21** — 03-01 menggambar kartu per pengajuan; legacy mendaftar per form evaluasi. Penulis rencana S3a: satu kartu per `SubmissionReviewer`, tanpa paginasi (beban reviewer kecil); form per pengajuan menjadi tab di 03-03. Field evaluasi bertipe `checkbox` mengikuti Form Engine (satu kotak ya/tidak), bukan pilihan ganda dipisah koma seperti legacy.
- **W22** — 03-02 menggambar kolom "Skor" dan filter "Tipe"; form evaluasi tidak punya konsep skor total, dan tipe konteks (proposal/monev) tidak tersimpan sebagai kolom. Penulis rencana S3a: keduanya tidak digambar; kolom "Konteks" = fase + periode.
- Admin/operator yang membuka `reviewer.evaluation-form.show` dari `ReviewSystem.vue` kini dialihkan ke halaman evaluasi reviewer dan mendapat 403; bacaan lembar evaluasi untuk Operator dibangun di S4 (OPERATOR-03-*).
