# FE v2 — Irisan S4: Pengajuan Sisi Operator — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Operator kembali bekerja dari UI v2: mendaftar semua pengajuan (OPERATOR-02-01), membaca detailnya termasuk reviewer, lembar evaluasi, dan ulir (02-02, menutup W22), lalu menjalankan enam aksinya — perpanjang tenggat (02-03), alihkan kepemilikan (02-04), tolak manual (02-05), tarik (02-06), tugaskan reviewer (03-01), ganti reviewer (03-02).

**Architecture:** Satu controller tipis `App\Http\Controllers\Submission\OperatorSubmissionController` (`index`, `show`) di route bernama lama `admin.submissions.index/show`, keluar dari grup `can:users.manage` ke `can:submissions.view-all` (§4.4 K1). Detail disusun `OperatorSubmissionDetail` yang memakai ulang `ResearcherSubmissionDetail` lalu menambah bagian operator. Enam aksi adalah dialog di `Submission/ManageShowPage` (§8), memanggil endpoint yang sudah ada; ketersediaannya dibaca dari `operatorCan` (Policy, ADR 0015). Kandidat reviewer dimuat sebagai prop opsional Inertia (`Inertia::optional`) lewat partial reload, jadi pencarian tetap di server.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, spatie/laravel-model-states, Inertia v2 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest, Playwright.

**Spec:** `docs/design/fe-v2-design.md` (§4.1–§4.6, §5, §6, baris S4 di §8, K1/W22/W24 di §9). Frame: `../simpas-wireframe/screens/OPERATOR-0{2-01..06,3-01,3-02}/screen.js`; `FRAME-AUDIT.md`/`STATE-COVERAGE.md` bagian yang sama. Skenario: `docs/requirement-analysis/bdd/` (peta di bawah). BR: BR-SM-05/07/09/11, BR-REV-01..05/07/10/15, BR-FE-08/09/10/16/17, BR-BUD-02/09, BR-IAM-10, BR-RPT-09/11.

**Keputusan pemilik 2026-11-01 (dicatat ke §9 di Task 11):**
- **W25** — Perpanjang Tenggat tetap satu mekanisme (ADR 0014): `FormSubmissionOverride` yang berlaku pada detail pemilik form evaluasi juga membuka kembali evaluasi reviewer yang lewat tenggat (FE-WINDOW-02 tetap berlaku tanpa override).
- **W26** — Alasan pengalihan dan penarikan wajib, catatan penggantian reviewer opsional; ketiganya disimpan di entri audit Action-nya (`properties.new.reason` / `properties.new.note`), tanpa kolom baru. "Kategori" di Tolak Manual tidak digambar (DDD tak punya daftar kategori).

