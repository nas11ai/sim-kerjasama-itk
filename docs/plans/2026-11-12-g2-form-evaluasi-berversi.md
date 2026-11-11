# G2 — Form Evaluasi Mandiri & Berversi (R3) + Terapkan Versi Baru (R4) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Form Evaluasi menjadi objek pustaka berversi (`/admin/evaluation-forms`, menu **Form Evaluasi**) yang dipasang ke banyak langkah; satu angkatan × langkah × Form Evaluasi dinilai dengan satu versi yang dipaku saat draf penilaian pertama tersimpan; Operator bisa menerapkan versi terbaru ke angkatan berjalan secara sadar.

**Architecture:** K1 — versi = salin baris: `review_form_fields` milik `review_evaluation_form_versions`, sehingga jawaban lama tetap ber-FK ke field yang persis sama. Pemasangan langkah ↔ Form Evaluasi pindah ke tabel `step_evaluation_forms` (wajib/aktif/urutan). Pemakuan K3 di `evaluation_form_pins` (periode × langkah × Form Evaluasi); jawaban reviewer mencatat versi yang diisinya. Edit isi lewat draf K2 (`configuration_drafts`, JSON) lalu Terbitkan = versi baru. Tanpa migrasi data (roadmap: belum produksi; DB dev `migrate:fresh --seed`).

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.1 (menu Form Evaluasi), §4.4, §4.5 (bagian Form Evaluasi), §7 butir 3–5. Peta irisan & keputusan K1–K3: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G2 = R3 + R4). ADR 0019. DDD `ddd/core/02_review.md`, `ddd/generic/01_form_engine.md`, `ddd/02_ubiquitous_language.md`.

**Basis:** `feat/init-be-development` @ `321a0afd` (G1 selesai).

**Ukuran:** tiga session. **G2-a = Task 0–1** (B5 + skema berversi), **G2-b = Task 2** (pustaka + pemasangan), **G2-c = Task 3–4** (terapkan versi + penutup). Kedua gate hijau di akhir tiap session.

## Global Constraints

- **Angkatan** = (`submission_period_id`, `form_phase_id`); pin disimpan per (`submission_period_id`, `form_phase_detail_id`, `review_evaluation_form_id`) — langkah sudah menyiratkan fase. Laporan monev memakai periode induknya (ADR 0019), jadi lembar Monev I dipaku terpisah dari lembar proposal.
- **"Mulai mengisi"** = `OpenEvaluationResponse` membuat `ReviewFormResponse` pertama (dipanggil simpan draf & submit; membuka layar saja tidak memaku).
- Versi yang terbit **tidak pernah diubah**: `ReviewEvaluationFormVersion`, `ReviewFormField`, `ReviewFormFieldOption` melempar `LogicException` di event `updating`. Hapus hanya lewat cascade basis data saat Form Evaluasi tak terpakai dihapus.
- `title` dan `description` Form Evaluasi = nama pustaka, disunting langsung (tidak berversi, tidak lewat draf). Isi berversi = field, opsi, wajib, aturan validasi.
- Label versi persis `"Versi {n}"` (`ReviewEvaluationFormVersion::label()`), `n` mulai 1, naik 1 per terbit.
- Teks galat persis (kunci dalam kurung):
    - (`fields`) `"Form evaluasi butuh minimal satu field."`
    - (`review_evaluation_form_id`) `"Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang."`
    - (`review_evaluation_form_id`) `"Form evaluasi ini sudah terpasang di langkah ini."`
    - (`step_evaluation_form`) `"Form evaluasi ini sudah ditugaskan ke {n} reviewer di langkah ini, jadi tidak bisa dilepas. Nonaktifkan saja."`
    - (`draft`) `"Tidak ada draf untuk diterbitkan."`
    - (`pin`) `"Angkatan ini sudah memakai versi terbaru."`
    - (`pin`) `"Tidak bisa diterapkan: {n} pengajuan di langkah ini sudah diputuskan."`
- Teks UI persis: menu `"Form Evaluasi"`; judul pustaka `"Form Evaluasi"`, subjudul `"Lembar penilaian reviewer yang bisa dipasang di banyak tahap."`; lencana `"Belum terbit"`; banner `"Draf belum diterbitkan. Reviewer masih memakai {Versi n}."` (tanpa versi: `"Draf belum diterbitkan. Belum ada versi yang dipakai reviewer."`); konfirmasi terbit `"Terbitkan {Versi n}? Berlaku untuk langkah yang belum mulai dinilai."` + bila m > 0 `" {m} angkatan sudah memakai versi lama dan tetap memakainya."`; riwayat jawaban `"Riwayat · {Versi n}"`.
- Route pustaka & pemasangan: grup `['auth', 'can:schemes.manage']`, prefix `admin`, nama `admin.`. Versi per angkatan & aksi terapkan: `can:periods.manage` (Operator memegangnya; `schemes.manage` hanya Admin).
- "Diputuskan" (R4): langkah Pengajuan — status ∈ {Approved, Rejected, NeedsRevision, Resubmitted}; langkah monev (`deadline_cycle` tak null) — laporan disubmit yang semua reviewer aktifnya `settled` dan tanpa ReviewSummary `open` (padanan SQL `FormSubmission::canProceed()`).
- BR baru + skenarionya + test-nya mendarat dalam satu commit (BddCoverageTest assertion 3, 4, 7; `pending.txt`/`uncovered.txt` tetap `max: 0`).
- Tag frame: pemakuan `@REVIEWER-03-03`; pustaka/pemasangan `@ADMIN-08-09`; terapkan versi `@OPERATOR-09-01`. Katalog frame tidak ditambah (pola R2).
- Gate via container (AGENTS.md); `free -m` ≥ 3 GB atau watchdog, satu langkah berat sekaligus.

