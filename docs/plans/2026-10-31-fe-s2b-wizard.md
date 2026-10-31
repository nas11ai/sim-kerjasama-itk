# FE v2 — Irisan S2b: Wizard Pengajuan — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Peneliti membuat dan menyunting draft lewat wizard tujuh langkah di atas `AppLayout` (RESEARCHER-02-02, 02-06..02-10, dan 02-04 yang ternyata langkah 5), lalu mengirimnya dari langkah 7. `User/FormPhase`, `User/Submissions/ShowPeriod` + route `user.submissions.period`, route `user.form-phase`, `TargetOutputManager`, dan `ResearchMemberManager` terhapus.

**Architecture:** Satu halaman `Submission/WizardPage` untuk buat (tanpa baris, langkah 1) dan sunting (`/user/submissions/{id}/wizard/{step}`). Props disusun Query `SubmissionWizard` (langkah aktif saja) dan `ResearcherStartOptions` (langkah 1). Field Form Engine dibagi ke langkah menurut tipe field: `scheme_selector`/`trl_selector` → 3, `file` → 6, sisanya → 2. Semua navigasi antar langkah adalah satu POST `user.submissions.wizard.save` yang menyimpan jawaban langkah itu (bila ada) lewat `SaveSubmissionDraft`, mencatat kemajuan (`last_completed_step`), lalu redirect ke langkah tujuan. Anggota, mahasiswa, mitra, target luaran, dan anggaran memakai endpoint per baris yang sudah ada. Submit lewat route baru `user.submissions.submit` → `SubmitFormSubmission`.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest.

**Spec:** `docs/design/fe-v2-design.md` (§4.1–§4.6, §6, baris S2b-1/S2b-2 di §8), `docs/adr/0012-wizard-driven-submission-lifecycle.md`, `docs/requirement-analysis/bdd/core/submission/wizard.feature`, DDD `core/01_submission.md` (tabel langkah, BR-SM-17). Frame: `../simpas-wireframe/screens/RESEARCHER-02-{02,06,07,08,04,09,10}/screen.js`.