**Dua session** (§6, dipecah di §8 sebelum dikerjakan): **S4-1** = Task 1–4 (daftar + detail baca, legacy operator dihapus), **S4-2** = Task 5–11 (enam aksi, skenario baru, smoke). Selama di antara keduanya Operator bisa membaca tapi belum bisa bertindak dari UI — boleh menurut §7.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter; tanpa blok duplikat 6 baris dengan berkas mana pun termasuk legacy (halaman `Submissions/*` dan dialog legacy jangan disalin — tulis dari frame).
- Baris pertama `<script setup>` halaman baru: `// Frame: OPERATOR-…`.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris; kosakata `02_ubiquitous_language.md` ("Reviewer Pengganti", "Ulir", "co-investigator").
- TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- PostgreSQL: pencarian pakai `whereLike` (case-insensitive), bukan `LIKE`.
- Status hanya lewat `transitionTo()`; setiap Action mencatat satu entri audit (BR-RPT-09, `AuditCoverageTest`).
- Mutasi lewat `router`/`useForm`, tanpa `axios`; tanpa dependency baru.
- Test yang mengikat skenario BDD menyebut ID-nya. Skenario lama sudah terikat Pest, jadi `pending.txt`/`uncovered.txt` tidak berubah; skenario baru (Task 5–6) ditulis **bersama** test Pest-nya dalam commit yang sama, sehingga `pending.txt` tetap `max: 0`.
- Halaman yatim `Pages/SubmissionEvaluationManager/IndexPage.vue` dan `Pages/ReviewerAssignments/IndexPage.vue` **tidak** dihapus di S4: pemilik memutuskan halaman legacy yatim dihapus di F99. Task 11 memindahkan keduanya ke kolom F99 di §8.
- Gate: `composer run quality` + `npm run quality`. Pest di container:
  `docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Peta skenario

Semua ID di bawah ditag ke frame S4. "Pest ✓" = sudah terikat test (BddCoverageTest assertion 4); kolom Task = test tambahan yang menggambar state-nya di layar.

| ID | Inti yang harus teramati | Pest | Task |
| --- | --- | --- | --- |
| SM-ACCESS-04 | Pemegang `submissions.view-all` membuka daftar dan detail | ✓ | 1, 3 (200) |
| SM-SUBMIT-14 | Super Admin membuka detail dan bisa menarik | ✓ | 3 (`operatorCan.withdraw`) |
| SM-STATE-05 | Override tetap tersedia di status terminal | ✓ | 3 (`operatorCan.grantOverride` pada APPROVED) |
| BUD-LOCK-03, BUD-WHO-04 | Anggaran tetap baca-saja bagi Super Admin | ✓ | 3 (`budget.editable` false), 4 |
| RPT-PRINT-01 | Operator mencetak dokumen dari detail | ✓ | 4 (aksi Cetak) |
| SM-WITHDRAW-01 | Tarik APPROVED → WITHDRAWN | ✓ | 7 |
| SM-WITHDRAW-03 | Belum disetujui → tidak bisa ditarik | ✓ | 3, 4 (tombol tidak tersedia + hint) |
| SM-WITHDRAW-04 | Setelah ditarik anggaran, anggota, berkas tetap terbaca | ✓ | 3 |
| SM-WITHDRAW-05 | WITHDRAWN final | ✓ | 4 (banner final, tanpa aksi) |
| SM-OWNER-01 | Alih ke co-investigator aktif; ketua lama tetap anggota | ✓ | 7 |
| SM-OWNER-02 | Member biasa ditolak | ✓ | 3 (tak ditawarkan), 7 (pesan galat) |
| REV-OUTCOME-07 | Tolak manual dengan alasan tertulis | ✓ | 7 |
| REV-OUTCOME-10 | Butuh review + 0 reviewer → tetap SUBMITTED | ✓ | 3, 4 (banner "menunggu penugasan") |
| REV-ASSIGN-01/03 | Penugasan bertahap; minimum tercapai → UNDER_REVIEW | ✓ | 10 (tombol aktif sejak 1 dipilih) |
| REV-ASSIGN-02 | Indikator **"1 dari minimal 2"**, status tetap SUBMITTED | ✓ | 3, 10 |
| REV-ASSIGN-04/07 | Konflik kepentingan ditolak | ✓ | 9 (kandidat diblok), 10 |
| REV-ASSIGN-05 | Sudah ditugaskan di siklus ini ditolak | ✓ | 9, 10 |
| REV-ASSIGN-06 | Beban lewat batas = peringatan, penugasan tetap berhasil | ✓ | 9 (badge), 10 (flash peringatan) |
| REV-ASSIGN-08 | Di luar masa tugas tidak ditawarkan | ✓ | 9 |
| REV-REASSIGN-01 | Lama `replaced`, tetap ada | ✓ | 3 (baris "Digantikan") |
| REV-REASSIGN-02 | Evaluasi reviewer lama tetap terbaca | ✓ | 3 (lembar evaluasi ikut `replaced`) |
| FE-OVERRIDE-03/04/05 | Override membuka, kedaluwarsa/dicabut tidak | ✓ | 8 (status + cabut) |
| FE-OVERRIDE-06 | Alasan kosong → galat di field alasan | ✓ | 8 |
| MON-CYCLE-09, RO-OUTPUT-16 | Override pada detail milik pengajuan itu sendiri, berlaku sampai jam 23:59:59 | ✓ | 8 (tanggal → `23:59:59`) |
| RO-OUTPUT-20 | `outputs.manage` menyunting luaran | ✓ | — (layar luaran = O1) |
| **FE-WINDOW-05/06/07** | baru (W25) | Task 6 | 6 |
| **SM-OWNER-05/06, SM-WITHDRAW-06/07, REV-REASSIGN-04/05** | baru (W26) | Task 5 | 5, 7, 10 |

## Review Focus

1. **Draft dibuka lewat URL operator** → 404 seperti legacy ("bagi Operator ia belum ada"), dan tidak muncul di daftar. → Task 1 test `draft tidak terdaftar`, Task 3 test `draft dijawab 404`.
2. **Pemegang `submissions.view-all` tanpa `reviewers.assign`** tidak melihat Tugaskan/Ganti dan POST-nya 403 — dua sumber otorisasi tidak boleh bertentangan (ADR 0015). → Task 5 test `penugasan tanpa reviewers.assign ditolak`.
3. **Override untuk detail lain atau yang sudah habis** tidak membuka evaluasi (BR-FE-10). → Task 6 FE-WINDOW-06/07.
4. **Galat validasi dari Action** (member biasa SM-OWNER-02, konflik REV-ASSIGN-04/07, alasan kosong) tampil di dialog yang tetap terbuka, isian tidak hilang. → Task 7 Vitest `galat penerima tampil di dialog`, Task 10 Vitest `galat penugasan tampil dan dialog tetap terbuka`.
5. **Pencarian nama ketua beda huruf besar/kecil** tetap menemukan baris (PostgreSQL). → Task 1 test `pencarian judul atau ketua tidak peka huruf`.

---

## S4-1

**Sebelum Task 1:** pecah baris S4 di `fe-v2-design.md` §8 menjadi S4-1 (OPERATOR-02-01, 02-02) dan S4-2 (02-03..06, 03-01, 03-02), keduanya `☐`, kolom legacy sesuai Task 4.

### Task 1: Read model daftar operator (BE)

**Files:**
- Create: `app/Http/Controllers/Submission/OperatorSubmissionController.php`, `app/Queries/Submission/OperatorSubmissionList.php`, `app/Queries/Submission/FirstSubmitAt.php`, `app/Data/View/Submission/Manage/OperatorSubmissionRowData.php`
- Modify: `routes/web.php`, `app/Queries/Submission/ResearcherSubmissionList.php`, `app/Http/Controllers/SubmissionViewController.php` (hapus `adminIndex`, `adminShowPeriod`), `tests/Feature/Submission/SubmissionViewPagesTest.php` (hapus tiga test periode Operator, aturannya pindah ke test baru)
- Delete: `app/Queries/Submission/OperatorPeriodDirectory.php`, `app/Queries/Submission/OperatorPeriodSubmissions.php` (pastikan dulu dengan grep tak ada pemakai lain)
- Test: `tests/Feature/Submission/OperatorSubmissionListTest.php`

**Interfaces:**
- Produces: `GET /admin/submissions` → `admin.submissions.index` (`OperatorSubmissionController::index`, middleware `auth` + `can:submissions.view-all`, **di luar** grup `can:users.manage`); query `search`, `status`, `period`, `scheme`, `page`. Route `admin.submissions.period` dihapus.
- Produces: `FirstSubmitAt::subquery(): Builder` — Extract Method dari `ResearcherSubmissionList::firstSubmitAt()` (W7), dipakai kedua daftar.
- Produces: `OperatorSubmissionRowData(int $id, string $title, string $leadName, ?string $organization, ?string $scheme, SubmissionStatusData $status, ?string $submittedAt, int $reviewerCount)` — `organization` = `submittedBy.userProfile.organization.name`; `reviewerCount` = `withCount` penugasan `active`.
- Produces: `OperatorSubmissionList::run(array{search: string, status: string, period: string, scheme: string} $filters): array{submissions: LengthAwarePaginator<int, OperatorSubmissionRowData>, statusOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>}` — hanya `is_submitted = true` dan pengajuan induk (`parent_submission_id` null; laporan monev milik M1); `search` = `whereLike` judul form **atau** nama ketua; 15 per halaman, terbaru dulu (`updated_at`); `statusOptions` dari `SubmissionStatusChoices`; tanpa N+1.
- Props `Submission/ManageIndexPage`: `{ submissions, statusOptions, periodOptions, schemeOptions, filters: {search, status, period, scheme} }`.

- [ ] **Step 1: Tulis test yang gagal.** `[SM-ACCESS-04] pemegang submissions.view-all membuka Submission/ManageIndexPage` (Operator **tanpa** `users.manage`; `component`, `has('submissions.data', n)`, `where('submissions.data.0.leadName', …)`, `where('submissions.data.0.reviewerCount', 2)`); `peneliti tanpa view-all ditolak` (403); `draft tidak terdaftar`; `laporan monev tidak terdaftar`; `saring status, periode, dan skema`; `pencarian judul atau ketua tidak peka huruf` (`search=budi` menemukan ketua "Dr. Budi Santoso"); `tanggal submit dari jejak audit` (`submittedAt` = entri `SubmitFormSubmission::AUDIT_DESCRIPTION` pertama). `ResearcherSubmissionListTest` tetap hijau tanpa diubah.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Submission/OperatorSubmissionListTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Controller `// BR-SM-07 — view-all melihat seluruh pengajuan terkirim; draft belum ada bagi Operator.`
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Submission` + `tests/Unit/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model daftar pengajuan operator`

### Task 2: `Submission/ManageIndexPage` (FE)

**Files:**
- Create: `resources/js/types/submission-manage.ts`, `resources/js/Pages/Submission/ManageIndexPage.vue`, `resources/js/Components/Submission/Manage/OperatorSubmissionTable.vue`
- Test: `tests/frontend/pages/ManageIndexPage.spec.ts`

**Interfaces:**
- Produces (TS, cermin Task 1): `OperatorSubmissionRow`, `ManageIndexFilters`, `ManageIndexPageProps`. Ulang pakai `FilterOption`, `Paginated` yang sudah ada.
- `OperatorSubmissionTable` props `{ rows: OperatorSubmissionRow[]; filtered: boolean }`: `DataTable` kolom Judul (tautan `admin.submissions.show`), Lead Researcher, Organisasi, Skema, Status (`StatusBadge`), Submit (`formatDate`), Reviewer, aksi "Detail"; kosong → `EmptyState` ("Tidak ada pengajuan yang cocok dengan filter." bila `filtered`, selain itu "Belum ada pengajuan terkirim.").
- Halaman: `PageHeader` "Semua Pengajuan" / "Seluruh submission di sistem — filter lanjutan tersedia.", aksi "Export" outline → `exports.index`; `useFilters('admin.submissions.index', filters)`; `Input` "Cari judul / lead researcher...", tiga `FilterSelect` (status, periode, skema); `PaginationBar`.

- [ ] **Step 1: Tulis Vitest yang gagal.** `[SM-ACCESS-04] baris menaut ke detail operator`; `filter skema meminta ulang ke server` (router.get dengan `scheme`); `tanpa hasil saat tersaring menampilkan pesan filter`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/pages/ManageIndexPage.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman semua pengajuan operator`