## Review Focus

1. **Reviewer kedua yang ditugaskan sesudah Versi 2 terbit**, padahal reviewer pertama sudah menyimpan draf di Versi 1 pada angkatan × langkah yang sama → layar dan jawabannya Versi 1. Test di Task 1 (`[REV-VER-02]`).
2. **Fase yang sama di dua periode**: angkatan 2025 terpaku Versi 1, angkatan 2026 belum mulai saat Versi 2 terbit → 2026 memakai Versi 2, 2025 tetap Versi 1. Test di Task 1 (`[REV-VER-04]`).
3. **Dua simpan draf pertama berurutan cepat** (dua reviewer) → tepat satu baris pin; `PinEvaluationVersion` dipanggil dua kali mengembalikan versi yang sama. Pin dibuat `createOrFirst` (savepoint) di atas indeks unik. Test di Task 1.
4. **Terapkan versi di angkatan yang satu pengajuannya NEEDS_REVISION** → ditolak dengan jumlahnya; pin, jawaban, `evaluation_status`, dan putaran tidak berubah, tanpa notifikasi. Test di Task 3 (`[REV-VER-07]`).
5. **Terapkan versi sesudah putaran selesai `ready`** (menunggu Setujui Operator) → putaran dibuka lagi, `DecisionReadiness` tidak siap, `ApproveSubmission` ditolak sampai reviewer submit ulang dan menekan Selesai menilai. Test di Task 3.

## Kesesuaian dengan BDD

| ID | Berkas | Kelas | Isi | Task |
| --- | --- | --- | --- | --- |
| `[REV-VER-01]` | `bdd/core/review/evaluation_version.feature` | `@happy` | Sebelum penilaian pertama, reviewer melihat versi terbit terbaru | 1 |
| `[REV-VER-02]` | 〃 | `@boundary` | Draf pertama memaku versi; reviewer yang ditugaskan belakangan memakai versi yang sama walau versi baru terbit | 1 |
| `[REV-VER-03]` | 〃 | `@boundary` | Pemakuan per langkah: lembar Monev I angkatan yang proposalnya sudah terpaku tetap memakai versi terbaru sampai penilaian Monev I dimulai | 1 |
| `[REV-VER-04]` | 〃 | `@boundary` | Angkatan lain pada fase yang sama memakai versi terbaru | 1 |
| `[REV-VER-05]` | 〃 | `@happy` | Menerbitkan versi baru tidak mengubah field versi lama; jawaban lama terbaca dengan label lamanya | 1 |
| `[FE-EVLIB-01]` | `bdd/generic/form_engine/evaluation_library.feature` | `@denial` | Form Evaluasi yang belum pernah terbit tidak bisa dipasang | 2 |
| `[FE-EVLIB-02]` | 〃 | `@happy` | Satu Form Evaluasi dipasang di dua tahap | 2 |
| `[FE-EVLIB-03]` | 〃 | `@boundary` | Draf tidak berlaku sebelum terbit: reviewer tetap melihat versi terbit | 2 |
| `[FE-EVLIB-04]` | 〃 | `@happy` | Terbitkan membentuk versi berikutnya dan menghapus draf | 2 |
| `[FE-EVLIB-05]` | 〃 | `@denial` | Form Evaluasi yang ditugaskan di sebuah langkah tidak bisa dilepas dari langkah itu | 2 |
| `[REV-VER-06]` | `evaluation_version.feature` | `@happy` | Terapkan versi baru membuka ulang penilaian yang sudah disubmit; jawaban lama tetap terbaca sebagai riwayat | 3 |
| `[REV-VER-07]` | 〃 | `@denial` | Terapkan ditolak bila ada pengajuan di langkah itu yang sudah diputuskan, menyebut jumlahnya | 3 |
| `[REV-VER-08]` | 〃 | `@happy` | Reviewer yang penilaiannya dibuka ulang diberi tahu | 3 |
| `[REV-VER-09]` | 〃 | `@boundary` | Terapkan di satu angkatan tidak menyentuh angkatan lain | 3 |

## Keputusan penulis rencana

- **G2-W1** — Penugasan (`reviewer_form_assignments`) tetap menunjuk Form Evaluasi (identitas, unik per reviewer); **jawaban** yang menunjuk versi. Terapkan versi (R4) cukup menandai jawaban lama `superseded_at`, tanpa memindahkan penugasan.
- **G2-W2** — `is_active` dan `is_required` pindah ke pemasangan (`step_evaluation_forms`): dulu Form = langkah, jadi "nonaktifkan" bermakna per langkah. Form pustaka tidak punya `is_active`.
- **G2-W3** — Hanya Form Evaluasi yang sudah terbit bisa dipasang (BR-FE-29). Menghilangkan kasus "langkah memakai Form Evaluasi tanpa versi" di semua pembaca, dan butir cek kesiapan §4.9 untuk Form Evaluasi menjadi tak perlu.
- **G2-W4** — Task 1 memakai tiga Action komposit sementara (`CreateReviewEvaluationForm`, `ReviseReviewEvaluationForm`, `DeleteReviewEvaluationForm`) supaya kartu di editor fase tetap jalan di antara session; Task 2 menghapusnya bersama kartunya. Simpan kartu dengan field = terbit versi baru seketika (draf baru ada di Task 2).
- **G2-W5** — Aksi terapkan ada di halaman baru **Versi Form Evaluasi** per periode (dibuka dari baris tabel Periode), bukan di pustaka: angkatan adalah milik periode, dan Operator memegang `periods.manage`, bukan `schemes.manage`.
- **G2-W6** — Ekspor: tidak ada ekspor yang memuat skor hari ini (`app/Support/Reporting/Exports/*`), jadi tidak ada label versi yang perlu ditambahkan di ekspor. Dicatat di ADR 0019.
- **G2-W7** — `configuration_drafts.draftable_type` memakai konstanta string (`ConfigurationDraft::EVALUATION_FORM = 'review_evaluation_form'`), bukan `MorphTo`: repo tidak punya morph map, dan R6 cukup menambah konstanta.