**Dua session.** S2b-1 = Task 1–6 (cangkang wizard, langkah 1/2/3/6/7, submit, unduh berkas, hapus legacy). S2b-2 = Task 7–9 (langkah 4 dan 5). Masing-masing ditutup dengan gate hijau dan commit sendiri.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris; tanpa blok duplikat 6 baris dengan berkas mana pun, **termasuk legacy** (`FormPhase.vue` jangan disalin — tulis dari wireframe).
- Baris pertama `<script setup>` halaman: `// Frame: RESEARCHER-02-02, RESEARCHER-02-06, RESEARCHER-02-07, RESEARCHER-02-08, RESEARCHER-02-04, RESEARCHER-02-09, RESEARCHER-02-10`. Komponen yang menggambar konsekuensi aturan diberi komentar BR.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris. Kosakata dari `02_ubiquitous_language.md` ("Ketua", "Co-Investigator", "Anggota", "Diarsipkan").
- TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast. `app/Queries/`, `app/Data/`, `app/Actions/` tier strict.
- Status hanya lewat `transitionTo()`; setiap Query yang mengembalikan data submission di-scope (`isAccessibleBy`/Policy). Logic di Action/Query, controller tipis.
- PostgreSQL: pencarian `whereLike` (ILIKE).
- Tanpa dependency baru, tanpa `axios`. Mutasi lewat `router`/`useForm`. Pencarian kandidat anggota lewat partial reload Inertia, bukan endpoint JSON.
- `config/inertia.php` testing `ensure_pages_exist: true`.
- Route baru `user.submissions.create` dideklarasikan **sebelum** `/submissions/{submission}`.
- Route legacy `user.form-submission.save-draft` dan `user.form-submission.submit` **tetap** (tanpa konsumen frontend setelah S2b; test HTTP-nya mengikat optimistic lock & resolusi). Hapus di F99.
- Gate: `composer run quality` + `npm run quality`. Pest di container:
  `docker run --rm --network host -v /home/nas11ai/projects/valtech/sim-kerjasama-itk:/home/nas11ai/projects/valtech/sim-kerjasama-itk -w <worktree> -u 1000:1000 -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.
- Berkas unggahan pengguna — jawaban pengajuan, unggahan biodata, lampiran review — disimpan di disk **`local`** (`storage/app/private`), bukan `public` (keputusan pemilik 2026-10-31, W15). Tidak ada URL publik; satu-satunya jalan baca adalah route unduh ber-scope. Lampiran pengumuman tetap `public` (pengumuman publik dibaca tamu, §4.7).

## Review Focus

1. **Berpindah langkah tanpa mengubah isi tidak boleh menggeser `updated_at`.** Kalau bergeser, tab kedua/penyunting lain kena 409 optimistic lock hanya karena seseorang menekan Lanjut. → Task 2, test `berpindah langkah tanpa jawaban tidak mengubah updated_at`.
2. **Mengganti skema saat TRL lama tidak diizinkan skema baru** harus ditolak dengan error di field TRL, bukan TRL lama diam-diam bertahan di `trl_id`. Frontend mengosongkan TRL saat skema berubah. → Task 3 test `TRL di luar izin skema ditolak`, Task 5 Vitest `mengosongkan TRL saat skema berganti`.
3. **Mengosongkan field opsional lalu Lanjut harus benar-benar mengosongkannya**, sementara field berkas yang tidak disentuh **tidak** boleh terhapus. Wizard v2 memuat ulang jawaban, jadi semantik `fill` (kosong = belum disentuh) salah untuknya. → Task 2 test `jawaban yang dikosongkan terhapus, berkas yang tidak dikirim tetap`; Task 5 Vitest `tidak mengirim field berkas tanpa berkas baru`.
4. **Submit lewat route baru setelah batas fase lewat (BR-FE-08)** harus ditolak. Jalur legacy lolos dari celah ini hanya karena ia menyimpan draft (yang memeriksa `PhaseAccessGate`) sebelum submit. → Task 2 test `[BR-FE-08] submit lewat batas fase ditolak`.
5. **Membuka wizard pengajuan yang sudah disubmit, terarsip, atau milik orang lain** dijawab 403, bukan form yang bisa diisi; langkah di luar 1..7 → 404; berkas jawaban pengajuan lain tidak bisa diunduh lewat id pengajuan sendiri, dan proposal yang baru diunggah **tidak** ada di disk `public` (tak terbuka lewat `/storage/…`). → Task 1 dan Task 3.

---

## S2b-1

### Task 1: Read model wizard dan halaman kosong

**Files:**
- Create: `app/Queries/Submission/SubmissionWizard.php`, `app/Queries/Submission/ResearcherStartOptions.php`
- Create (`app/Data/View/Submission/Wizard/`): `WizardSubmissionData.php`, `WizardStepData.php`, `WizardFieldData.php`, `WizardReviewData.php`, `WizardMissingFieldData.php`, `StartPeriodOptionData.php`, `StartFormOptionData.php`
- Create: `app/Http/Controllers/Submission/SubmissionWizardController.php` (`create`, `edit`)
- Modify: `routes/web.php`, `app/Support/RequiredFieldGuard.php`, `app/Support/AccountStateGuard.php`, `app/Support/WizardStepGate.php`, `app/Data/View/Submission/SubmissionTargetOutputData.php` (+ `int $id` di depan, untuk hapus per baris; tipe TS `SubmissionTargetOutput` ikut)
- Create: `resources/js/Pages/Submission/WizardPage.vue` (kerangka: `AppLayout` + `PageHeader` saja; diisi Task 4)
- Test: `tests/Feature/Submission/SubmissionWizardQueryTest.php`, `tests/Feature/Submission/SubmissionWizardPageTest.php`

**Interfaces:**
- Produces (PHP, properti camelCase):
  - `WizardStepGate::LABELS` = `[1 => 'Pilih Form & Periode', 2 => 'Informasi Dasar', 3 => 'Skema, TRL & Target Luaran', 4 => 'Anggota Penelitian', 5 => 'Rencana Anggaran', 6 => 'Upload Berkas', 7 => 'Review & Submit']`; `WizardStepGate::stepForFieldType(string $type): int` → 3 untuk `scheme_selector|trl_selector`, 6 untuk `file`, 2 selain itu.
  - `RequiredFieldGuard::missingFields(FormSubmission $s): Collection<int, FormField>` (Extract Method dari `assertComplete`, yang tetap melempar dengan pesan yang sama).
  - `AccountStateGuard::submitBlockReason(User $u): ?string` (Extract Method dari `assertVerified`; dua pesan yang sama, null bila aktif).
  - `WizardSubmissionData(int $id, string $title, SubmissionStatusData $status, ?string $period, string $form, string $lastUpdatedAt)` — `title` = `form.title` (sama dengan S2a), `lastUpdatedAt` = ISO `updated_at` (cap BR-FE-14).
  - `WizardStepData(int $number, string $label, bool $completed, ?string $blockedReason)` — `completed` = `number <= last_completed_step`, alasan dari `WizardStepGate::blockedReason`.
  - `WizardFieldData(int $id, string $label, string $type, bool $required, ?string $value, list<array{value: string, label: string}> $options, ?string $fileName, ?string $downloadUrl)` — `required` = `FormField::isRequiredFor($submission)` (BR-FE-11); `options`: `select|radio` dari `formFieldOptions`, `scheme_selector` = skema aktif **ditambah** skema terpilih walau nonaktif (BR-SCH-10, [SCH-RULE-14]), `trl_selector` = `scheme->allowedTrls` (BR-SCH-05) atau `[]` tanpa skema; `fileName` = basename nilai berkas; `downloadUrl` = `route('user.submissions.files.show', [id, responseId])` (route dari Task 3; sampai Task 3, null).
  - `WizardMissingFieldData(int $fieldId, string $label, string $message, int $step)`; `WizardReviewData(?string $scheme, ?string $trl, int $memberCount, int $targetOutputCount, int $budgetTotal, ?int $maxBudget, list<string> $fileNames, list<WizardMissingFieldData> $missingFields, ?string $accountBlock)`.
  - `SubmissionWizard::run(User $viewer, FormSubmission $submission, int $step): array{step: int, steps: list<WizardStepData>, submission: WizardSubmissionData, fields: list<WizardFieldData>, targetOutputs: list<SubmissionTargetOutputData>, targetOutputTypes: list<array{value: string, label: string}>, review: WizardReviewData|null, can: SubmissionAbilitiesData}` — `fields` hanya field langkah itu (urut `order`), kosong untuk 1/4/5/7; `targetOutputs`/`targetOutputTypes` (`TargetOutputTypeChoices`) hanya langkah 3; `review` hanya langkah 7.
  - `StartFormOptionData(int $formId, int $formPhaseId, string $title, ?int $draftId)`; `StartPeriodOptionData(int $id, string $name, ?string $closesAt, list<StartFormOptionData> $forms)`; `ResearcherStartOptions::run(User $u): list<StartPeriodOptionData>` — hanya periode `SubmissionPeriodGate::isOpen`, fase yang terpasang di periode itu, detail fase yang `FormAccessControl::accessibleBy($u)`; `draftId` = `ResolveWizardSubmission` bila `$u->can('update', …)`; periode tanpa form dibuang.
  - Route (grup `auth`, `check_biodata`, prefix `user.`): `GET /submissions/create` → `create` (`can:submissions.create`, name `submissions.create`); `GET /submissions/{submission}/wizard/{step?}` → `edit` (`whereNumber('step')`, name `submissions.wizard`).
  - `create` merender `Submission/WizardPage` dengan `step: 1`, `submission: null`, `startOptions`, `steps` (semua `completed: false`, langkah 2–7 `blockedReason: 'Pilih form dan periode lebih dulu.'`), kunci lain kosong/null. `edit`: `abort_unless($viewer->can('update', $submission), 403)`, `abort_unless(1 <= step <= 7, 404)`, step default `WizardStepGate::resumeStep`, `startOptions: []`, `...SubmissionWizard::run`.

- [ ] **Step 1: Tulis Pest yang gagal.**
  - Query: `membagi field per langkah menurut tipe` (text→2, scheme_selector→3, file→6); `[FE-TEMPORAL-01]`-gaya `field yang diwajibkan setelah draft dibuat tidak wajib` (`required_since` > `created_at` → `required` false); `[SCH-RULE-14] skema nonaktif tidak ditawarkan kecuali sudah terpilih`; `[SCH-RULE-10] TRL tersaring skema terpilih`; `TRL kosong tanpa skema`; `[SM-WIZARD-04] langkah 5 membawa alasan penguncian`; `[SM-WIZARD-06] langkah 7 mendaftar field wajib yang kosong beserta langkahnya`; `[IAM-VERIFY-02] [IAM-VERIFY-09] langkah 7 membawa alasan akun` (dua pesan); `opsi mulai hanya dari periode terbuka dan form yang boleh diakses`; `opsi mulai menautkan draft yang masih bisa disunting` (draft → `draftId`; Rejected → null).
  - Halaman: `peneliti membuka Submission/WizardPage untuk membuat` (`->component('Submission/WizardPage')->where('step', 1)->has('startOptions')`); `tanpa submissions.create ditolak` → 403; `[SM-WIZARD-07] draft dibuka di langkah terakhir yang selesai` (`last_completed_step` 4 → `step` 4); `pengajuan yang sudah disubmit ditolak` → 403; `pengajuan peneliti lain ditolak` → 403; `[SM-ARCHIVE-01] draft terarsip ditolak` → 403; `langkah 8 tidak ada` → 404; `[SM-SUBMIT-13] Super Admin bukan pemilik membuka wizard tanpa can.submit` (bila `update` lolos lewat `Gate::before`; bila tidak, assert 403 — tulis sesuai Policy yang ada).
- [ ] **Step 2: Jalankan, pastikan gagal.** `… ./vendor/bin/pest tests/Feature/Submission/SubmissionWizardQueryTest.php tests/Feature/Submission/SubmissionWizardPageTest.php` → FAIL.
- [ ] **Step 3: Implementasikan** Extract Method di tiga kelas `Support`, Data class, dua Query, controller, route, kerangka `WizardPage.vue`.
- [ ] **Step 4: Jalankan** test Step 1 + `tests/Feature/Submission` + `tests/Feature/Identity` (pesan `assertVerified`) → PASS.
- [ ] **Step 5: Commit** `feat(be): read model wizard pengajuan`

### Task 2: Simpan langkah, kemajuan, dan submit

**Files:**
- Create: `app/Actions/FormSubmission/SaveWizardStep.php`, `app/Http/Requests/Submission/WizardStepRequest.php`
- Modify: `SubmissionWizardController.php` (`save`, `submit`), `UserFormController::startSubmission` (redirect ke wizard langkah 2), `app/Data/Request/SubmissionAnswersInput.php`, `app/Services/Submission/FieldAnswerWriter.php`, `app/Actions/FormSubmission/SaveSubmissionDraft.php`, `app/Actions/FormSubmission/SubmitFormSubmission.php`, `routes/web.php`
- Test: `tests/Feature/Submission/SaveWizardStepTest.php`, `tests/Feature/Submission/WizardSubmitTest.php`; sesuaikan assertion redirect di `tests/Feature/Submission/StartSubmissionTest.php` bila ada

**Interfaces:**
- Produces:
  - `SubmissionAnswersInput` + parameter terakhir `bool $clearsEmptyAnswers = false`. `SaveSubmissionDraft` memanggil `FieldAnswerWriter::overwrite` bila true, `fill` bila false (legacy tak berubah).
  - `FieldAnswerWriter::overwrite(FormSubmission $s, array<int, ?string> $answers, array<int, UploadedFile> $uploads, string $directory): void` — hanya field yang **dikirim** yang diganti; nilai null/'' menghapus jawabannya; field yang tidak dikirim tak disentuh.
  - `WizardStepRequest extends SubmissionAnswersRequest`: `prepareForValidation()` mengisi `form_id`, `submission_period_id`, `form_phase_id` dari pengajuan di route (`formPhaseDetail?->form_phase_id`); `responses` → `nullable|array`; tambah `goto` → `required|integer|between:1,7`; `toInput()` mengirim `clearsEmptyAnswers: true`.
  - `SaveWizardStep::run(FormSubmission $submission, SubmissionAnswersInput $input, int $step, int $goto, User $user): FormSubmission` — 403 bila `!can('update')`; bila ada jawaban/berkas: 409 (`ConflictHttpException`) bila `ResolveWizardSubmission` bukan baris ini, lalu `SaveSubmissionDraft::run`; bila `goto > step` dan `step > last_completed_step`: tulis `last_completed_step = step` **tanpa** menyentuh `updated_at`. Tanpa entri audit untuk kemajuan saja (komentar: ADR 0012 — state UI, bukan peristiwa bisnis).
  - Route: `POST /submissions/{submission}/wizard/{step}` → `save` (name `submissions.wizard.save`) → redirect `user.submissions.wizard` `[id, goto]`. `POST /submissions/{submission}/submit` → `submit` (name `submissions.submit`) → `SubmitFormSubmission::run` → redirect `user.submissions.show` + flash `success` "Pengajuan berhasil dikirim."; `ValidationException` kembali ke langkah 7 dengan error.
  - `SubmitFormSubmission`: setelah `guardSubmittable`, `PhaseAccessGate::assertOpenFor($submission, $submission->formPhaseDetail)` (`// BR-FE-08`).
  - `startSubmission` redirect ke `route('user.submissions.wizard', [$submission, 2])`.