### Task 3: Read model detail operator (BE)

**Files:**
- Create: `app/Queries/Submission/OperatorSubmissionDetail.php`, `app/Queries/Submission/OwnershipCandidates.php`, `app/Queries/Review/ManagedReviewers.php`, `app/Queries/Review/SubmittedEvaluationSheets.php`, `app/Support/Review/MinimumReviewers.php`, `app/Data/View/Submission/Manage/{OperatorAbilitiesData,ManagedReviewerData,OwnershipCandidateData,OverrideTargetData}.php`
- Modify: `routes/web.php`, `app/Http/Controllers/Submission/OperatorSubmissionController.php`, `app/Http/Controllers/SubmissionViewController.php` (hapus `adminShowSubmission`), `app/Policies/FormSubmissionPolicy.php`, `app/Actions/Review/AssignReviewer.php`, `app/Queries/Review/VisibleReviewerScores.php`, `resources/js/types/submission-page.ts` (`ReviewerScore.replaced`), `tests/Feature/Review/ReviewThreadCreationTest.php:115-127`, `tests/Feature/Review/ReviewThreadAuthorPayloadTest.php:25-42`
- Delete: `app/Queries/Submission/OperatorSubmissionView.php`
- Test: `tests/Feature/Submission/OperatorSubmissionDetailTest.php`

**Interfaces:**
- Produces: `GET /admin/submissions/{submission}` → `admin.submissions.show` (`OperatorSubmissionController::show`, `can:submissions.view-all`, di luar `users.manage`); draft → 404 "Pengajuan tidak ditemukan.".
- Produces Policy (`FormSubmissionPolicy`): `transferOwnership(User, FormSubmission): bool` = `submissions.view-all` (BR-IAM-10); `assignReviewers` dan `replaceReviewer` = `reviewers.assign` dan status `Submitted|UnderReview|NeedsRevision|Resubmitted` (`// BR-REV-01, BR-SM-05 — penugasan bertahap selama pengajuan terkirim dan belum diputuskan`). Hari ini route penugasan tidak menjaga status sama sekali; satu-satunya yang ditambah adalah kunci status terminal. `reject`, `withdraw`, `grantOverride` tetap.
- Produces: `OperatorAbilitiesData(bool $assignReviewers, bool $replaceReviewer, bool $grantOverride, bool $transferOwnership, bool $reject, bool $withdraw)` + `static forUser(User, FormSubmission): self`.
- Produces: `MinimumReviewers::for(FormSubmission): int` — Move Method dari `AssignReviewer::minimumReviewers()` beserta konstanta bawaan 2; `AssignReviewer` memanggilnya.
- Produces: `SubmittedEvaluationSheets::run(FormSubmission $submission, bool $includeReplaced): list<Score>` — Extract Class dari `VisibleReviewerScores` (yang memanggilnya dengan `false`, perilaku peneliti tak berubah); `Score` bertambah `replaced: bool`.
- Produces: `ManagedReviewerData(int $reviewerId, string $name, ?string $nidn, string $type, bool $replaced, string $evaluationLabel)` — `evaluationLabel`: `pending` "Evaluasi berjalan", `completed` "Selesai", `not_required` "Tanpa form evaluasi". `ManagedReviewers::run(FormSubmission): list<ManagedReviewerData>` — aktif dulu, lalu `replaced` (REV-REASSIGN-01).
- Produces: `OwnershipCandidateData(int $userId, string $name, ?string $organization)`; `OwnershipCandidates::run(FormSubmission): list<OwnershipCandidateData>` — logika `ownershipCandidates` dari `OperatorSubmissionView`, hanya co-investigator aktif (BR-SM-09).
- Produces: `OverrideTargetData(?string $phaseLabel, list<string> $affectedReviewers)` — `phaseLabel` = "{judul fase} — {label detail}" dari `getFormPhaseDetail()`; `affectedReviewers` = nama reviewer aktif yang punya form evaluasi `isOverdue()` (dipakai Task 8).
- Produces: `OperatorSubmissionDetail::run(User $viewer, FormSubmission $submission): array` = semua kunci `ResearcherSubmissionDetail::run` **kecuali** `targetOutputTypes`, ditambah `organization: ?string`, `reviewers: list<ManagedReviewerData>`, `activeReviewerCount: int`, `minimumReviewers: int`, `needsReview: bool` (`formPhaseDetail.needs_review`), `evaluationSheets: list<Score>` (`SubmittedEvaluationSheets::run($s, true)`, W22), `threads: list<ReviewThreadData>` (`ReviewThreadData::many` atas `ReviewThreads::run`), `ownershipCandidates`, `overrideTarget`, `operatorCan: OperatorAbilitiesData`. Tanpa `canCreateThread` (BR-REV-15: Operator bukan reviewer bertugas).

