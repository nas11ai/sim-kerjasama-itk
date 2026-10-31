# FE v2 — Irisan S2c: Revisi, Target Luaran, Riwayat — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Peneliti membaca catatan reviewer, membalas ulirnya, dan mengirim ulang revisi dari `Submission/RevisionPage` (RESEARCHER-02-05); ketua pengaju menambah/mengubah/menghapus target luaran dari tab "Target Luaran" detail pengajuan beserta jejak perubahannya (RESEARCHER-02-11); tab "Riwayat" detail menjadi RESEARCHER-07-01 dengan penjelasan cakupannya.

**Architecture:** Hanya satu halaman baru, `Submission/RevisionPage`, di route `user.submissions.revision` yang props-nya disusun Query `SubmissionRevision`. Frame 02-11 dan 07-01 menggambar **tab detail pengajuan** (wireframe-nya sendiri memakai tab Informasi…Riwayat), jadi keduanya dibangun di `Submission/ShowPage` yang sudah ada, bukan `TargetOutputPage`/`AuditPage` tersendiri (W17). Entri audit target luaran pindah ke subjek pengajuan (aggregate root), sehingga `SubmissionHistory` dan `AuditScope` (BR-RPT-05) otomatis menjangkaunya; penyuntingan target yang selama ini hanya masuk log `default` ikut dicatat `AuditTrail` (W19).

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest.

**Spec:** `docs/design/fe-v2-design.md` (§4.1–§4.6, §6, baris S2c di §8, W9/W11/W12 di §9). Frame: `../simpas-wireframe/screens/RESEARCHER-02-{05,11}/screen.js`, `RESEARCHER-07-01/screen.js`; `FRAME-AUDIT.md`/`STATE-COVERAGE.md` bagian yang sama. BR: BR-SM-02/03/04 (resubmit), BR-REV-06/15 (ulir), BR-SM-15/19 (target), BR-RPT-05/09 (audit).