- [ ] **Step 1: Tulis Pest yang gagal.**
  - `SaveWizardStepTest`: `[SM-WIZARD-02] isian langkah 2 tersimpan dan dimuat ulang`; `[SM-WIZARD-03] menyimpan langkah 2 tidak menuntut field langkah lain`; `jawaban yang dikosongkan terhapus, berkas yang tidak dikirim tetap`; `[SM-WIZARD-10] melompat mundur dari 6 ke 2 tidak mengubah kemajuan` (`last_completed_step` tetap 6); `maju dari langkah 3 mencatat kemajuan 3`; `berpindah langkah tanpa jawaban tidak mengubah updated_at` (`travelTo` lalu bandingkan); `[SM-WIZARD-05] memilih skema di langkah 3 menulis scheme_id`; `[BR-FE-14] cap waktu basi ditolak 409`; `anggota tim ditolak 403`; `[IAM-VERIFY-11] akun pending tetap boleh menyimpan`.
  - `WizardSubmitTest`: `[SM-SUBMIT-09] ketua submit dari langkah 7` → status Submitted, redirect detail; `[SM-SUBMIT-07] tanpa proposal PDF ditolak dengan error per field` (`assertSessionHasErrors('field_X')`), status tetap Draft (`[SM-SUBMIT-16]`); `[SM-SUBMIT-15] pengajuan aktif yang sama disebut` (pesan memuat judul pengajuan lain); `[IAM-VERIFY-02] akun pending ditolak`; `[BR-FE-08] submit lewat batas fase ditolak` (pola fixture `PhaseOverrideTest`); `[SM-WIZARD-01] langkah 1 mengarahkan ke langkah 2`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan.** `overwrite` memakai ulang `storedValues` + `create`; hapus jawaban untuk `array_keys($answers + $uploads)`.