---

### Task 0: B5 — tapis `?form=` bertahan sesudah aksi pustaka fase

**Files:**

- Modify: `app/Http/Controllers/FormEngine/FormPhaseController.php` (`update`, `destroy`, `edit`), `resources/js/Pages/FormEngine/PhaseEditPage.vue`, `resources/js/Components/FormEngine/PhaseEditor.vue`, `resources/js/types/form-engine.ts`
- Modify: `docs/plans/2026-11-11-fase-reuse-catatan-bug.md` (B5 → Selesai)
- Test: `tests/Feature/FormEngine/PhaseLibraryTest.php`, `PhaseEditTest.php`, `tests/frontend/pages/PhaseEditPage.spec.ts`

**Interfaces:**

- Produces: `edit` dan `create` menerima kueri opsional `form` (id Form, tak dikenal → 404, pola `requestedForm`) dan mengirim prop `returnForm: number | null`; `update` membaca `form` dari body dan redirect ke `admin.phases.index` dengan `['form' => id]` bila ada; `destroy` → `back()`. `PhaseCard` menaut Sunting dengan `{ form }` bila pustaka bertapis.

- [ ] **Step 1: Tulis test** — `it('returns to the filtered library after update')` (PUT dengan `form` → redirect `admin.phases.index?form={id}`), `it('stays on the filtered library after destroy')` (DELETE dari `Referer` bertapis → kembali ke URL itu), Vitest: Kembali/Batalkan memakai `route('admin.phases.index', { form: returnForm })` bila `returnForm` tak null.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** berkas test di atas → PASS.
- [ ] **Step 5: Commit** — `fix(form-engine): tapis Form pustaka fase bertahan sesudah sunting dan hapus`

### Task 1: Skema berversi + pemakuan per angkatan (BE)

**Files:**

- Create: `database/migrations/2026_11_12_500000_version_review_evaluation_forms.php`
- Create: `app/Models/ReviewEvaluationFormVersion.php`, `app/Models/StepEvaluationForm.php`, `app/Models/EvaluationFormPin.php`
- Modify: `app/Models/ReviewEvaluationForm.php`, `ReviewFormField.php`, `ReviewFormFieldOption.php`, `ReviewFormResponse.php`, `ReviewerFormAssignment.php`, `FormPhaseDetail.php`
- Create: `app/Queries/Review/EvaluationVersionFor.php`, `app/Actions/Review/PinEvaluationVersion.php`, `app/Actions/Review/CreateEvaluationForm.php`, `PublishEvaluationFormVersion.php`, `AttachEvaluationForm.php`, `UpdateStepEvaluationForm.php`, `DetachEvaluationForm.php`, `app/Data/Request/EvaluationFormDetailsInput.php`
- Modify (komposit sementara, G2-W4): `app/Actions/Review/CreateReviewEvaluationForm.php`, `ReviseReviewEvaluationForm.php`, `DeleteReviewEvaluationForm.php`
- Modify (pembaca): `OpenEvaluationResponse`, `StartEvaluationForm`, `AssignReviewers`, `AssertEvaluatedDetailsStayReviewed`, `ExtendSubmissionDates::followEvaluation`, `app/Services/Review/EvaluationAnswerWriter.php`, `app/Queries/Review/{ReviewerEvaluation,EvaluationRequirements,EvaluationFormsOverview,EvaluationAnswerErrors,EvaluationFormCompletion,RequiredFormsCompletion}.php`, `app/Queries/Submission/OperatorSubmissionDetail.php`, `app/Queries/Form/{PhaseEdit,PhaseEvaluationForms}.php`, `app/Data/View/FormEngine/{EvaluationSectionData,EvaluationFormEditData}.php`, `database/seeders/EndToEndFlowSeeder.php`
- Delete: `app/Services/Form/ReviewFormFieldWriter.php` (penjaga field terjawab menjadi tak perlu: versi lama tidak pernah disentuh)
- Modify: `docs/requirement-analysis/ddd/core/02_review.md` (BR-REV-18), `ddd/02_ubiquitous_language.md` (baris **ReviewEvaluationForm** → "Form Evaluasi: pustaka berversi, dipasang ke langkah `needs_review` lewat StepEvaluationForm"; istilah baru **Versi Form Evaluasi**, **Pemakuan versi**)
- Create: `docs/requirement-analysis/bdd/core/review/evaluation_version.feature` (REV-VER-01..05)
- Modify: `tests/Support/fixtures.php` + ±34 berkas test yang membuat Form Evaluasi
- Test: `tests/Feature/Review/EvaluationVersionPinTest.php` (baru)

**Interfaces:**