**Satu session.** Lima task; tidak ada legacy yang terhapus (sisa `User/*` adalah dasbor, biodata, pengumuman — milik D1/P1/A1; `ReviewSystem.vue` masih dipakai `Submissions/ShowSubmission` operator sampai S4), jadi `quality-legacy.txt` dan `max.legacy.files` tidak berubah.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris; tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy (`ReviewSystem.vue` jangan disalin — tulis dari wireframe).
- Baris pertama `<script setup>` halaman baru: `// Frame: RESEARCHER-02-05`. `ShowPage.vue` menjadi `// Frame: RESEARCHER-02-03, RESEARCHER-02-11, RESEARCHER-02-12, RESEARCHER-07-01`.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris; kosakata `02_ubiquitous_language.md` ("Ketua", "Co-Investigator", "Ulir").
- TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- Otorisasi tombol dibaca dari `can` (ADR 0015); frontend tidak menyusun ulang aturan. `viewerRole` dan key status hanya memilih **teks** banner.
- Mutasi lewat `router`/`useForm`, tanpa `axios`; tanpa dependency baru.
- Test yang mengikat skenario BDD menyebut ID-nya. Skenario yang dipakai sudah dicakup Pest, jadi `pending.txt`/`uncovered.txt` tidak berubah.
- Gate: `composer run quality` + `npm run quality`. Pest di container:
  `docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Kembali ke halaman revisi setelah Kirim Ulang.** `ResubmitController` menjawab `back()`, jadi pengguna mendarat lagi di `/revision` pada pengajuan yang kini UnderReview; ia harus dialihkan ke detail, bukan 403 atau halaman kosong. → Task 3 test `pengajuan yang bukan NEEDS_REVISION dialihkan ke detail`.
2. **Co-Investigator membuka halaman revisi**: membaca ulir, tetapi tanpa kotak balas dan tanpa tombol Kirim Ulang (BR-SM-04; `AddReviewComment` hanya menerima ketua). → Task 3 test `can` untuk anggota, Task 4 Vitest `[SM-SUBMIT-13] …`.
3. **Target yang sudah dihapus tetap ada di jejaknya.** Jejak yang diambil dari id target yang masih ada akan kehilangan penghapusan. → Task 1 test `jejak target memuat target yang sudah dihapus`.
4. **Reviewer yang ditugaskan lolos BR-SM-07 tetapi tidak boleh membaca jejak target** (BR-RPT-05). → Task 1 test `[RPT-AUDIT-05] reviewer tidak membaca jejak target`.
5. **Peneliti lain membuka `/revision` pengajuan orang** dijawab 403 (BR-SM-07), bukan dialihkan ke detail lebih dulu. → Task 3 test `[SM-ACCESS-03] peneliti lain ditolak di halaman revisi`.

---

### Task 1: Audit target luaran pada pengajuan + payload tab target

**Files:**
- Modify: `app/Actions/FormSubmission/SaveTargetOutput.php`, `app/Actions/FormSubmission/DeleteTargetOutput.php`, `app/Data/Submission/SubmissionAbilitiesData.php`, `app/Queries/Submission/ResearcherSubmissionDetail.php`
- Create: `app/Queries/Submission/TargetOutputHistory.php`, `app/Data/View/Submission/TargetOutputChangeData.php`
- Test: `tests/Feature/Submission/TargetOutputTest.php` (tambah), `tests/Feature/Submission/TargetOutputHistoryTest.php`, `tests/Feature/Submission/SubmissionShowPageTest.php` (tambah)

**Interfaces:**
- Produces: konstanta `SaveTargetOutput::AUDIT_CREATED = 'Target luaran disimpan'`, `SaveTargetOutput::AUDIT_UPDATED = 'Target luaran diubah'`, `DeleteTargetOutput::AUDIT_DESCRIPTION = 'Target luaran dihapus'`.
- Produces: `TargetOutputChangeData(string $description, ?string $actor, ?string $at, ?string $from, ?string $to)` — `from`/`to` adalah **nama** tipe (`OutputTypeDefinition::name`, jatuh ke kode bila tak dikenal).
- Produces: `TargetOutputHistory::run(User $viewer, FormSubmission $submission): list<TargetOutputChangeData>` — terbaru dulu.
- Produces: `SubmissionAbilitiesData` + `bool $manageTargetOutput` (Policy `manageTargetOutput`). `discussReview` baru ditambahkan di Task 3.
- Produces: props `Submission/ShowPage` baru: `targetOutputTypes: list<array{value: string, label: string}>` (dari `TargetOutputTypeChoices`, **kosong** bila `!can.manageTargetOutput`) dan `targetHistory: list<TargetOutputChangeData>`.

- [ ] **Step 1: Tulis test yang gagal.** Di `TargetOutputTest.php`: `[SM-TARGET-09] mengubah target dicatat di jejak audit pengajuan dengan nilai lama dan baru` — `SaveTargetOutput::run($submission, $owner, 'haki', $target)` → `AuditEntry::query()->auditOnly()->where('subject_type', FormSubmission::class)->where('subject_id', $submission->id)->where('description', 'Target luaran diubah')` ada, `properties['old']['output_type'] === 'publikasi'`, `properties['new']['output_type'] === 'haki'`. Test `[SM-TARGET-09]` lama (log `default` pada subjek target) **tetap** lulus. Di `TargetOutputHistoryTest.php`: `jejak target memuat tambah, ubah, dan hapus dengan nama tipe` (tiga entri, terbaru dulu, `from`/`to` berupa nama), `jejak target memuat target yang sudah dihapus`, `[RPT-AUDIT-05] co-investigator membaca jejak target`, `[RPT-AUDIT-05] reviewer tidak membaca jejak target` (reviewer aktif → `[]`), `tidak memuat entri audit pengajuan lain` (mis. "Pengajuan disubmit"). Di `SubmissionShowPageTest.php`: pemilik → `has('targetHistory')`, `where('can.manageTargetOutput', true)`, `targetOutputTypes` tidak kosong; co-investigator → `where('can.manageTargetOutput', false)`, `where('targetOutputTypes', [])`.
- [ ] **Step 2: Jalankan, pastikan gagal.** `… ./vendor/bin/pest tests/Feature/Submission/TargetOutputTest.php tests/Feature/Submission/TargetOutputHistoryTest.php tests/Feature/Submission/SubmissionShowPageTest.php` → FAIL (konstanta/Query/props belum ada).
- [ ] **Step 3: Implementasi.**
  - Ketiga entri `AuditTrail::record` target memakai subjek **`$submission`** (bukan `$target`), dengan `AuditChange`: tambah `new: ['output_type' => …]`; ubah `old`+`new` (cabang ubah sebelumnya tanpa audit — BR-SM-15, BR-RPT-09); hapus `old` (sudah ada). Komentar `// BR-SM-15, BR-RPT-05 — target adalah bagian aggregate pengajuan; jejaknya ikut terbaca tim lewat AuditScope.`
  - `TargetOutputHistory`: `AuditScope::forUser($viewer)` + `subject_type`/`subject_id` pengajuan + `whereIn('description', [tiga konstanta])`, urut `created_at`, `id` menurun; satu kueri `OutputTypeDefinition` untuk peta kode → nama (pola `SubmissionTargetOutputs`).
  - `ResearcherSubmissionDetail::handle` menambah `targetOutputTypes` dan `targetHistory`; ubah `@phpstan-type Detail`.