- [ ] **Step 1: Tulis test yang gagal.** `OperatorSubmissionDetailTest.php`: `pengajuan APPROVED tidak menawarkan penugasan reviewer` (`operatorCan.assignReviewers` false); `[SM-ACCESS-04] pemegang view-all membuka Submission/ManageShowPage` (`has('submission')`, `has('reviewers')`, `has('operatorCan')`, `missing('canCreateThread')`); `draft dijawab 404`; `peneliti tanpa view-all ditolak` (403); `[SM-SUBMIT-14] Super Admin membuka detail dan bisa menarik pengajuan APPROVED` (`operatorCan.withdraw` true); `[SM-WITHDRAW-03] pengajuan UNDER_REVIEW tidak bisa ditarik` (`operatorCan.withdraw` false, `operatorCan.reject` true); `[SM-WITHDRAW-04] pengajuan WITHDRAWN tetap membawa anggaran, anggota, dan berkas` (`budget.items` tidak kosong, `members` > 1, `answers` berisi berkas); `[BUD-LOCK-03] anggaran APPROVED tetap baca-saja bagi Super Admin` (`budget.editable` false); `[SM-STATE-05] perpanjangan tetap tersedia di status terminal` (`operatorCan.grantOverride` true pada APPROVED); `[REV-ASSIGN-02] satu reviewer dari minimal dua` (`activeReviewerCount` 1, `minimumReviewers` 2, status `submitted`); `[REV-OUTCOME-10] fase butuh review tanpa reviewer` (`needsReview` true, `activeReviewerCount` 0, status `submitted`); `[REV-REASSIGN-01] reviewer yang digantikan tetap terdaftar` (`reviewers.1.replaced` true); `[REV-REASSIGN-02] evaluasi reviewer yang digantikan tetap terbaca` (`evaluationSheets` memuat entri `replaced` true); `[SM-OWNER-02] member biasa tidak ditawarkan sebagai penerima`; `operatorCan mengikuti reviewers.assign` (view-all tanpa `reviewers.assign` → `assignReviewers` false). Dua test ulir lama diganti: Operator → `missing('canCreateThread')`; penulis ulir → `where('threads.0.reviewer', 'Penilai Ulir')` (`ReviewThreadData::$reviewer`). `ScoreVisibilityTest` tetap hijau.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Submission/OperatorSubmissionDetailTest.php tests/Feature/Review/ReviewThreadCreationTest.php tests/Feature/Review/ReviewThreadAuthorPayloadTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Controller `// BR-SM-07 — view-all; draft belum diajukan, bagi Operator ia belum ada.`
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Feature/Submission` + `tests/Unit/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model detail pengajuan operator`

### Task 4: `Submission/ManageShowPage` (FE) + hapus legacy operator

**Files:**
- Create: `resources/js/Pages/Submission/ManageShowPage.vue`, `resources/js/Components/Submission/Manage/{OperatorActions,ReviewersSection,ReviewTab}.vue`
- Modify: `resources/js/types/submission-manage.ts`, `resources/js/Components/Submission/ScoreSection.vue` (badge "Digantikan" bila `score.replaced`), `quality-legacy.txt`, `quality-budget.txt` (`max.legacy.files` 79 → 73)
- Delete: `resources/js/Pages/Submissions/{IndexPage,ShowPeriod,ShowSubmission}.vue`, `resources/js/Components/{AssignReviewerDialog,ReplaceReviewerDialog,RejectSubmissionDialog,TransferOwnershipDialog,ReviewSystem,ReviewScorePanel,PhaseAccessPanel}.vue`, `tests/frontend/components/reviewSystemResolve.spec.ts` (grep dulu: satu-satunya pengimpor semuanya `ShowSubmission.vue`)
- Test: `tests/frontend/components/Submission/Manage/OperatorActions.spec.ts`, `tests/frontend/pages/ManageShowPage.spec.ts`

**Interfaces:**
- Produces (TS): `OperatorAbilities`, `ManagedReviewer`, `OwnershipCandidate`, `OverrideTarget`, `ManageShowPageProps` (= `Omit<SubmissionShowPageProps, 'targetOutputTypes'>` + kunci Task 3).
- Produces: `type OperatorActionKey = 'assign' | 'extend' | 'transfer' | 'reject' | 'withdraw'`.
- `OperatorActions` props `{ can: OperatorAbilities; hasOwnershipCandidates: boolean }`, emit `select: [OperatorActionKey]`: `ActionPanel` dengan urutan dan hint frame — Tugaskan Reviewer "Tersedia saat status Disubmit." · Perpanjang Tenggat "Jika ada tenggat yang terlewat." · Alihkan Kepemilikan "Perlu anggota tim inti yang aktif." (`available` = `can.transferOwnership && hasOwnershipCandidates`) · Tolak Manual "Tersedia saat status Direview." · Tarik Pengajuan "Tersedia saat status Disetujui.". Di bawahnya `AppBanner` lock "Pengajuan yang sudah Disetujui, Ditolak, atau Ditarik tidak dapat diubah lagi." (`<!-- BR-SM-05, BR-SM-11 -->`).
- `ReviewersSection` props `{ reviewers: ManagedReviewer[]; activeCount: number; minimum: number; canReplace: boolean }`, emit `replace: [reviewerId]`: indikator `"{activeCount} dari minimal {minimum} reviewer"` (`<!-- BR-REV-01 -->`), satu baris per reviewer (nama, NIDN, tipe, `evaluationLabel`; `replaced` → badge "Digantikan" tanpa tombol), tombol "Ganti" bila `canReplace`.
- `ReviewTab` props `{ sheets: ReviewerScore[]; threads: ReviewThread[] }`: `ScoreSection` (note "Belum ada evaluasi yang disubmit.") + `ReviewThreadCard` per ulir dengan `can-reply=false`.
- Halaman: `PageHeader` judul, subjudul "{skema} · Ketua: {leadName} · {organization}", back "Semua Pengajuan" → `admin.submissions.index`, badge status, aksi "Cetak" per `printableDocuments` (RPT-PRINT-01, POST `submissions.print`); `DecisionSection` bila ada; banner `REV-OUTCOME-10` "Pengajuan menunggu penugasan reviewer." bila `needsReview && activeReviewerCount === 0`; banner final "Penarikan bersifat final. Kalau keliru, jalurnya adalah pengajuan baru yang merujuk pengajuan ini." bila status `withdrawn`; tab Informasi · Anggota · Anggaran · Berkas · Review ({activeReviewerCount}) · Riwayat (komponen Submission yang ada, `TargetOutputsSection` baca-saja di Informasi tidak perlu); sisi kanan `OperatorActions`, `ReviewersSection`, "Timeline Status" (3 entri). State `openAction = ref<OperatorActionKey | null>(null)` dan `replacing = ref<number | null>(null)` disiapkan untuk dialog S4-2.

- [ ] **Step 1: Tulis Vitest yang gagal.** `OperatorActions`: `[SM-WITHDRAW-03] tarik tidak tersedia sebelum disetujui` (tombol nonaktif + hint); `alihkan tidak tersedia tanpa co-investigator aktif`; `aksi tersedia memancarkan kuncinya`. `ManageShowPage`: `[REV-ASSIGN-02] indikator 1 dari minimal 2` (`toContain('1 dari minimal 2')`); `[REV-OUTCOME-10] menunggu penugasan reviewer`; `[REV-REASSIGN-01] reviewer digantikan tampil berlabel tanpa tombol ganti`; `[SM-WITHDRAW-05] pengajuan ditarik menampilkan banner final tanpa aksi tersedia`; `[BUD-WHO-04] anggaran tampil baca-saja`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Submission/Manage tests/frontend/pages/ManageShowPage.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces, lalu hapus berkas legacy dan barisnya (`Submissions/IndexPage`, `ShowPeriod`, `ShowSubmission`, `RejectSubmissionDialog`, `AssignReviewerDialog`, `ReviewSystem`) dari `quality-legacy.txt`.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` + `… pest tests/Unit/Architecture tests/Unit/Quality` → PASS (`FrontendRouteNamesTest` hijau: `admin.submissions.period` tak lagi dirujuk).
- [ ] **Step 5: Commit** `feat(fe): halaman detail pengajuan operator`
- [ ] **Step 6: Tutup S4-1** — `composer run quality` + `npm run quality` dibaca hasilnya; cek di peramban (resep browser di memori: daftar → detail → tab Review); S4-1 `☑` di §8; commit `feat(fe): irisan S4-1 daftar dan detail operator selesai`; sarankan session baru untuk S4-2.