- Skema (migrasi skema saja, `down()` membalik):
    - `review_evaluation_forms`: hapus `form_phase_detail_id`, `is_required`, `order`, `is_active` (+ indeksnya). Sisa: `title`, `description`, timestamps.
    - `review_evaluation_form_versions`: `review_evaluation_form_id` (cascade), `number` unsignedSmallInteger, `published_by` FK users nullable `nullOnDelete`, `published_at`, timestamps; unik (`review_evaluation_form_id`, `number`).
    - `review_form_fields`: `review_evaluation_form_id` → `review_evaluation_form_version_id` (cascade); indeks (`review_evaluation_form_version_id`, `order`).
    - `step_evaluation_forms`: `form_phase_detail_id` (cascade), `review_evaluation_form_id` (restrict), `is_required` bool default true, `is_active` bool default true, `order` unsignedSmallInteger, timestamps; unik (`form_phase_detail_id`, `review_evaluation_form_id`).
    - `evaluation_form_pins`: `submission_period_id`, `form_phase_detail_id`, `review_evaluation_form_id`, `review_evaluation_form_version_id` (semua restrict), `pinned_at`, timestamps; unik (`submission_period_id`, `form_phase_detail_id`, `review_evaluation_form_id`) bernama `evaluation_form_pins_cohort_unique`.
    - `review_form_responses`: + `review_evaluation_form_version_id` not null (restrict).
- Model:
    - `ReviewEvaluationForm`: `versions(): HasMany` (urut `number`), `latestVersion(): HasOne` (`latestOfMany('number')`), `stepEvaluationForms(): HasMany`; penjaga `deleting` (BR-FE-22) menghitung `penugasan reviewer` dan `pemasangan di langkah`.
    - `ReviewEvaluationFormVersion`: `reviewEvaluationForm()`, `reviewFormFields()` (urut `order`), `label(): string`.
    - `StepEvaluationForm`: `formPhaseDetail()`, `reviewEvaluationForm()`, scope `active`, `required`, `ordered`.
    - `EvaluationFormPin`: `submissionPeriod()`, `formPhaseDetail()`, `reviewEvaluationForm()`, `reviewEvaluationFormVersion()`.
    - `FormPhaseDetail`: `reviewEvaluationForms|activeReviewEvaluationForms|requiredReviewEvaluationForms` diganti `stepEvaluationForms()`, `activeStepEvaluationForms()` (aktif, urut `order`), `requiredStepEvaluationForms()`; `hasReviewEvaluationForms()` tetap, membaca pemasangan aktif.
    - `ReviewFormResponse::reviewEvaluationFormVersion()`; `hasRequiredFieldsCompleted()` membaca field versi jawaban.
    - `ReviewerFormAssignment::isClosedByDeadline()` membaca langkah dari `submissionReviewer->formSubmission->formPhaseDetail` (bukan dari Form Evaluasi).
- Kueri & Action:
    - `EvaluationVersionFor::run(FormSubmission $submission, ReviewEvaluationForm $form): ReviewEvaluationFormVersion` — versi pin (periode, langkah pengajuan, form) atau `latestVersion`; `firstOrFail` (G2-W3 menjamin ada).
    - `PinEvaluationVersion::run(FormSubmission $submission, ReviewEvaluationForm $form): ReviewEvaluationFormVersion` — `createOrFirst` pin dengan versi terbaru; audit `"Versi form evaluasi dipaku"` hanya saat baris baru lahir. `// BR-REV-18`.
    - `OpenEvaluationResponse` membuat jawaban dengan `review_evaluation_form_version_id` = `PinEvaluationVersion`.
    - `CreateEvaluationForm::run(EvaluationFormDetailsInput $input, User $actor): ReviewEvaluationForm`; `EvaluationFormDetailsInput(title, description)`.
    - `PublishEvaluationFormVersion::run(ReviewEvaluationForm $form, list<ReviewFormFieldInput> $fields, User $actor): ReviewEvaluationFormVersion` — menyalin baris field + opsi baru (id masukan diabaikan), `number` = maks + 1; audit `"Form evaluasi diterbitkan"` berisi nomor + label field.
    - `AttachEvaluationForm::run(FormPhaseDetail $detail, ReviewEvaluationForm $form, bool $isRequired, User $actor): StepEvaluationForm` — `AssertReviewedPhaseDetail`, sudah terbit, belum terpasang; `order` terakhir + 1.
    - `UpdateStepEvaluationForm::run(StepEvaluationForm $step, bool $isRequired, bool $isActive, User $actor): void`.
    - `DetachEvaluationForm::run(StepEvaluationForm $step, User $actor): void` — tolak bila ada `ReviewerFormAssignment` atas form itu pada pengajuan di langkah itu.
    - `StartEvaluationForm`, `AssignReviewers`, `EvaluationRequirements`, `EvaluationFormsOverview`, `ReviewerEvaluation` membaca pemasangan aktif langkah pengajuan; `is_required` dari pemasangan. `ReviewerEvaluation` mengambil field dari versi jawaban, atau `EvaluationVersionFor` bila belum ada jawaban (membaca saja). `EvaluationAnswerWriter::assertOwnFields` dan `EvaluationAnswerErrors` membaca field versi jawaban.
    - Fixture: `evaluationFormOn(FormPhaseDetail $detail, list<array{type: string, label: string, required?: bool}> $fields = [['type' => 'Text', 'label' => 'Catatan']], bool $required = true): ReviewEvaluationForm` — form + Versi 1 + pemasangan, tanpa audit; `publishVersion(ReviewEvaluationForm $form, list<...> $fields): ReviewEvaluationFormVersion`.

- [ ] **Step 1: Tulis skenario** REV-VER-01..05 dan **BR-REV-18** di `02_review.md` (sesudah BR-REV-17): "Versi Form Evaluasi dipaku per angkatan × langkah × Form Evaluasi saat draf penilaian pertama di sana tersimpan. Sebelumnya berlaku versi terbit terbaru; sesudahnya semua reviewer langkah itu di angkatan itu — termasuk yang ditugaskan belakangan — memakai versi pakuan. Versi yang terbit tidak berubah." Contoh:
    ```gherkin
    @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
    Scenario: [REV-VER-02] Reviewer yang ditugaskan belakangan memakai versi pakuan
      Given "Sari" menyimpan draf penilaian "P-001" pada "Lembar Proposal" Versi 1
      And Admin menerbitkan "Lembar Proposal" Versi 2
      When "Dodi" ditugaskan ke "P-002" di angkatan yang sama dan membuka lembarnya
      Then "Dodi" mengisi "Lembar Proposal" Versi 1
    ```