- [ ] **Step 4: Jalankan test Step 2 + `tests/Feature/Audit` + `tests/Architecture`** → PASS (AuditCoverageTest tetap hijau).
- [ ] **Step 5: Commit** `feat(be): jejak target luaran di audit pengajuan`

### Task 2: Tab Target Luaran yang bisa disunting + tab Riwayat (FE)

**Files:**
- Modify: `resources/js/types/submission-page.ts` (`SubmissionAbilities` + `manageTargetOutput: boolean`; `TargetOutputChange`; `SubmissionShowPageProps` + `targetOutputTypes: FilterOption[]`, `targetHistory: TargetOutputChange[]`), `resources/js/Components/Submission/Wizard/TargetOutputEditor.vue`, `resources/js/Components/Submission/TargetOutputsSection.vue`, `resources/js/Pages/Submission/ShowPage.vue`
- Test: `tests/frontend/components/Submission/TargetOutputsSection.spec.ts`, `tests/frontend/components/Submission/Wizard/TargetOutputEditor.spec.ts` (tambah); fixture `can` di spec lain yang membangun `SubmissionAbilities` ikut diberi dua field baru

**Interfaces:**
- Consumes: props Task 1.
- Produces: `TargetOutputsSection` props `{ submission: SubmissionDetail; targetOutputs: SubmissionTargetOutput[]; types: FilterOption[]; history: TargetOutputChange[]; canManage: boolean }`.
- `TargetOutputEditor` tetap dipakai `SchemeStep`; tambahannya: tombol "Ubah" per baris → `Select` tipe + "Simpan" → `router.put(route('user.submissions.target-outputs.update', [submissionId, id]), { output_type }, { preserveScroll: true })`. Karena `SubmissionTargetOutput.type` adalah **nama**, nilai awal `Select` dicari dari `types` dengan `label === type` (kosong bila tak ketemu).

- [ ] **Step 1: Tulis Vitest yang gagal.** `TargetOutputEditor`: `mengubah tipe target lewat target-outputs.update`. `TargetOutputsSection`: `[SM-TARGET-06] ketua pengaju menyunting target lewat editor` (`canManage` → editor dirender); `[SM-TARGET-08] co-investigator hanya membaca, dengan banner ketua pengaju` (teks "Hanya ketua pengaju yang dapat mengubah target luaran."); `[SM-TARGET-12] target terkunci saat pengajuan ditolak` (ketua, status `rejected`, `canManage=false` → "Target luaran terkunci karena pengajuan ditolak atau ditarik."); `[SM-TARGET-09] jejak perubahan menampilkan nilai lama dan baru` ("Publikasi → HKI"); `jejak kosong menampilkan keadaan kosong`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Submission` → FAIL.
- [ ] **Step 3: Implementasi.**
  - `TargetOutputsSection`: `canManage` → `TargetOutputEditor`; selain itu daftar baca-saja yang sudah ada + satu `AppBanner` (lock): status `rejected`/`withdrawn` → teks SM-TARGET-12, `viewerRole` bukan `lead_researcher` → teks SM-TARGET-08/13. Ketua dengan status `approved` mendapat banner info frame: "Target luaran masih dapat diubah meski pengajuan sudah disetujui — target menyesuaikan jalannya kegiatan." (`<!-- BR-SM-15 -->`). Di bawahnya `SectionCard "Jejak Perubahan Target"`: deskripsi, `from → to` bila ada, `formatDate(at) · actor` (pola `HistoryTimeline`, tanpa menyalin bloknya — boleh mengekstrak satu `TimelineItem` bila jadi duplikat).
  - `ShowPage` tab `history`: `AppBanner` lock "Riwayat ini mencatat seluruh aktivitas pada pengajuan Anda. Hanya Anda dan tim pengajuan yang dapat melihatnya." bila `viewerRole !== 'viewer'` (`<!-- BR-RPT-05 -->`); komentar frame diperbarui.
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend` + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 5: Commit** `feat(fe): tab target luaran dan riwayat detail pengajuan`