---

## S4-2

### Task 5: Alasan aksi operator + otorisasi lewat Policy (BE, W26)

**Files:**
- Modify: `app/Actions/FormSubmission/{TransferSubmissionOwnership,WithdrawSubmission}.php`, `app/Actions/Review/ReplaceReviewer.php`, `app/Http/Controllers/Submission/SubmissionDecisionController.php`, `app/Http/Controllers/SubmissionWithdrawalController.php`, `app/Http/Controllers/Review/ReplaceReviewerController.php`, `app/Http/Controllers/ReviewController.php` (`assignReviewers`), `routes/web.php`, pemanggil di `tests/Feature/Submission/{OwnershipTest,WithdrawTest}.php`, `tests/Feature/Review/{ReplaceReviewerTest,ReviewerSummaryPageTest,ReviewerHistoryPageTest}.php`, `tests/Feature/Http/SubmissionActionsHttpTest.php`, `tests/Feature/EndToEnd/SubmissionReviewFlowTest.php`
- Modify (spec): `docs/requirement-analysis/bdd/core/submission/ownership.feature`, `withdrawal.feature`, `core/review/reassignment.feature`
- Test: `tests/Feature/Submission/OperatorActionReasonTest.php`

**Interfaces:**
- `TransferSubmissionOwnership::handle(FormSubmission $submission, User $successor, string $reason): FormSubmission`; `WithdrawSubmission::handle(FormSubmission $submission, User $operator, string $reason): FormSubmission`; `ReplaceReviewer::handle(FormSubmission $submission, Reviewer $outgoing, Reviewer $incoming, ?string $note): SubmissionReviewer`. Entri audit yang sudah ada diberi `new AuditChange(new: ['reason' => $reason])` / `['note' => $note]` (catatan kosong → tanpa `AuditChange`). Pemanggil produksi hanya tiga controller di atas.
- Validasi controller: `reason` `required|string|min:3|max:2000` (transfer, withdraw), `note` `nullable|string|max:2000` (replace). Otorisasi: transfer `can('transferOwnership')`, replace `can('replaceReviewer')`, assign `can('assignReviewers')` — `abort_unless(…, 403)`.
- Route `admin.submissions.assign-reviewers` keluar dari grup `can:users.manage` ke grup Policy di bawah `routes/web.php:472`. Nama route lain tetap.
- Skenario baru (ditulis persis, frasa baku README):

```gherkin
  # ownership.feature
  @BR-SM-09 @BR-RPT-09 @M1 @OPERATOR-02-04 @denial
  Scenario: [SM-OWNER-05] Pemindahan kepemilikan tanpa alasan ditolak
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari" dengan alasan kosong
    Then pemindahan ditolak dengan error validasi pada field alasan

  @BR-SM-09 @BR-RPT-09 @M1 @OPERATOR-02-04 @happy
  Scenario: [SM-OWNER-06] Alasan pemindahan kepemilikan tercatat di jejak audit
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari" dengan alasan "Ketua pindah institusi"
    Then perubahan tercatat di jejak audit beserta alasan "Ketua pindah institusi"

  # withdrawal.feature
  @BR-SM-11 @BR-RPT-09 @M1 @OPERATOR-02-06 @denial
  Scenario: [SM-WITHDRAW-06] Penarikan tanpa alasan ditolak
    When Operator menarik "P-001" dengan alasan kosong
    Then penarikan ditolak dengan error validasi pada field alasan
    And status "P-001" tetap APPROVED

  @BR-SM-11 @BR-RPT-09 @M1 @OPERATOR-02-06 @happy
  Scenario: [SM-WITHDRAW-07] Alasan penarikan tercatat di jejak audit
    When Operator menarik "P-001" dengan alasan "Duplikasi pendanaan"
    Then status "P-001" menjadi WITHDRAWN
    And perubahan tercatat di jejak audit beserta alasan "Duplikasi pendanaan"

  # reassignment.feature
  @BR-REV-10 @BR-RPT-09 @M2 @OPERATOR-03-02 @boundary
  Scenario: [REV-REASSIGN-04] Penggantian tanpa catatan tetap berhasil
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001" tanpa catatan
    Then penugasan "Sari" pada "P-001" berstatus replaced

  @BR-REV-10 @BR-RPT-09 @M2 @OPERATOR-03-02 @happy
  Scenario: [REV-REASSIGN-05] Catatan penggantian tercatat di jejak audit
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001" dengan catatan "Tidak responsif lebih dari 7 hari"
    Then perubahan tercatat di jejak audit beserta catatan "Tidak responsif lebih dari 7 hari"
```

- Tambah ke tabel kosakata `bdd/README.md`: `When … dengan alasan kosong` / `dengan alasan "<teks>"`, `Then <aksi> ditolak dengan error validasi pada field alasan`, `Then perubahan tercatat di jejak audit beserta alasan|catatan "<teks>"`.