- [ ] **Step 4: Jalankan** test Step 1 + `tests/Feature/Submission` + `tests/Feature/FormEngine` (optimistic lock, phase override) → PASS.
- [ ] **Step 5: Commit** `feat(be): simpan langkah wizard dan submit dari langkah 7`

### Task 3: Sinkron TRL, unggahan di disk privat, dan unduh berkas jawaban

**Files:**
- Create: `app/Actions/Scheme/SyncTrlSelection.php`, `app/Http/Controllers/Submission/SubmissionFileController.php`, `app/Support/PrivateUploadDisk.php`
- Modify: `SaveSubmissionDraft.php` (panggil `SyncTrlSelection` setelah `SyncSchemeSelection`), `app/Services/Submission/FieldAnswerWriter.php`, `app/Http/Controllers/ReviewController.php:93`, `app/Http/Controllers/Review/ReviewAttachmentDownloadController.php`, `routes/web.php`, `app/Data/View/Submission/SubmissionAnswerData.php` + pembuatnya di `ResearcherSubmissionDetail`, `SubmissionWizard.php` (`downloadUrl`)
- Test: `tests/Feature/Scheme/TrlSelectionTest.php`, `tests/Feature/Submission/SubmissionFileDownloadTest.php`; Modify `tests/Feature/Submission/ResearcherSubmissionDetailTest.php`, dan `Storage::fake('public')` → `Storage::fake('local')` di `ResearcherWizardPagesTest.php`, `SubmitProposalRequirementTest.php`, `SaveBiodataTest.php`, `ReviewAttachmentDownloadTest.php`, serta test baru Task 2. `AnnouncementLifecycleTest.php` **tidak** berubah.

**Interfaces:**
- `SyncTrlSelection::run(FormSubmission $s): void` — baca jawaban `trl_selector`; kosong → `trl_id` null; nilai harus id di `scheme->allowedTrls`, selain itu `ValidationException` `field_{id}` "TRL ini tidak diizinkan skema terpilih."; berubah → `AuditTrail::record('TRL pengajuan dipilih', …, new AuditChange(old: ['trl_id' => …], new: ['trl_id' => …]))`. Form tanpa `trl_selector` → tidak melakukan apa-apa. Komentar BR-SCH-05, BR-SCH-09.
- `PrivateUploadDisk::NAME = 'local'` (kelas `final` berisi konstanta saja; komentar `// fe-v2-design W15 — unggahan pengguna tidak pernah punya URL publik`). Dipakai `FieldAnswerWriter::storedValues` (`$file->store($directory, PrivateUploadDisk::NAME)` — mencakup jawaban pengajuan **dan** biodata `SaveBiodata`), `ReviewController` (lampiran review), `ReviewAttachmentDownloadController` (`exists` + `download`), dan `SubmissionFileController`. Replace Magic Literal: tidak ada lagi string `'public'` di jalur unggahan pengguna. Path tersimpan tetap relatif (`form-uploads/…`, `biodata-uploads/…`, `review-attachments/{id}/…`).
- Route `GET /user/submissions/{submission}/files/{response}` (name `user.submissions.files.show`, grup `auth`): `abort_unless($submission->isAccessibleBy($user), 403)`; 404 bila `response.form_submission_id !== submission.id`, tipe field bukan `file`, atau berkas tidak ada di disk `local`; selain itu `Storage::disk(PrivateUploadDisk::NAME)->download($value, basename($value))`. Komentar `// BR-SM-07 — satu-satunya jalan baca berkas pengajuan`.
- Berkas lama: tidak ada migrasi berkas (aplikasi belum live, §7). Karena path relatif, DB dev yang ingin mempertahankan berkasnya cukup `mv storage/app/public/{form-uploads,biodata-uploads,review-attachments} storage/app/private/`. Tulis kalimat ini di laporan penutup S2b-1, jangan dijalankan otomatis.
- `SubmissionAnswerData` + `?string $downloadUrl` (hanya bila `isFile` dan bernilai).

- [ ] **Step 1: Tulis Pest yang gagal**: `[SM-WIZARD-05] TRL di langkah 3 menulis trl_id`; `TRL di luar izin skema ditolak` (ganti skema, TRL lama tetap dikirim → error `field_X`, `trl_id` & `scheme_id` lama utuh karena transaksi batal); `mengosongkan TRL mengosongkan trl_id`; `perubahan TRL tercatat di audit`. Disk: `[BR-SM-07] proposal yang diunggah tersimpan di disk privat, bukan public` (`Storage::fake('local')` + `Storage::fake('public')`; simpan langkah 6 → `assertExists` di `local`, `assertMissing` di `public`); `unggahan biodata tersimpan di disk privat` (di `SaveBiodataTest`, pola yang sama); `lampiran review tersimpan di disk privat dan tetap bisa diunduh pihak yang berhak` (di `ReviewAttachmentDownloadTest`; assertion akses yang ada tidak dihapus). Unduh: `ketua mengunduh proposalnya`; `[SM-ACCESS-03] peneliti lain ditolak 403`; `jawaban dari pengajuan lain 404`; `jawaban bukan berkas 404`. Detail S2a: `berkas membawa downloadUrl`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan.**
- [ ] **Step 4: Jalankan** test Step 1 + `tests/Feature/Scheme` + `tests/Feature/Submission` → PASS.
- [ ] **Step 5: Commit** `feat(be): sinkron TRL dan unduhan berkas pengajuan`

### Task 4: Cangkang wizard dan langkah 1 (RESEARCHER-02-02)

**Files:**
- Create: `resources/js/types/submission-wizard.ts`, `resources/js/Components/app/Stepper.vue`, `resources/js/Components/Submission/Wizard/{WizardFooter,StartStep}.vue`
- Modify: `resources/js/Pages/Submission/WizardPage.vue`, `resources/js/Pages/Submission/IndexPage.vue` + `SubmissionViewController::userIndex` (`canCreate`)
- Test: `tests/frontend/components/app/Stepper.spec.ts`, `tests/frontend/components/Submission/Wizard/StartStep.spec.ts`; Modify `tests/Feature/Submission/SubmissionIndexPageTest.php`