- [ ] **Step 2: Tulis test** `EvaluationVersionPinTest.php` — satu `it('[REV-VER-0n] …')` per skenario, ditambah: `it('pins once when two reviewers save their first draft')` (dua `PinEvaluationVersion` → satu baris, versi sama); `it('refuses to update a published version field')` (`expect(fn () => $field->update([...]))->toThrow(LogicException::class)`); `it('rejects answers for fields of another version')` (jawaban Versi 1 berisi id field Versi 2 → kunci `responses`). REV-VER-05: jawaban Versi 1 dibaca `SubmittedEvaluationSheets` dengan label field Versi 1 sesudah Versi 2 mengganti labelnya.
- [ ] **Step 3: Jalankan** `./vendor/bin/pest tests/Feature/Review/EvaluationVersionPinTest.php` → FAIL (tabel/kelas belum ada).
- [ ] **Step 4: Implementasi** migrasi, model, kueri, Action sesuai Interfaces; komposit sementara memanggil Action baru (Create = Create + Publish + Attach; Revise = ubah judul + Publish bila `fields !== null` + `UpdateStepEvaluationForm`; Delete = Detach lalu hapus form bila tak terpasang lagi). Seeder memakai jalur yang sama.
- [ ] **Step 5: Jalankan** test baru → PASS.
- [ ] **Step 6: Pindahkan fixture & test lama** ke `evaluationFormOn`. `grep -rn "activeReviewEvaluationForms\|requiredReviewEvaluationForms\|reviewEvaluationForms()\|review_evaluation_forms.form_phase_detail_id\|ReviewFormFieldWriter" app database tests` → kosong. Test yang khusus membuktikan penjaga `ReviewFormFieldWriter` (hapus/ganti tipe field terjawab) diganti satu test REV-VER-05 — perilakunya kini dijamin versi beku.
- [ ] **Step 7: Gate G2-a** — `composer run quality` lalu `npm run quality` (berurutan, watchdog; FE tak berubah, tetapi tipe props kartu ikut diperiksa). Hijau.
- [ ] **Step 8: Commit** — `feat(review): form evaluasi berversi, dipaku per angkatan dan langkah`

### Task 2: Pustaka Form Evaluasi + draf/terbit + pemasangan di editor fase

**Files:**