### Task 3: Read model & route halaman revisi (BE)

**Files:**
- Create: `app/Http/Controllers/Submission/SubmissionRevisionController.php`, `app/Queries/Submission/SubmissionRevision.php`, `app/Data/View/Submission/Revision/{RevisionSubmissionData,ReviewThreadData,ReviewCommentData,ReviewAttachmentData}.php`
- Modify: `routes/web.php` (grup `check_biodata`, setelah route `submissions.submit`), `app/Policies/FormSubmissionPolicy.php`, `app/Actions/Review/AddReviewComment.php`, `app/Data/Submission/SubmissionAbilitiesData.php`, `app/Queries/Submission/ResearcherSubmissionDetail.php` (`editUrl`), `tests/Feature/Review/ReviewThreadAuthorPayloadTest.php`
- Test: `tests/Feature/Submission/SubmissionRevisionPageTest.php`, `tests/Feature/Submission/ResearcherSubmissionDetailTest.php` (ubah `editUrl menaut ke wizard`)

**Interfaces:**
- Produces: route `GET /user/submissions/{submission}/revision` → `user.submissions.revision`.
- Produces: `FormSubmissionPolicy::discussReview(User, FormSubmission): bool` = `submitted_by === $user->id`. `AddReviewComment::authorize` memakai `$author->can('users.manage') || $author->can('discussReview', $submission)` di tempat perbandingan `submitted_by` sekarang (BR-REV-06; satu sumber untuk tombol balas, ADR 0015). `SubmissionAbilitiesData` + `bool $discussReview`.
- Produces: `RevisionSubmissionData(int $id, string $title, SubmissionStatusData $status)`; `ReviewAttachmentData(string $name, string $url)` (`url` = `route('review-attachments.download', ['path' => $file_path])`, `name` = `basename`); `ReviewCommentData(int $id, string $author, string $body, ?string $at, list<ReviewCommentData> $replies, list<ReviewAttachmentData> $attachments)`; `ReviewThreadData(int $id, string $reviewer, bool $isOpen, ?string $notes, ?string $at, list<ReviewAttachmentData> $attachments, list<ReviewCommentData> $comments)` — `reviewer` = nama user penulis asli (`reviewer.user.name`, fallback `'Reviewer'`); penulis komentar = `reviewer.user.name` bila `reviewer_id`, selain itu `user.name`.
- Produces: `SubmissionRevision::run(User $viewer, FormSubmission $submission): array{submission: RevisionSubmissionData, threads: list<ReviewThreadData>, missingFields: list<WizardMissingFieldData>, accountBlock: string|null, can: SubmissionAbilitiesData}` — ulir dari `ReviewThreads::run` (komentar dikelompokkan per `review_summary_id`, terlama dulu di dalam ulir); `missingFields`/`accountBlock` dari `WizardReviewSummary::run` (jangkar `created_at` yang sama, SM-SUBMIT-19).
- Controller: `abort_unless($submission->isAccessibleBy($user), 403)` **lebih dulu**, lalu bukan `NeedsRevision` → `redirect()->route('user.submissions.show', $submission)`, lalu `Inertia::render('Submission/RevisionPage', SubmissionRevision::run(...))`.
- `ResearcherSubmissionDetail::editUrl`: `NeedsRevision` → `route('user.submissions.revision', id)`; Draft tetap wizard.