**Interfaces:**
- Produces (TS, cermin Task 1): `WizardFieldType` (union 14 nama di `FieldTypeSeeder`), `WizardStep`, `WizardSubmission`, `WizardField`, `WizardMissingField`, `WizardReview`, `StartFormOption`, `StartPeriodOption`, `SubmissionWizardPageProps { step: number; steps: WizardStep[]; submission: WizardSubmission | null; startOptions: StartPeriodOption[]; fields: WizardField[]; targetOutputs: SubmissionTargetOutput[]; targetOutputTypes: FilterOption[]; review: WizardReview | null; can: SubmissionAbilities | null }` (S2b-2 menambah `members`, `budget`).
- `Stepper.vue`: props `{ steps: WizardStep[]; active: number; disabled?: boolean }`, emit `select(number)`. Langkah selesai bertanda centang, langkah ber-`blockedReason` bertanda gembok dengan `title` = alasan tapi **tetap bisa dipilih** (BR-SM-17: halaman yang menjelaskan kuncinya); `disabled` mematikan semua kecuali `active`.
- `WizardFooter.vue`: props `{ step: number; savedAt: string | null; processing: boolean; showNext: boolean }`, emit `back`, `next`; teks `Tersimpan {jam:menit}` dari `savedAt`; langkah 1 menampilkan "Batalkan" (tautan `user.submissions.index`) bukan "Sebelumnya".
- `StartStep.vue`: props `{ submission: WizardSubmission | null; options: StartPeriodOption[] }`. Tanpa `submission`: `Select` Periode → `Select` Form (dari periode terpilih), hint "Hanya form yang dapat Anda akses sesuai organisasi Anda", bila form terpilih punya `draftId` tampil `Link` "Lanjutkan draft" ke wizard draft itu, selain itu tombol Lanjut → `router.post(route('user.submissions.store'), { submission_period_id, form_id, form_phase_id })`; error `profile` ditampilkan di `AppBanner tone="denial"`. Dengan `submission`: `InfoGrid` Periode/Form + catatan "Form dan periode tidak dapat diganti setelah draft dibuat." Selalu: `AppBanner` "Jika periode ditutup saat Anda masih mengisi, draft tetap tersimpan namun tidak dapat disubmit." (`<!-- BR-SM-10 -->`). Tanpa periode terbuka: `EmptyState` "Belum ada periode pengajuan yang dibuka."
- `WizardPage.vue`: `PageHeader` — buat: judul "Buat Pengajuan", subjudul "Isi pengajuan Anda tahap demi tahap — kemajuan tersimpan setiap berpindah langkah."; sunting: back "Daftar Pengajuan", judul `Buat Pengajuan — {label langkah}`, subjudul `submission.title`, badge `StatusBadge`. Grid `lg:grid-cols-[14rem_1fr]`: `Stepper` kiri, konten langkah + `WizardFooter` kanan. Langkah 4/5 sementara `EmptyState` "Langkah ini disiapkan di irisan berikutnya." (diganti S2b-2).
- `IndexPage`: tombol "Buat Pengajuan Baru" (`Link` `user.submissions.create`) di aksi `PageHeader` bila prop `canCreate` (`$viewer->can('submissions.create')`).

- [ ] **Step 1: Tulis test yang gagal.** Vitest `Stepper`: `menandai langkah selesai`, `[SM-WIZARD-04] langkah terkunci tetap bisa dipilih dan menyebut alasannya`, `[SM-WIZARD-10] memancarkan select untuk langkah sebelumnya`, `disabled hanya menyisakan langkah aktif`. `StartStep`: `form tersaring periode terpilih`, `menawarkan lanjutkan draft bila ada draftId`, `Lanjut memanggil router.post ke user.submissions.store dengan form_phase_id`, `draft yang ada tampil hanya-baca`. Pest: `IndexPage membawa canCreate`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan** tipe, komponen, halaman.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend/components/app/Stepper.spec.ts tests/frontend/components/Submission/Wizard` + Pest IndexPage + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): cangkang wizard dan langkah pilih form`

### Task 5: Langkah jawaban 2, 3, dan 6 (RESEARCHER-02-06, 02-07, 02-09)

**Files:**
- Create: `resources/js/composables/useWizardStep.ts`, `resources/js/Components/Submission/Wizard/{WizardFieldInput,WizardFieldList,AnswersStep,SchemeStep,TargetOutputEditor}.vue`
- Modify: `WizardPage.vue`
- Test: `tests/frontend/composables/useWizardStep.spec.ts`, `tests/frontend/components/Submission/Wizard/{WizardFieldInput,SchemeStep,TargetOutputEditor}.spec.ts`

**Interfaces:**
- `useWizardStep(submission: WizardSubmission, step: number, fields: WizardField[]): { values: Record<number, string | File | null>; go: (goto: number) => void; processing: Ref<boolean>; errors: Ref<Record<string, string>> }` — `values` diisi dari `field.value` (berkas: null). `go` → `router.post(route('user.submissions.wizard.save', [submission.id, step]), { goto, last_updated_at: submission.lastUpdatedAt, responses }, { forceFormData: <ada File>, preserveScroll: true })`; `responses` = semua field non-berkas langkah ini `{ form_field_id, value }` (string kosong → null) + field berkas **hanya** yang berisi `File` baru.
- `WizardFieldInput.vue`: props `{ field: WizardField; modelValue: string | File | null; error?: string }`. `text|email|url|phone|number|date|time` → `Input` dengan `type` padanannya (`phone` → `tel`); `textarea` → `Textarea`; `select|scheme_selector|trl_selector` → `Select` dari `options` (`trl_selector` tanpa opsi: placeholder "Pilih skema terlebih dahulu", nonaktif); `radio` → `RadioGroup`; `checkbox` → `Checkbox` bernilai `'1'`/`'0'`; `file` → input berkas + tautan `fileName` ke `downloadUrl` bila ada. Label diberi tanda wajib bila `required`. Error dibaca dari `field_{id}` atau `responses.{i}.value`.
- `WizardFieldList.vue`: props `{ fields: WizardField[]; values; errors }`, `v-model` per field; kosong → `EmptyState` "Form ini tidak punya isian di langkah ini."
- `AnswersStep.vue` (langkah 2 dan 6): `SectionCard` `Step {n} — {label}` + `WizardFieldList`. Langkah 2 catatan: "Field bertanda wajib mengikuti konfigurasi Form saat pengajuan ini dibuat. Field yang diwajibkan setelah tanggal tersebut tidak menghalangi submit." (`<!-- BR-FE-11 -->`). Langkah 6 banner: "Proposal PDF wajib ada sebelum submit. Berkas lain bersifat opsional kecuali diminta skema." (`<!-- BR-SM-02 -->`).
- `SchemeStep.vue`: `SectionCard "Step 3 — Skema & TRL"` + field langkah 3 + banner "Pilihan TRL disaring oleh skema yang dipilih. Batas anggaran dan pilihan target luaran juga mengikuti skema ini."; mengganti nilai `scheme_selector` mengosongkan nilai `trl_selector`; Form tanpa field langkah 3: `AppBanner` "Form ini tidak memakai skema — batas anggaran tidak berlaku." (`<!-- BR-SCH-04 -->`). Di bawahnya `TargetOutputEditor`.
- `TargetOutputEditor.vue`: props `{ submissionId: number; targetOutputs: SubmissionTargetOutput[]; types: FilterOption[] }`. Baris = badge tipe + deskripsi + "Hapus" (`router.delete(route('user.submissions.target-outputs.destroy', [submissionId, id]))`); `Select` tipe + "Tambah Target" (`router.post(route('user.submissions.target-outputs.store', submissionId), { output_type })`), `preserveScroll`. Catatan: "Tipe yang tersedia dibatasi skema terpilih. Target ini masih bisa diubah setelah pengajuan disetujui — setiap perubahan dicatat di jejak audit." (`<!-- BR-SM-14, BR-SM-15 -->`). Tambah/hapus target **tidak** lewat `go` (tersimpan per baris).