- [ ] **Step 1: Tulis skenario + test yang gagal.** `OperatorActionReasonTest.php` lewat HTTP, satu test per ID di atas (`assertSessionHasErrors('reason')`; `AuditEntry` terbaru `properties->new->reason` = teks); plus `penugasan tanpa reviewers.assign ditolak` (view-all saja → POST `admin.submissions.assign-reviewers` 403), `pemegang reviewers.assign tanpa users.manage bisa menugaskan` (302, tanpa galat), `penggantian tanpa reviewers.assign ditolak` (403).
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Submission/OperatorActionReasonTest.php tests/Unit/Bdd` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces; perbarui semua pemanggil lama dengan alasan contoh.
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Submission` + `tests/Feature/Review` + `tests/Feature/Http` + `tests/Feature/EndToEnd` + `tests/Feature/Audit` → PASS (`BddCoverageTest` hijau, `pending.txt` tetap kosong).
- [ ] **Step 5: Commit** `feat(be): alasan aksi operator tercatat di audit`

### Task 6: Override membuka kembali evaluasi yang lewat tenggat (BE, W25)

**Files:**
- Modify: `app/Models/ReviewerFormAssignment.php`, `app/Policies/ReviewerFormAssignmentPolicy.php`, `app/Queries/Review/ReviewerEvaluation.php`, `app/Queries/Submission/OperatorSubmissionDetail.php` (`overrideTarget.affectedReviewers` memakai aturan yang sama), `docs/requirement-analysis/bdd/generic/form_engine/phase_window.feature`, `docs/requirement-analysis/bdd/README.md`
- Test: `tests/Feature/Review/EvaluationOverrideTest.php`

**Interfaces:**
- Produces: `ReviewerFormAssignment::isClosedByDeadline(): bool` = `isOverdue()` dan **tidak** ada `PhaseAccessGate::activeOverride($submission, $form->formPhaseDetail)` — detailnya detail pemilik form evaluasi (`review_evaluation_forms.form_phase_detail_id`), jadi BR-FE-10 tetap: override detail lain tak berlaku. Komentar `// BR-FE-17, BR-FE-08 — tenggat evaluasi bisa dibuka kembali hanya lewat override detail pemiliknya (ADR 0014, W25).`
- Policy `evaluate` dan `ReviewerEvaluation::state()` memakai `isClosedByDeadline()` menggantikan `isOverdue()`.
- Skenario baru:

```gherkin
  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @happy
  Scenario: [FE-WINDOW-05] Override yang berlaku membuka kembali evaluasi yang lewat batas
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then detail terbuka

  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-06] Override yang kedaluwarsa tidak membuka kembali evaluasi
    Given waktu sekarang "2026-04-02 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-10 @BR-FE-17 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-07] Override fase lain tidak membuka kembali evaluasi
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Laporan Monev I" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403
```

- Tambah ke kosakata README: `Given form evaluasi "<orang>" melekat pada detail "<detail>"` — form evaluasi reviewer hidup di detail tahap pengajuannya, bukan detail tersendiri.

- [ ] **Step 1: Tulis skenario + test yang gagal.** `EvaluationOverrideTest.php`: tiga ID di atas lewat HTTP `reviewer.evaluation-form.save-draft` (200/302 vs 403) dan payload `reviewer.assignments.evaluation` (`forms.0.state` `draft` vs `overdue`); `override yang dicabut tidak membuka kembali evaluasi` (FE-OVERRIDE-05 untuk evaluasi). `ReviewerEvaluationPageTest` `[FE-WINDOW-02]` tetap hijau.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Review/EvaluationOverrideTest.php tests/Feature/Review/ReviewerEvaluationPageTest.php tests/Unit/Bdd` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Feature/FormEngine` → PASS.
- [ ] **Step 5: Commit** `feat(be): override membuka kembali evaluasi lewat tenggat`

### Task 7: Dialog tolak, tarik, alihkan + `ConfirmDialog` terkunci saat proses (FE)

**Files:**
- Create: `resources/js/Components/Submission/Manage/{RejectDialog,WithdrawDialog,TransferOwnershipDialog,ReasonField}.vue`
- Modify: `resources/js/Components/app/ConfirmDialog.vue`, `resources/js/Pages/Submission/ManageShowPage.vue`
- Test: `tests/frontend/components/app/ConfirmDialog.spec.ts` (tambah), `tests/frontend/components/Submission/Manage/DecisionDialogs.spec.ts`

**Interfaces:**
- `ConfirmDialog`: selama `processing`, "Batal" nonaktif dan `update:open` ke `false` diabaikan (tutup lewat Esc/klik luar juga ditahan).
- `ReasonField` props `{ id: string; label: string; placeholder: string; error?: string; required?: boolean }`, `v-model` string: `FormField` + `Textarea`. Dipakai ketiga dialog dan Task 8/10 (cegah blok duplikat).
- Ketiga dialog: `v-model:open`, props `{ submission: SubmissionDetail; … }`, `useForm`, `onSuccess` menutup dialog, galat dari `form.errors.*` tampil di field-nya dan dialog tetap terbuka.
  - `RejectDialog` (+ prop `activeReviewerCount`): banner "**Aksi ini tidak dapat dibatalkan.** Gunakan setelah meninjau hasil evaluasi reviewer, atau untuk kondisi khusus seperti plagiarisme dan pelanggaran administratif."; ringkasan (Judul, Ketua, Status Saat Ini, "Reviewer Aktif: {n} — evaluasi akan dihentikan"); `ReasonField` "Penjelasan Detail" (placeholder "Dikirimkan ke researcher via notifikasi — tulis dengan jelas.") → `reason`; "Tolak Pengajuan" membuka `ConfirmDialog` destruktif ("Pengajuan yang ditolak tidak dapat diaktifkan kembali.") lalu POST `admin.submissions.reject`. `<!-- BR-REV-07 -->`
  - `WithdrawDialog`: banner "**Penarikan pengajuan yang sudah disetujui.** Monev dan pelaporan luaran untuk penelitian ini akan dihentikan."; `ReasonField` "Alasan" (placeholder "Misal: permintaan researcher, duplikasi pendanaan, force majeure.") → `reason`; konfirmasi kedua ("Data penelitian tetap tersimpan sebagai arsip baca-saja.") lalu POST `admin.submissions.withdraw`. `<!-- BR-SM-11 -->`
  - `TransferOwnershipDialog` (+ prop `candidates: OwnershipCandidate[]`): "Kepemilikan Saat Ini" (Ketua, Anggota Inti Aktif); `OptionSelect` "Ketua Baru" ("{name} — {organization}", hint "Hanya anggota inti yang aktif yang dapat dipilih.") → `successor_id`; `ReasonField` "Alasan Pengalihan" → `reason`; banner "Setelah pengalihan, peran ketua pengaju berpindah — ketua lama tetap menjadi anggota tim. Aksi ini tercatat di riwayat sistem."; POST `admin.submissions.transfer-ownership`. `<!-- BR-SM-09, BR-IAM-10 -->`