- Create: `database/migrations/2026_11_13_100000_create_configuration_drafts_table.php`, `app/Models/ConfigurationDraft.php`
- Create: `app/Actions/Review/{SaveEvaluationFormDraft,PublishEvaluationFormDraft,DiscardEvaluationFormDraft,UpdateEvaluationForm,DeleteEvaluationForm}.php`, `app/Data/Request/EvaluationDraftInput.php`
- Create: `app/Queries/Review/{EvaluationFormLibrary,EvaluationFormEdit,EvaluationPublishImpact}.php`, `app/Data/View/FormEngine/{EvaluationFormCardData,EvaluationFormEditPageData,StepEvaluationFormData}.php`
- Rewrite: `app/Http/Controllers/FormEngine/EvaluationFormController.php` (pustaka); Create: `EvaluationFormDraftController.php`, `StepEvaluationFormController.php`, FormRequest pasangannya
- Delete: komposit `CreateReviewEvaluationForm`, `ReviseReviewEvaluationForm`, `DeleteReviewEvaluationForm`, `app/Data/Request/ReviewEvaluationFormInput.php`, `app/Http/Requests/Review/ReviewEvaluationFormRequest.php`, `resources/js/Components/FormEngine/EvaluationFormCard.vue`, `EvaluationFormLifecycle.vue`
- Modify: `routes/web.php`, `app/Queries/Form/PhaseEvaluationForms.php`, `app/Data/View/FormEngine/EvaluationSectionData.php`
- Create: `resources/js/Pages/FormEngine/EvaluationFormIndexPage.vue`, `EvaluationFormEditPage.vue`, `resources/js/lib/evaluationPreview.ts`
- Modify: `resources/js/Components/FormEngine/EvaluationSection.vue`, `EvaluationFieldDialog.vue` (dipakai editor pustaka), `PhaseEditor.vue`, `resources/js/lib/navigation.ts`, `resources/js/types/form-engine.ts`
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-29, BR-FE-30; BR-FE-22 menyebut pemasangan), `ddd/02_ubiquitous_language.md` (**Draf**, **Terbitkan**), `docs/requirement-analysis/ux/ia/08_administration/form_builder.md` (halaman #6 "Form Evaluasi — pustaka" `/admin/evaluation-forms`, #7 "Edit Form Evaluasi" `/admin/evaluation-forms/{id}/edit`; #5 "Edit Phase — Details & Review Forms" kini memasang, bukan menyusun)
- Create: `docs/requirement-analysis/bdd/generic/form_engine/evaluation_library.feature` (FE-EVLIB-01..05)
- Test: `tests/Feature/FormEngine/EvaluationLibraryTest.php` (baru), `PhaseEvaluationTest.php`, `tests/Feature/Review/EvaluationFormCrudTest.php`, `EvaluationFormLifecycleTest.php`; `tests/frontend/pages/EvaluationFormIndexPage.spec.ts`, `EvaluationFormEditPage.spec.ts` (baru), `tests/frontend/components/FormEngine/EvaluationSection.spec.ts`, `tests/frontend/lib/navigation.spec.ts`

**Interfaces:**

- Consumes: Task 1 (`CreateEvaluationForm`, `PublishEvaluationFormVersion`, `AttachEvaluationForm`, `UpdateStepEvaluationForm`, `DetachEvaluationForm`, `EvaluationVersionFor`).
- Produces:
    - `configuration_drafts`: `draftable_type` string(64), `draftable_id` unsignedBigInteger, `payload` jsonb, `updated_by` FK users nullable `nullOnDelete`, timestamps; unik (`draftable_type`, `draftable_id`). `ConfigurationDraft::EVALUATION_FORM` (G2-W7). `ReviewEvaluationForm::draft(): HasOne`.
    - `EvaluationDraftInput(list<ReviewFormFieldInput> $fields)` dengan `toPayload(): array` / `fromPayload(array): self`.
    - `SaveEvaluationFormDraft::run(ReviewEvaluationForm, EvaluationDraftInput, User): ConfigurationDraft` (upsert); `PublishEvaluationFormDraft::run(ReviewEvaluationForm, User): ReviewEvaluationFormVersion` (Publish + hapus draf, satu transaksi; `// BR-FE-30`); `DiscardEvaluationFormDraft::run(ReviewEvaluationForm, User): void`; `UpdateEvaluationForm::run(ReviewEvaluationForm, EvaluationFormDetailsInput, User): void`; `DeleteEvaluationForm::run(ReviewEvaluationForm, User): void` (penjaga model + draf ikut terhapus).
    - `EvaluationFormLibrary::run(): list<EvaluationFormCardData>` — `{ id, title, latestVersionLabel: ?string, hasDraft, stepCount, assignmentCount }`, urut `title`, `id`, tanpa kueri per kartu.
    - `EvaluationFormEdit::run(ReviewEvaluationForm): EvaluationFormEditPageData` — `{ id, title, description, fields (draf ?? versi terbaru ?? []), hasDraft, latestVersionLabel, versions: list<{label, publishedAt, publishedBy}>, usedBy: list<{phaseId, phaseTitle, stepLabel}>, impact: {nextLabel, pinnedCohorts} }`.
    - `EvaluationPublishImpact::run(ReviewEvaluationForm): array{nextLabel: string, pinnedCohorts: int}`.
    - Route: `admin.evaluation-forms.index` GET `/admin/evaluation-forms`; `.create` GET `/create`; `.store` POST → `.edit` + `"Form evaluasi dibuat. Susun field-nya lalu terbitkan."`; `.edit` GET `/{reviewEvaluationForm}/edit`; `.update` PUT (judul/deskripsi) `"Form evaluasi diperbarui."`; `.destroy` DELETE → `.index` `"Form evaluasi dihapus."`; `.draft.update` PUT `/{reviewEvaluationForm}/draft` `"Draf disimpan."`; `.draft.destroy` DELETE `"Draf dibuang."`; `.publish` POST `/{reviewEvaluationForm}/publish` `"{Versi n} diterbitkan."`. Pemasangan: `admin.phase-details.evaluation-forms.store` POST `/admin/phase-details/{formPhaseDetail}/evaluation-forms` (`review_evaluation_form_id`, `is_required`) `"Form evaluasi dipasang."`; `admin.step-evaluation-forms.update` PUT `/admin/step-evaluation-forms/{stepEvaluationForm}` (`is_required`, `is_active`) `"Pemasangan diperbarui."`; `.destroy` DELETE `"Form evaluasi dilepas."`. Pemasangan → `back()`.
    - Props `FormEngine/EvaluationFormIndexPage`: `{ forms: EvaluationFormCardData[] }`; `FormEngine/EvaluationFormEditPage`: `{ form: EvaluationFormEditPageData, fieldTypeOptions }`. Tab editor: **Field** (EvaluationFieldDialog; Simpan draf), **Pratinjau** (`EvaluationFormPanel` dari `Components/Review`, `canEdit: false`, dari `toPreviewForm(title, description, fields): EvaluationFormData`), **Versi & pemakaian**.
    - `EvaluationSectionData`: `{ detailId, detailLabel, attached: StepEvaluationFormData[], reviewsInProgress }`; `StepEvaluationFormData { id, formId, title, versionLabel, isRequired, isActive, assignmentCount }`. Props `PhaseEditPage` + `evaluationOptions: Option[]` (Form Evaluasi terbit, urut judul); tiap bagian menyaring yang sudah terpasang dan menautkan "Kelola di Form Evaluasi".
    - Menu: `{ label: 'Form Evaluasi', icon: ClipboardCheck, routeName: 'admin.evaluation-forms.index', activePattern: 'admin.evaluation-forms.*', anyPermission: ['schemes.manage'] }` di antara "Form Builder" dan "Fase".

- [ ] **Step 1: Tulis skenario** FE-EVLIB-01..05 + **BR-FE-29** ("Form Evaluasi adalah pustaka. Ia dipasang ke langkah `needs_review` lewat StepEvaluationForm — wajib/aktif/urutan per pemasangan — sekali per langkah, dan hanya bila sudah pernah terbit. Pemasangan yang sudah ditugaskan ke reviewer tidak bisa dilepas, hanya dinonaktifkan.") dan **BR-FE-30** ("Isi Form Evaluasi disunting sebagai draf. Draf tidak berlaku bagi reviewer; Terbitkan membentuk versi bernomor berikutnya dan menghapus draf.").
- [ ] **Step 2: Tulis test BE** `EvaluationLibraryTest.php` — FE-EVLIB-01..05 (pesan persis Global Constraints), ditambah `it('lists library forms with their latest version and draft flag')`, `it('counts cohorts that keep an older version in the publish impact')`, `it('refuses to delete a form still attached to a step')`, `it('hides the library from users without schemes.manage')` (403). Pindahkan `PhaseEvaluationTest`/`EvaluationFormCrudTest`/`EvaluationFormLifecycleTest` ke route pemasangan; hapus test kartu yang menyusun field dari editor fase.
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi BE.** `grep -rn "ReviewEvaluationFormInput\|ReviseReviewEvaluationForm\|CreateReviewEvaluationForm\|DeleteReviewEvaluationForm" app routes tests` → kosong.
- [ ] **Step 5: Pest berkas di atas** → PASS.
- [ ] **Step 6: Tulis test Vitest** — pustaka: kartu "Belum terbit" tanpa versi, banner draf berteks persis, EmptyState; editor: Simpan draf → `route('admin.evaluation-forms.draft.update', id)`, konfirmasi terbit menampilkan kalimat dampak (m = 0 tanpa kalimat kedua, m = 2 dengan), Pratinjau merender field draf read-only; `EvaluationSection`: daftar pasangan dengan label versi, pilihan hanya Form yang belum terpasang, Lepas → `route('admin.step-evaluation-forms.destroy', id)`; navigasi: item "Form Evaluasi" sebelum "Fase".
- [ ] **Step 7: Implementasi FE** (sistem desain FE v2, `docs/design/fe-v2-design.md`); Vitest berkas di atas → PASS.
- [ ] **Step 8: Gate G2-b** — `composer run quality` lalu `npm run quality`. Hijau.
- [ ] **Step 9: Commit** — `feat(form-engine): pustaka form evaluasi dengan draf dan terbit`

### Task 3: Terapkan versi baru ke angkatan + label versi di layar

**Files:**

- Create: `database/migrations/2026_11_13_200000_add_superseded_at_to_review_form_responses.php`, `2026_11_13_300000_declare_evaluation_version_notification_channels.php` (pola `2026_11_11_000000_declare_review_decision_notification_channels.php`)
- Create: `app/Actions/Review/ApplyLatestEvaluationVersion.php`, `app/Actions/Review/ReopenRoundSeat.php`, `app/Queries/Review/DecidedSubmissionsAtStep.php`, `app/Queries/Period/CohortEvaluationVersions.php`, `app/Data/View/FormEngine/CohortEvaluationVersionData.php`, `app/Notifications/EvaluationVersionAppliedNotification.php`
- Create: `app/Http/Controllers/FormEngine/CohortEvaluationVersionController.php`, `resources/js/Pages/FormEngine/PeriodEvaluationVersionsPage.vue`
- Modify: `app/Models/ReviewerFormAssignment.php`, `ReviewFormResponse.php`, `app/Queries/Review/SubmittedEvaluationSheets.php`, `ReviewerEvaluation.php`, `app/Enums/NotificationCategory.php`, `app/Queries/Notification/NotificationDestination.php`, `routes/web.php`, `resources/js/Components/FormEngine/PeriodTable.vue`, `resources/js/Components/Submission/ScoreSection.vue`, `resources/js/Components/Review/EvaluationFormPanel.vue`, `resources/js/types/*`
- Modify: `ddd/core/02_review.md` (BR-REV-19; BR-REV-08 + "kecuali [BR-REV-19]"), `evaluation_version.feature` (REV-VER-06..09)
- Test: `tests/Feature/Review/ApplyEvaluationVersionTest.php` (baru), `ScoreVisibilityTest.php`, `ReviewerEvaluationPageTest.php`; `tests/frontend/pages/PeriodEvaluationVersionsPage.spec.ts` (baru), `tests/frontend/components/Submission/ScoreSection.spec.ts`

**Interfaces:**

- Consumes: Task 1 (`EvaluationFormPin`, `EvaluationVersionFor`), `RecalculateEvaluationStatus::run(SubmissionReviewer)`.
- Produces:
    - `review_form_responses.superseded_at` timestamp nullable. `ReviewerFormAssignment::reviewFormResponse()` hanya yang `superseded_at IS NULL`; `supersededResponses(): HasMany`.
    - `DecidedSubmissionsAtStep::run(SubmissionPeriod $period, FormPhaseDetail $detail): int` — definisi "diputuskan" di Global Constraints, satu kueri.
    - `ReopenRoundSeat::run(SubmissionReviewer $seat): void` — kursi seat itu di putaran terakhir pengajuannya: `finished_at` null; putaran yang selesai dengan `outcome = 'ready'` dibuka lagi (`completed_at`, `outcome` null). Tidak ada `transitionTo` (status tetap UNDER_REVIEW). Baca `FinishReviewRound`/`ConcludeReviewRound` untuk nama relasinya.
    - `ApplyLatestEvaluationVersion::run(EvaluationFormPin $pin, User $actor): int` — `// BR-REV-19`; tolak (dua pesan `pin`) lalu, dalam satu transaksi: pin → versi terbaru; jawaban berlaku atas form itu pada pengajuan (periode, langkah) pin → `superseded_at = now()`; per SubmissionReviewer tersentuh `RecalculateEvaluationStatus` + `ReopenRoundSeat`; audit `"Versi baru form evaluasi diterapkan"` (versi lama/baru + jumlah); notifikasi sesudah commit. Mengembalikan jumlah jawaban yang dibuka ulang.
    - `EvaluationVersionAppliedNotification(FormSubmission $submission, ReviewEvaluationForm $form, ReviewEvaluationFormVersion $version)` — `EVENT_TYPE = 'EvaluationVersionApplied'`, `type: 'evaluation_version_applied'` (masuk `SUBMISSION_TYPES` dan kategori review), subjek `"Penilaian perlu diisi ulang — SIMPAS"`, pesan `"Lembar \"{judul}\" untuk pengajuan #{id} diganti ke {Versi n}. Isi ulang penilaian Anda; jawaban lama tetap tersimpan sebagai riwayat."`. Satu per SubmissionReviewer tersentuh.
    - `CohortEvaluationVersions::run(SubmissionPeriod $period): list<CohortEvaluationVersionData>` — per fase tertaut → langkah `needs_review` → pemasangan aktif: `{ phaseTitle, stepLabel, formTitle, pinId: ?int, pinnedLabel: ?string, latestLabel, responseCount, decidedCount, canApply }`; `canApply` = pin ada ∧ pin < terbaru ∧ `decidedCount === 0`. Belum terpaku → `pinnedLabel` null, layar menulis `"Belum mulai dinilai — memakai {latestLabel}"`.
    - Route (`can:periods.manage`): `admin.submission-periods.evaluation-versions` GET `/admin/submission-periods/{submissionPeriod}/evaluation-versions` → `FormEngine/PeriodEvaluationVersionsPage` `{ period: {id, name}, rows: CohortEvaluationVersionData[] }`; `admin.evaluation-form-pins.apply` POST `/admin/evaluation-form-pins/{evaluationFormPin}/apply` → `back()` + `"{Versi n} diterapkan; {k} penilaian dibuka ulang."`. `PeriodTable` mendapat aksi baris `"Versi Form Evaluasi"`. Konfirmasi: `"Terapkan {latestLabel} ke angkatan ini? {responseCount} penilaian akan dibuka ulang dan reviewer-nya diberi tahu."`.
    - `SubmittedEvaluationSheets` Score + `version_label: string`, `superseded: bool`; jawaban `superseded` hanya ikut bila `$includeReplaced` (Operator) — peer/peneliti (BR-REV-11/12, BR-MON-09) hanya membaca yang berlaku. `EvaluationFormData` + `versionLabel: string`. FE: `ScoreSection` menampilkan chip label versi, baris riwayat `"Riwayat · {Versi n}"` diredam; `EvaluationFormPanel` menampilkan `versionLabel` di kepala lembar.

- [ ] **Step 1: Tulis skenario** REV-VER-06..09 + **BR-REV-19** ("Operator boleh menerapkan versi terbaru Form Evaluasi ke satu angkatan × langkah. Jawaban yang ada — draf maupun sudah disubmit — menjadi riwayat yang tetap terbaca, dan reviewer-nya mengisi ulang versi baru serta diberi tahu. Pengecualian atas BR-REV-08. Ditolak bila sudah ada pengajuan di langkah itu yang diputuskan; tidak ada transisi status baru dan keputusan yang sudah diambil tidak dibatalkan.").
- [ ] **Step 2: Tulis test** `ApplyEvaluationVersionTest.php` — REV-VER-06..09, Review Focus 4 dan 5, ditambah: `it('refuses when the cohort already uses the latest version')`, `it('counts decided monev reports at a monev step')`, `it('hides superseded answers from the researcher but shows them to the operator')`, `it('forbids the page and the action without periods.manage')`. Notifikasi: `Notification::fake()` + `assertSentTo` per reviewer tersentuh, `assertNothingSent` saat ditolak.
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi BE** sesuai Interfaces.
- [ ] **Step 5: Pest berkas di atas + `tests/Feature/Review`** → PASS.
- [ ] **Step 6: Tulis test Vitest** — halaman versi: baris belum terpaku berteks persis, tombol Terapkan hanya bila `canApply`, `decidedCount > 0` menampilkan alasan tanpa tombol, konfirmasi berteks persis; `ScoreSection`: chip `"Versi 2"`, baris riwayat.
- [ ] **Step 7: Implementasi FE**; Vitest berkas di atas → PASS.
- [ ] **Step 8: Commit** — `feat(review): terapkan versi baru form evaluasi ke angkatan`

### Task 4: Penutup G2 — seeder, review, gate, dokumen

**Files:**

- Modify: `database/seeders/EndToEndFlowSeeder.php` (Form Evaluasi pustaka + Versi 1 + pemasangan di langkah direview; satu penilaian tersimpan sehingga ada pin)
- Modify: `docs/adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md` (Status R3/R4 "Selesai — {commit}"; catat G2-W1, G2-W2, G2-W6), `docs/requirement-analysis/bdd/README.md` (daftar berkas baru bila README mendaftarnya), `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G2 selesai; rencana rinci += G2), `docs/plans/2026-11-11-fase-reuse-catatan-bug.md`

- [ ] **Step 1: Seeder** — `php artisan migrate:fresh --seed --force && php artisan db:seed --class=EndToEndFlowSeeder --force` di container. Klasifikasi auto-mode pernah menolak perintah ini (G1): bila ditolak lagi, minta pemilik menjalankannya (`! <perintah>`) — jangan dipaksa.
- [ ] **Step 2: Review akhir gelombang** (satu review menyeluruh G2). Perbaiki Critical/Important + yang murah; sisanya ke catatan bug.
- [ ] **Step 3: Gate penuh** — `free -m`, lalu `composer run quality` (`COMPOSER_PROCESS_TIMEOUT=0`) dan `npm run quality`, berurutan di bawah watchdog. Laporkan angka test apa adanya.
- [ ] **Step 4: Dokumen status** (berkas di atas).
- [ ] **Step 5: Commit** — `docs: status G2 form evaluasi berversi`