- [ ] **Step 1: Tulis Vitest yang gagal.** `useWizardStep`: `mengirim seluruh field non-berkas langkah ini`, `tidak mengirim field berkas tanpa berkas baru`, `memakai FormData bila ada berkas`, `string kosong dikirim sebagai null`, `membawa goto dan last_updated_at`. `WizardFieldInput`: dataset tipe → elemen yang dirender; `menampilkan error field_{id}`; `trl tanpa opsi nonaktif`. `SchemeStep`: `mengosongkan TRL saat skema berganti`, `[SM-WIZARD-09] form tanpa skema memberi tahu batas anggaran tidak berlaku`. `TargetOutputEditor`: `menambah target lewat target-outputs.store`, `menghapus lewat target-outputs.destroy`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan** dan pasang di `WizardPage` (footer Lanjut/Sebelumnya/Stepper memanggil `go`; langkah tanpa field memanggil `go` dengan `responses` kosong).
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): langkah informasi dasar, skema, dan berkas wizard`

### Task 6: Langkah 7, sambungan detail, hapus legacy, tutup S2b-1

**Files:**
- Create: `resources/js/Components/Submission/Wizard/ReviewStep.vue`
- Modify: `WizardPage.vue`, `app/Queries/Submission/ResearcherSubmissionDetail.php` (`editUrl`), `resources/js/Components/Submission/FilesSection.vue` (tautan unduh), `resources/js/Pages/User/DashboardPage.vue` (tautan `user.form-phase` → `user.submissions.create`; perbaikan perilaku, bukan refactor)
- Delete: `resources/js/Pages/User/FormPhase.vue`, `resources/js/Pages/User/Submissions/ShowPeriod.vue`, `resources/js/Components/TargetOutputManager.vue`; route `user.form-phase` + `UserFormController::showFormPhase` + `app/Queries/Submission/ResearcherFormPhaseSteps.php`; route `user.submissions.period` + `SubmissionViewController::userShowPeriod` (+ `ResearcherPeriodPhases` bila tak ada pemakai lain — cek `grep -rn ResearcherPeriodPhases app`)
- Modify tests: `tests/Feature/Submission/ResearcherWizardPagesTest.php` (kasus GET `user.form-phase` dipindah ke `SubmissionWizardPageTest`/`SubmissionWizardQueryTest` dengan perilaku yang sama: pengajuan periode lain tidak dimuat, form tanpa akses ditolak), `ResearcherSubmissionDetailTest.php` (`editUrl`); `grep -rn "user.form-phase\|user.submissions.period\|User/FormPhase\|User/Submissions/ShowPeriod" tests app resources/js` harus kosong di akhir
- Modify: `quality-legacy.txt`, `quality-budget.txt`, `docs/design/fe-v2-design.md`, memori hand-off
- Test: `tests/frontend/components/Submission/Wizard/ReviewStep.spec.ts`

**Interfaces:**
- `ReviewStep.vue`: props `{ submission: WizardSubmission; review: WizardReview; can: SubmissionAbilities }`. `SectionCard "Step 7 — Periksa Sebelum Mengirim"` + `InfoGrid` Judul/Skema/TRL/Anggota (`{n} orang`)/Target Luaran (`{n} target`)/Total Anggaran (`formatRupiah(total)` + ` dari {formatRupiah(max)}` bila ada)/Berkas. `SectionCard "Pemeriksaan Kelengkapan"`:
  - `accountBlock` → `AppBanner tone="denial"` berisi pesannya (`<!-- BR-IAM-04, BR-IAM-13 -->`).
  - `missingFields` tidak kosong → banner "{n} field wajib belum terisi. Perbaiki sebelum mengirim." + daftar `label — message` dengan tombol "Ke Langkah {step}" (emit `goto(step)`); kosong → banner "Semua field wajib sudah terisi. Field yang baru diwajibkan setelah pengajuan ini dibuat tidak ikut diperiksa." (`<!-- BR-FE-11 -->`).
  - Selalu: "Setelah disubmit, pengajuan tidak dapat diubah kecuali Operator meminta revisi. Hanya ketua pengaju yang dapat menekan tombol Submit." (`<!-- BR-SM-04 -->`).
  - Semua error halaman (mis. `SM-SUBMIT-15`, `verification_status`) dirender di `AppBanner tone="denial"`, teks biasa.
  - Tombol: `can.submit` → "Submit Pengajuan" → `router.post(route('user.submissions.submit', id))` lewat `ConfirmDialog`; `can.resubmit` → "Kirim Ulang Revisi" → `user.submissions.resubmit`; keduanya nonaktif bila `accountBlock` atau `missingFields` ada. Tanpa keduanya: **tidak ada tombol**, banner "Hanya ketua pengaju yang dapat mengirim pengajuan ini." (`[SM-SUBMIT-13]`, ADR 0015).
- `editUrl` = `route('user.submissions.wizard', id)` bila `can('update')` dan periode + fase ada (Draft **dan** NeedsRevision; revisi tetap lewat wizard sampai S2c), selain itu null.

- [ ] **Step 1: Tulis test yang gagal.** Vitest `ReviewStep`: `[SM-WIZARD-06] mendaftar field wajib yang kosong dengan tautan langkah`, `[IAM-VERIFY-02] akun pending: tombol Submit nonaktif dengan alasan`, `[SM-SUBMIT-13] bukan ketua: tombol Submit tidak dirender`, `[SM-WIZARD-08] submit nonaktif selama field wajib kosong`, `revisi menawarkan Kirim Ulang Revisi`, `menampilkan error penolakan sebagai teks`. Pest: `editUrl menaut ke wizard`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan** `ReviewStep`, `editUrl`, `FilesSection` (nama berkas jadi tautan `downloadUrl`), `DashboardPage`.
- [ ] **Step 4: Hapus legacy** sesuai daftar Files; pindahkan test yang menyentuhnya. Baris `Pages/User/FormPhase.vue` dan `Pages/User/Submissions/ShowPeriod.vue` keluar dari `quality-legacy.txt`; `max.legacy.files` turun sebanyak baris yang hilang (88 → 86).
- [ ] **Step 5: Cek di browser** (resep di memori hand-off): buat draft → isi langkah 2 → ke langkah 6 lewat Stepper → unggah PDF → langkah 7 → submit → mendarat di detail dengan toast. Kalau tidak bisa dijalankan, laporkan apa adanya.
- [ ] **Step 6: Gate.** `composer run quality` dan `npm run quality`. Baca hasilnya; laporkan kegagalan apa adanya.
- [ ] **Step 7: Dokumen.** `fe-v2-design.md` §8: S2b-1 `☑`. §9 tambah W12–W16 (lihat bawah).
- [ ] **Step 8: Commit** `feat(fe): irisan S2b-1 wizard pengajuan langkah 1-3, 6, 7`

---

## S2b-2

### Task 7: Read model langkah 4 dan 5

**Files:**
- Create: `app/Queries/Submission/ResearchMemberCandidates.php`; Data `WizardMembersData.php`, `WizardMemberRowData.php`, `WizardStudentRowData.php`, `WizardPartnerRowData.php`, `MemberCandidateData.php`, `WizardBudgetData.php`, `WizardBudgetItemData.php` (`app/Data/View/Submission/Wizard/`)
- Modify: `SubmissionWizard.php` (kunci `members`, `budget`), `SubmissionWizardController::edit` (prop opsional `memberCandidates`)
- Test: `tests/Feature/Submission/WizardMembersBudgetTest.php`

**Interfaces:**
- `WizardMemberRowData(int $id, string $name, string $role, string $roleLabel, ?string $unit, ?string $nidn, bool $removable)` — ketua pertama (`id` 0, `removable` false, [SM-MEMBER-06]), lalu anggota **aktif**; `removable` = `can` Policy hapus anggota yang dipakai `RemoveResearchMember`.
- `WizardStudentRowData(int $id, string $name, string $studentId, ?string $studyProgram)`, `WizardPartnerRowData(int $id, string $institutionName, ?string $contactPerson)`.
- `WizardMembersData(list<WizardMemberRowData> $members, list<WizardStudentRowData> $students, list<WizardPartnerRowData> $partners, ?int $memberLimit, ?int $studentLimit, list<array{value: string, label: string}> $roleOptions)` — batas dibaca dari aturan skema yang sama dengan `AddResearchMember`/`AddStudentMember` (Extract Method ke `Scheme` bila sekarang tersirat di Action); `roleOptions` = peran yang divalidasi `ResearchMemberController::store`, dengan label UL.
- `MemberCandidateData(int $id, string $name, ?string $nidn, ?string $blockedReason)`; `ResearchMemberCandidates::run(FormSubmission $s, string $search): list<MemberCandidateData>` — `search` < 2 karakter → `[]`; user ber-permission `submissions.create`, nama atau NIDN `whereLike`, tanpa ketua dan anggota aktif, maks 10; `blockedReason` = "{nama} sedang ditugaskan sebagai reviewer pengajuan ini, jadi ia tidak dapat ditambahkan sebagai anggota." bila ia `SubmissionReviewer` pengajuan ini (BR-SM-08).
- `WizardBudgetItemData(int $id, int $componentId, string $component, string $item, int $volume, string $unit, int $unitPrice, int $total)`; `WizardBudgetData(list<WizardBudgetItemData> $items, int $total, ?int $maxBudget, list<array{value: string, label: string}> $components, ?string $blockedReason, bool $editable)` — `components` hanya komponen aktif (BR-BUD-05), `blockedReason` dari `WizardStepGate`, `editable` = `can('update-budget')`.
- `SubmissionWizard::run` + `members: WizardMembersData|null` (langkah 4), `budget: WizardBudgetData|null` (langkah 5). Controller: `memberCandidates` = `Inertia::optional(fn () => ResearchMemberCandidates::run($submission, $request->string('member_search')->value()))`.

- [ ] **Step 1: Tulis Pest yang gagal**: `[SM-MEMBER-06] ketua tercantum dan tidak bisa dikeluarkan`; `[SM-MEMBER-03] batas anggota dari skema`; `[SM-STUDENT-03] skema tanpa batas mahasiswa`; `anggota nonaktif tidak tercantum`; kandidat: `mencari nama tanpa peka huruf`, `[SM-MEMBER-01] reviewer pengajuan ini ditandai tidak bisa ditambahkan`, `anggota yang sudah ada tidak ditawarkan`, `pencarian pendek tidak memuat apa pun`; anggaran: `[SM-WIZARD-04] langkah 5 terkunci tanpa skema`, `[SM-WIZARD-09] form tanpa skema tanpa batas`, `[BUD-ITEM-03] komponen nonaktif tidak ditawarkan`, `[BUD-ITEM-04] item lama dengan komponen nonaktif tetap tampil`, `total = jumlah item`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan.**
- [ ] **Step 4: Jalankan** test Step 1 + `tests/Feature/Submission` + `tests/Feature/Budget` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model langkah anggota dan anggaran wizard`