- [ ] **Step 1: Tulis test yang gagal.** `SubmissionRevisionPageTest.php`: `ketua membuka Submission/RevisionPage` (`component`, `has('threads', 1)`, `where('threads.0.reviewer', 'Penilai Ulir')`, `where('threads.0.isOpen', true)`, `where('can.resubmit', true)`, `where('can.discussReview', true)`, `has('missingFields')`); `[REV-DISCUSS-02] balasan ketua tampil di ulir yang sama` (POST `review-comments.store` lalu GET → `threads.0.comments.0.body`); `[REV-DISCUSS-06] penulis ulir tetap reviewer asli setelah diganti` (`SubmissionReviewer` asli `status = replaced`, pengganti aktif → `threads.0.reviewer` tetap nama asli); `co-investigator membaca tanpa hak balas dan kirim ulang` (`can.discussReview`/`can.resubmit` false); `[SM-SUBMIT-19] field wajib kosong dikirim sebagai missingFields`; `pengajuan yang bukan NEEDS_REVISION dialihkan ke detail` (`assertRedirect(route('user.submissions.show', …))`); `[SM-ACCESS-03] peneliti lain ditolak di halaman revisi` (403). `ReviewThreadAuthorPayloadTest`: test pertama (level Query) diganti test HTTP ke `user.submissions.revision` yang mengikat `threads.0.reviewer` (ulir dibuat dengan submission berstatus `NeedsRevision`); komentar "lahir di S2c" dihapus. `ResearcherSubmissionDetailTest`: `editUrl` Draft → wizard, NeedsRevision → revision. Fixture: pakai/ekstrak `threadAuthorFixture` bila perlu, tanpa menduplikasi.
- [ ] **Step 2: Jalankan** `… pest tests/Feature/Submission/SubmissionRevisionPageTest.php tests/Feature/Review tests/Feature/Submission/ResearcherSubmissionDetailTest.php` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Komentar BR: controller `// BR-SM-07 …`, Policy `// BR-REV-06 — dari pihak peneliti hanya ketua pengaju yang berdiskusi; ia juga yang mengirim ulang (BR-SM-04).`
- [ ] **Step 4: Jalankan** test Step 2 + `tests/Feature/Review` + `tests/Architecture` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model halaman revisi pengajuan`

### Task 4: `Submission/RevisionPage` (FE)

**Files:**
- Create: `resources/js/types/submission-revision.ts`, `resources/js/Pages/Submission/RevisionPage.vue`, `resources/js/Components/Submission/Revision/{ReviewThreadCard,RevisionEditPanel}.vue`
- Test: `tests/frontend/components/Submission/Revision/{ReviewThreadCard,RevisionEditPanel}.spec.ts`, `tests/frontend/pages/RevisionPage.spec.ts`

**Interfaces:**
- Consumes: payload Task 3; `WizardMissingField` dari `types/submission-wizard.ts`; `ConfirmDialog`, `AppBanner`, `SectionCard`, `PageHeader`, `StatusBadge`.
- Produces (TS, cermin Task 3): `SubmissionAbilities` + `discussReview: boolean` (fixture `can` di spec lain ikut), `RevisionSubmission`, `ReviewAttachment`, `ReviewComment`, `ReviewThread`, `SubmissionRevisionPageProps { submission: RevisionSubmission; threads: ReviewThread[]; missingFields: WizardMissingField[]; accountBlock: string | null; can: SubmissionAbilities }`.
- `ReviewThreadCard` props `{ thread: ReviewThread; canReply: boolean }`: nama reviewer + badge "Terbuka"/"Selesai", catatan (`whitespace-pre-line`, tanpa `v-html`), lampiran (tautan), komentar + balasan; ulir terbuka → `AppBanner` lock "Hanya reviewer yang membuka catatan ini yang dapat menandainya selesai. Balas untuk menyampaikan perbaikan Anda." (`<!-- BR-REV-06 -->`), **tanpa** tombol tandai selesai; `canReply` → `useForm({ comment_text: '' })` → `post(route('review-comments.store', thread.id), { preserveScroll: true, onSuccess: reset })`, error `comment_text` tampil.
- `RevisionEditPanel` props `{ submissionId: number; missingFields: WizardMissingField[]; canEdit: boolean }`: `missingFields` → `AppBanner` "{n} field wajib masih kosong. Lengkapi sebelum mengirim ulang." + daftar label bertaut `route('user.submissions.wizard', [submissionId, field.step])` (`<!-- BR-SM-02 -->`); `canEdit` → tautan "Isian pengajuan" (2), "Anggota" (4), "Anggaran" (5), "Berkas" (6) ke wizard; footnote frame "Rencana anggaran masih dapat diubah selama revisi."
- `RevisionPage`: `PageHeader` judul "Revisi Pengajuan", subjudul judul pengajuan, back "Detail Pengajuan" → `user.submissions.show`; aksi "Kirim Ulang" hanya bila `can.resubmit`, nonaktif bila `missingFields.length > 0 || accountBlock`, lewat `ConfirmDialog` → `router.post(route('user.submissions.resubmit', id))`; tanpa `can.resubmit` → banner "Hanya ketua pengaju yang dapat mengirim ulang pengajuan ini."; `accountBlock` tampil sebagai teks. Dua kolom `lg:grid-cols-2`: "Catatan Reviewer" (`ReviewThreadCard` per ulir, `EmptyState` bila kosong) dan "Edit Pengajuan" (`RevisionEditPanel`, `canEdit = can.update`).

- [ ] **Step 1: Tulis Vitest yang gagal.** `ReviewThreadCard`: `[REV-DISCUSS-02] ketua membalas lewat review-comments.store`, `[REV-DISCUSS-04] tidak ada tombol tandai selesai bagi peneliti`, `tanpa hak balas, kotak balas tidak dirender`, `catatan dirender sebagai teks, bukan HTML`. `RevisionEditPanel`: `[SM-SUBMIT-19] mendaftar field wajib kosong dengan tautan langkah wizard`. `RevisionPage`: `[SM-SUBMIT-12] ketua mengirim ulang lewat submissions.resubmit`, `[SM-SUBMIT-19] Kirim Ulang nonaktif selama field wajib kosong`, `[SM-SUBMIT-13] bukan ketua: Kirim Ulang tidak dirender`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Submission/Revision tests/frontend/pages/RevisionPage.spec.ts` → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces.
- [ ] **Step 4: Jalankan** `npm run quality` → PASS.
- [ ] **Step 5: Commit** `feat(fe): halaman revisi pengajuan`