- Halaman memasang dialog ini pada `openAction` `reject|withdraw|transfer`.

- [ ] **Step 1: Tulis Vitest yang gagal.** `ConfirmDialog`: `tidak bisa ditutup selama proses`. `DecisionDialogs`: `[REV-OUTCOME-07] tolak mengirim alasan setelah konfirmasi kedua`; `[SM-WITHDRAW-01] tarik mengirim alasan setelah konfirmasi kedua`; `[SM-WITHDRAW-06] alasan penarikan kosong ditampilkan sebagai galat field`; `[SM-OWNER-01] alihkan mengirim penerima dan alasan`; `[SM-OWNER-02] galat penerima tampil di dialog yang tetap terbuka`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/app/ConfirmDialog.spec.ts tests/frontend/components/Submission/Manage` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): dialog tolak, tarik, dan alihkan kepemilikan`

### Task 8: Dialog perpanjang tenggat (FE)

**Files:**
- Create: `resources/js/Components/Submission/Manage/ExtendDeadlineDialog.vue`
- Modify: `resources/js/Pages/Submission/ManageShowPage.vue`
- Test: `tests/frontend/components/Submission/Manage/ExtendDeadlineDialog.spec.ts`

**Interfaces:**
- Props `{ submissionId: number; phaseAccess: PhaseAccess; target: OverrideTarget }`, `v-model:open`.
- "Tenggat Saat Ini": Fase (`target.phaseLabel`), Tenggat Awal (`formatDate(phaseAccess.deadline)` + " — terlewat" bila `!phaseAccess.is_open`), Reviewer Terdampak (`target.affectedReviewers` dipisah koma; kosong → baris disembunyikan).
- Override yang ada: status "Aktif sampai {tanggal}" / "Kedaluwarsa", alasannya (Operator boleh membaca, BR-FE-16), tombol "Cabut" → `ConfirmDialog` → DELETE `admin.submissions.overrides.destroy` (FE-OVERRIDE-05).
- Form: `Input type="date"` "Tanggal Tenggat Baru" (wajib, `min` = besok) → dikirim `expires_at: "{tanggal} 23:59:59"` (MON-CYCLE-09/RO-OUTPUT-16 berlaku sampai akhir hari); `ReasonField` "Alasan Perpanjangan" (placeholder "Wajib diisi — tercatat di riwayat sistem.") → `reason`; `form_phase_detail_id` = `phaseAccess.form_phase_detail_id`; POST `admin.submissions.overrides.store`. Banner "Perpanjangan tercatat di riwayat sistem beserta alasan dan operator yang melakukannya." `<!-- BR-FE-08, BR-FE-09, BR-FE-10 -->`
- Tanpa `form_phase_detail_id` (data lama) → `AppBanner` "Pengajuan ini tidak tertaut ke tahap mana pun, jadi tidak ada tenggat untuk diperpanjang." tanpa form.

- [ ] **Step 1: Tulis Vitest yang gagal.** `[FE-OVERRIDE-03] perpanjangan dikirim berlaku sampai akhir hari` (payload `expires_at` berakhiran `23:59:59`); `[FE-OVERRIDE-06] alasan kosong tampil sebagai galat field`; `[FE-OVERRIDE-05] override aktif bisa dicabut lewat konfirmasi`; `[FE-WINDOW-05] reviewer terdampak ditampilkan`; `tanpa tahap tidak menawarkan form`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Submission/Manage/ExtendDeadlineDialog.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): dialog perpanjang tenggat`

### Task 9: Kandidat reviewer (BE)

**Files:**
- Create: `app/Queries/Review/ReviewerCandidates.php`, `app/Data/View/Submission/Manage/ReviewerCandidateData.php`
- Modify: `app/Support/ReviewerWorkload.php` (`limitFor` jadi `public static`), `app/Http/Controllers/Submission/OperatorSubmissionController.php` (`show`)
- Test: `tests/Feature/Review/ReviewerCandidatesTest.php`

**Interfaces:**
- Produces: `ReviewerCandidateData(int $reviewerId, string $name, ?string $nidn, string $type, int $activeLoad, bool $overloaded, ?string $blockedReason)` — `blockedReason`: "Konflik kepentingan" (`ConflictOfInterestGuard::conflicts`), "Sudah ditugaskan pada siklus ini" (ada baris `SubmissionReviewer` apa pun statusnya, sama dengan guard BR-REV-03), selain itu null.
- Produces: `ReviewerCandidates::run(FormSubmission $submission, string $search): list<ReviewerCandidateData>` — `ReviewersWithinTenure::run()` (REV-ASSIGN-08: di luar masa tugas tidak muncul), user aktif saja (BR-IAM-05), `whereLike` nama atau NIDN, maks 20, urut nama; `activeLoad` lewat `withCount` penugasan `active`; `overloaded` = batas `ReviewerWorkload::limitFor` ada dan `activeLoad >= batas`; tanpa N+1.
- `show` menambah prop `reviewerCandidates` = `Inertia::optional(fn () => ReviewerCandidates::run($submission, $request->string('reviewer_search')->trim()->toString()))` — tidak dikirim saat kunjungan biasa.

- [ ] **Step 1: Tulis test yang gagal.** `kandidat tidak dikirim tanpa partial reload` (`missing('reviewerCandidates')`); `partial reload mengirim kandidat` (header `X-Inertia-Partial-Data: reviewerCandidates`); `[REV-ASSIGN-04] ketua pengaju ditandai konflik kepentingan`; `[REV-ASSIGN-07] co-investigator ditandai konflik kepentingan`; `[REV-ASSIGN-05] reviewer yang sudah ditugaskan ditandai`; `[REV-ASSIGN-06] beban di batas skema ditandai tinggi tapi tidak diblok` (`overloaded` true, `blockedReason` null); `[REV-ASSIGN-08] reviewer di luar masa tugas tidak ditawarkan`; `pencarian NIDN tidak peka huruf`.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Review/ReviewerCandidatesTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Unit/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): kandidat reviewer untuk penugasan operator`

### Task 10: Dialog tugaskan & ganti reviewer (FE)