### Task 8: Langkah Anggota (RESEARCHER-02-08) dan Anggaran (RESEARCHER-02-04)

**Files:**
- Create: `resources/js/Components/Submission/Wizard/{MembersStep,MemberSearch,StudentMembersCard,PartnersCard,BudgetStep,BudgetItemForm}.vue`
- Modify: `types/submission-wizard.ts`, `WizardPage.vue` (ganti `EmptyState` sementara)
- Delete: `resources/js/Components/ResearchMemberManager.vue`
- Test: `tests/frontend/components/Submission/Wizard/{MembersStep,MemberSearch,BudgetStep}.spec.ts`

**Interfaces:**
- `MembersStep.vue`: `SectionCard "Step 4 — Anggota Penelitian"` tabel Nama/Peran/Unit/NIDN/aksi "Hapus" (`router.delete(route('user.submissions.members.destroy', [id, memberId]))`, lewat `ConfirmDialog`, hanya bila `removable`); catatan "Jumlah anggota dibatasi skema terpilih. Ketua tidak dapat dihapus — gunakan Alihkan Kepemilikan lewat Operator." (`<!-- BR-SM-18 -->`). Kuota: `InfoGrid` "Anggota saat ini {n} dari {limit}" bila `memberLimit`; penuh → banner "Jumlah anggota sudah mencapai batas skema. Keluarkan anggota lain sebelum menambah." dan pencarian nonaktif (`<!-- BR-SM-08, BR-SCH-03 -->`). Lalu `MemberSearch`, `StudentMembersCard`, `PartnersCard`.
- `MemberSearch.vue`: input "Cari nama atau NIDN..." (debounce 300 ms) → `router.reload({ only: ['memberCandidates'], data: { member_search } })`; `Select` peran dari `roleOptions`; kandidat dengan `blockedReason` dirender nonaktif + banner alasannya; "Tambah" → `router.post(route('user.submissions.members.store', id), { user_id, role }, { preserveScroll: true })`.
- `StudentMembersCard.vue` / `PartnersCard.vue`: daftar + form tambah (`student-members.store`: `name`, `student_id`, `study_program`; `partners.store`: `institution_name`, `contact_person`, `description`) + hapus (`*.destroy`); error dari `form.errors` (`[SM-STUDENT-04]`, `[SM-PARTNER-02]`).
- `BudgetStep.vue` (frame RESEARCHER-02-04): `blockedReason` → `AppBanner` + tombol "Ke Langkah 3" saja (`<!-- BR-SM-17, BR-BUD-01 -->`); selain itu tabel Komponen/Item/Volume/Satuan/Harga Satuan/Total + aksi Ubah/Hapus (`budget-items.update`/`destroy`), `BudgetItemForm` (komponen, item, volume, satuan, harga) untuk tambah/ubah, baris "Total {formatRupiah(total)}" + " dari {formatRupiah(maxBudget)}" bila ada; tanpa `maxBudget`: catatan "Pengajuan tanpa skema tidak punya batas anggaran."; `!editable` → hanya baca. Error `scheme_id`/total dari backend dirender apa adanya.