### Task 5: Tutup irisan

**Files:**
- Modify: `docs/design/fe-v2-design.md` (§8, §9), memory `fe-v2-s1-carryover.md`

- [ ] **Step 1: Periksa di browser** (resep di memory `fe-v2-s1-carryover`): ketua NeedsRevision → detail → "Mulai Revisi"/edit → RevisionPage, balas ulir, buka wizard dari panel, Kirim Ulang → mendarat di detail berstatus Direview; tab Target Luaran: tambah, ubah, hapus, jejaknya; tab Riwayat dengan banner. Laporkan apa yang dicek dan apa yang tidak.
- [ ] **Step 2: Gate penuh.** `composer run quality` (container) dan `npm run quality` → keduanya hijau; laporkan keluaran apa adanya.
- [ ] **Step 3: `fe-v2-design.md`.** §8: S2c `☑`, kolom halaman baru → `Submission/RevisionPage` + tab Target Luaran/Riwayat di `ShowPage` (W17), legacy "—". §9: tambah
  - **W17** — RESEARCHER-02-11 dan 07-01 menggambar tab detail pengajuan, bukan layar sendiri. Penulis rencana S2c: dibangun sebagai tab `Submission/ShowPage`; `TargetOutputPage`/`AuditPage` dicoret. Riwayat sistem untuk Operator tetap `Reporting/AuditPage` di R1.
  - **W18** — Panel "Edit Pengajuan" 02-05 menggambar field inline dan "Kirim Ulang aktif setelah ada perubahan"; tak ada BR yang mensyaratkan perubahan sebelum resubmit (SM-SUBMIT-12/19 hanya menuntut field wajib). Penulis rencana S2c: penyuntingan revisi = wizard (seperti W12), panel menaut langkahnya; Kirim Ulang hanya dinonaktifkan oleh field wajib kosong dan blok akun.
  - **W19** — Entri audit target luaran bersubjek target, sehingga `AuditScope` tidak menunjukkannya ke tim pengajuan, dan penyuntingan target hanya tercatat di log `default`. Penulis rencana S2c: subjeknya pengajuan (aggregate root), penyuntingan dicatat `AuditTrail` dengan nilai lama/baru (BR-SM-15, BR-RPT-05/09). Entri lama di DB dev tidak dimigrasi.
- [ ] **Step 4: Memory.** S2c selesai; hapus butir S2c dari "Still to carry"; tambahkan selisih/minor baru yang ditemukan.
- [ ] **Step 5: Commit** `feat(fe): irisan S2c revisi dan target luaran selesai`, lalu sarankan session baru untuk S3a.