**Files:**
- Create: `resources/js/Components/Submission/Manage/{ReviewerPicker,AssignReviewersDialog,ReplaceReviewerDialog}.vue`, `resources/js/composables/useReviewerCandidates.ts`
- Modify: `resources/js/types/submission-manage.ts` (`ReviewerCandidate`, `reviewerCandidates?: ReviewerCandidate[]`), `resources/js/Pages/Submission/ManageShowPage.vue`
- Test: `tests/frontend/composables/useReviewerCandidates.spec.ts`, `tests/frontend/components/Submission/Manage/ReviewerDialogs.spec.ts`

**Interfaces:**
- `useReviewerCandidates()` → `{ search: Ref<string>, load(): void }`: `router.reload({ only: ['reviewerCandidates'], data: { reviewer_search } })` dengan debounce 300 ms, dipanggil saat dialog dibuka dan saat `search` berubah.
- `ReviewerPicker` props `{ candidates: ReviewerCandidate[]; selected: number[]; multiple: boolean }`, emit `toggle: [reviewerId]`: per baris nama, "NIDN {nidn} · {Internal|Eksternal} · Workload: {activeLoad} aktif"; `overloaded` → badge "Beban Tinggi" dan tetap bisa dipilih (`<!-- BR-REV-04 -->`); `blockedReason` → badge berisi alasannya, tombol nonaktif (`<!-- BR-REV-02, BR-REV-03 -->`).
- `AssignReviewersDialog` props `{ submissionId; activeCount; minimum; candidates? }`: `Input` "Cari nama / NIDN reviewer...", `ReviewerPicker` multiple, kartu "{activeCount + selected.length} dari minimal {minimum} reviewer" + "Status pengajuan menjadi Direview setelah jumlah minimum terpenuhi."; "Tugaskan" aktif bila ≥ 1 dipilih (penugasan bertahap, REV-ASSIGN-01 — selisih frame dicatat W27) → POST `admin.submissions.assign-reviewers` `{ reviewer_ids, auto_assign_forms: true }`; galat `errors.reviewer_id` tampil, dialog tetap terbuka.
- `ReplaceReviewerDialog` props `{ submissionId; outgoing: ManagedReviewer; candidates? }`: "Reviewer Lama" (nama, NIDN, `evaluationLabel`, "Riwayat reviewer lama tetap tersimpan dan akan ditampilkan dengan label "Digantikan"."), `ReviewerPicker` tunggal (yang diblok disembunyikan), `ReasonField` "Catatan Penggantian" tidak wajib (placeholder "Opsional — tercatat di riwayat sistem.") → `note`; POST `admin.submissions.replace-reviewer` `[submissionId, outgoing.reviewerId]` `{ incoming_reviewer_id, note }`. `<!-- BR-REV-10, BR-REV-15 -->`
- Halaman: `flash.workloadWarnings` (shared prop yang sudah ada) → `AppBanner` tone warning per peringatan di atas tab (REV-ASSIGN-06).

- [ ] **Step 1: Tulis Vitest yang gagal.** `useReviewerCandidates`: `memuat kandidat lewat partial reload`, `pencarian di-debounce`. `ReviewerDialogs`: `[REV-ASSIGN-01] satu reviewer sudah bisa ditugaskan`; `[REV-ASSIGN-02] indikator menghitung reviewer aktif dan pilihan` (`toContain('1 dari minimal 2')` sebelum memilih); `[REV-ASSIGN-04] kandidat konflik tidak bisa dipilih`; `[REV-ASSIGN-06] kandidat berbeban tinggi tetap bisa dipilih`; `galat penugasan tampil dan dialog tetap terbuka`; `[REV-REASSIGN-04] ganti tanpa catatan mengirim note kosong`; `[REV-REASSIGN-05] ganti mengirim catatan`. `ManageShowPage`: `[REV-ASSIGN-06] peringatan beban dari flash ditampilkan`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/composables/useReviewerCandidates.spec.ts tests/frontend/components/Submission/Manage tests/frontend/pages/ManageShowPage.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): dialog tugaskan dan ganti reviewer`

### Task 11: Smoke Playwright + tutup irisan

**Files:**
- Modify: `tests/e2e/smoke.spec.ts`, `docs/design/fe-v2-design.md` (§8, §9)

**Interfaces:**
- Smoke (§5, alur kritis S4), data dari `EndToEndFlowSeeder`: Operator login → Semua Pengajuan → detail → Tugaskan dua reviewer → status "Direview" → reviewer login → Form Evaluasi → Submit Evaluasi → Operator → Tolak Manual dengan alasan → status "Ditolak". Dijalankan dengan resep browser di memori (server `php -S` dari `public/`, `SESSION_DRIVER=file`, chromium lokal tanpa sandbox).
- §9 ditambah: **W25**, **W26** (keputusan pemilik di atas); **W27** — 03-01 menggambar "Hapus" reviewer dan "Konfirmasi" yang menunggu minimum: tidak ada jalur menghapus penugasan (`RemoveReviewerEndpointRemovedTest`, BR-REV-10 → ganti), dan penugasan bertahap (REV-ASSIGN-01/02) sehingga tombol aktif sejak satu dipilih, indikator tetap tampil; **W28** — 02-01 filter "Organisasi" dan "Tanggal: 30 hari" tidak dibangun, daftar hanya pengajuan induk terkirim; **W29** — 02-06 "Siklus Monev Aktif"/"Luaran Terdaftar" di ringkasan penarikan tidak digambar; **W30** — frame 02-03..06/03-01/03-02 berupa halaman, dibangun sebagai dialog (§8); **W31** — 02-02 tidak menggambar aksi sunting untuk Super Admin (SM-SUBMIT-14 "bisa menyunting isi" tetap terikat di Policy/Pest; wizard Super Admin masih 409, catatan S2b). W22 ditutup: Operator membaca lembar evaluasi di tab Review. W24 tetap terbuka.
- §8: S4-1 dan S4-2 `☑`; `SubmissionEvaluationManager/*` dan `ReviewerAssignments/*` dipindah ke kolom legacy F99.

- [ ] **Step 1: Tulis smoke** sesuai Interfaces.
- [ ] **Step 2: Jalankan** `npx playwright test tests/e2e/smoke.spec.ts` dengan server resep → PASS (kalau environment tidak memungkinkan, **laporkan**, jangan dilewati diam-diam).
- [ ] **Step 3: Gate penuh** `composer run quality` + `npm run quality` → baca hasilnya, laporkan apa adanya.
- [ ] **Step 4: Perbarui** §8/§9 sesuai Interfaces.
- [ ] **Step 5: Commit** `feat(fe): irisan S4 pengajuan sisi operator selesai`; sarankan session baru, irisan berikutnya A1 (D1 menunggu A1).