- [ ] **Step 1: Tulis Vitest yang gagal**: `MembersStep` — `[SM-MEMBER-06] ketua tanpa tombol hapus`, `[SM-MEMBER-03] kuota penuh mematikan pencarian`, `[SM-MEMBER-04] hapus anggota lewat members.destroy`; `MemberSearch` — `pencarian memuat ulang memberCandidates saja`, `[SM-MEMBER-01] kandidat reviewer nonaktif dengan alasan`, `menambah lewat members.store dengan peran`; `BudgetStep` — `[SM-WIZARD-04] tanpa skema hanya menampilkan alasan`, `[SM-WIZARD-09] tanpa batas menampilkan catatan`, `total dan batas dirender rupiah`, `[BUD-WHO-03] tidak editable: tanpa form`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan**, hapus `ResearchMemberManager.vue`.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): langkah anggota dan anggaran wizard`

### Task 9: Tutup S2b-2

- [ ] **Step 1:** Baris `resources/js/Components/ResearchMemberManager.vue` keluar dari `quality-legacy.txt`, `max.legacy.files` turun 1.
- [ ] **Step 2:** Cek browser: langkah 4 cari & tambah anggota, langkah 5 tambah item sampai melewati `max_budget` → error terbaca. Laporkan apa adanya bila tak bisa dijalankan.
- [ ] **Step 3:** `composer run quality` + `npm run quality`; laporkan apa adanya.
- [ ] **Step 4:** `fe-v2-design.md` §8: S2b-2 `☑`; baris S2c: hapus `EditPage` (RESEARCHER-02-04 adalah langkah 5 wizard, W12).
- [ ] **Step 5:** Memori hand-off: S2b selesai; F99 menghapus route `user.form-submission.save-draft`/`submit` + `UserFormController::saveDraft/submitForm` dengan test HTTP-nya dipindah ke `user.submissions.wizard.save`/`submit`; I1 membangun unduh berkas biodata ber-scope untuk verifikasi Operator (berkasnya sudah di disk privat, W15).
- [ ] **Step 6: Commit** `feat(fe): irisan S2b-2 wizard pengajuan selesai`

---

## Selisih untuk §9 (ditulis di Task 6 Step 7)

- **W12** — RESEARCHER-02-04 "Edit Pengajuan (Draft)" adalah wizard pada langkah 5, bukan layar tersendiri; tidak ada frame langkah 5 lain. Penulis rencana S2b: menyunting draft = wizard; `EditPage` dicoret dari S2c.
- **W13** — ADR 0012 menulis tujuh endpoint per langkah (PATCH basic-info, PATCH scheme, POST files, …). Langkah 2, 3, dan 6 sama-sama menulis `form_field_responses` (skema & TRL lewat field `scheme_selector`/`trl_selector`, ADR 0005), jadi ketiganya satu endpoint `POST …/wizard/{step}` dengan nomor langkah di route; langkah 4/5 memakai endpoint per baris yang sudah ada. Pembagian field ke langkah menurut tipe field (`WizardStepGate::stepForFieldType`), karena Form Engine tidak menyimpan langkah per field.
- **W14** — `trl_id` tidak pernah ditulis sebelum S2b (hanya `scheme_id` yang disinkron). S2b menambah `SyncTrlSelection` dengan validasi izin skema (BR-SCH-05).
- **W15** — Berkas jawaban pengajuan dulu disimpan di disk `public` (`FieldAnswerWriter`), sehingga terbuka lewat `/storage/…` bagi siapa pun yang tahu path-nya, di luar BR-SM-07. Pemilik, 2026-10-31: berkas pengajuan disimpan di penyimpanan privat (disk `local`) dan hanya dibaca lewat route unduh ber-scope; tanpa migrasi berkas lama. Pemilik, 2026-10-31 (lanjutan): unggahan biodata (`SaveBiodata`, termasuk KTP) dan lampiran review (`ReviewController`) ikut pindah ke disk privat di S2b; keduanya tanpa pembaca URL publik (biodata belum punya pembaca, lampiran review dibaca `ReviewAttachmentDownloadController` yang sudah ber-scope). Lampiran pengumuman tetap `public`. Unduh berkas biodata untuk Operator (verifikasi) dibangun di I1.
- **W16** — Banner langkah 1 "sudah memiliki pengajuan aktif pada periode dan skema yang sama" tidak bisa ditampilkan di langkah 1: skema baru dipilih di langkah 3 dan BR-SM-01 tidak menghitung DRAFT. Pesannya muncul saat submit ([SM-SUBMIT-15]). Wireframe menulis "Tersimpan otomatis"; wizard menyimpan setiap berpindah langkah, bukan autosave.
