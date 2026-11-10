# P1 — Putaran Review, Setujui oleh Operator — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** APPROVED hanya lewat aksi Setujui operator; NEEDS_REVISION tetap otomatis dari catatan reviewer, tapi baru dihitung setelah setiap peserta putaran review menekan "Selesai menilai"/"Selesai memeriksa"; Setujui dan Tolak menunggu putaran selesai.

**Architecture:** Konsep baru **putaran review** (`review_rounds` + `review_round_participants`) milik pengajuan utama. Putaran penilaian (`assessment`) dibuka saat reviewer ditugaskan; putaran pemeriksaan (`recheck`) dibuka saat peneliti mengirim ulang. Action `FinishReviewRound` mencatat tanda Selesai; peserta terakhir memicu `ConcludeReviewRound`, satu-satunya tempat hasil review dihitung (NEEDS_REVISION atau "siap diputuskan"). `EvaluateSubmissionOutcome` dihapus — pemicu otomatisnya di `SubmitEvaluationForm` dan `ResolveReviewSummary` ikut dicabut. "Siap diputuskan" adalah Query (`DecisionReadiness`), bukan kolom. Aksi baru `ApproveSubmission` meniru `RejectSubmission`.

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, spatie/laravel-model-states, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-keputusan-operator-design.md` (disetujui pemilik 2026-11-10). DDD `ddd/core/02_review.md` (BR-REV-05, 06, 07, 08, 10, 14, 15), `ddd/generic/01_form_engine.md` (BR-FE-06), ADR 0006, 0017. BDD `bdd/core/review/outcome.feature`.

**Ukuran:** dua session. **P1-a = Task 1–6** (dokumen + backend), **P1-b = Task 7–9** (frontend + penutup). Gate `composer run quality` hijau di akhir P1-a; kedua gate hijau di akhir P1-b.

## Global Constraints

- Status hanya lewat `transitionTo()`; tidak ada jalur ke APPROVED selain `ApproveSubmission`, tidak ada jalur ke REJECTED selain `RejectSubmission` (BR-REV-14, `[REV-OUTCOME-08]`).
- Keputusan operator dijaga permission `submissions.view-all` (sama dengan Tolak sekarang).
- Putaran review hanya untuk pengajuan utama (`parent_submission_id IS NULL`). Laporan monev tidak pernah punya putaran dan tidak pernah "siap diputuskan".
- BR-FE-06 tetap: catatan dibuka setelah evaluasi reviewer itu `completed`/`not_required`.
- BR-REV-08 tetap: lembar penilaian yang sudah `submitted` tidak diisi ulang di putaran pemeriksaan.
- Tanda Selesai tidak bisa dibatalkan. Setelah Selesai, reviewer tidak bisa membuka catatan baru di putaran itu; `ReviewComment` tetap boleh.
- Teks UI persis: "Selesai menilai", "Selesai memeriksa", "Menunggu keputusan", "Setujui", "Pengajuan disetujui." (flash), "Anda sudah menyelesaikan putaran ini."
- Audit: `ApproveSubmission::AUDIT_DESCRIPTION = 'Pengajuan disetujui'`; `FinishReviewRound` → `'Putaran review diselesaikan reviewer'`; `ConcludeReviewRound` → `'Putaran review selesai'`.
- Gate via container (lihat AGENTS.md): Postgres lokal di port **5433**; jalankan Pest dengan `-e DB_PORT=5433 -e DB_PASSWORD=<.env> -e APP_URL=http://localhost`.

## Review Focus

1. **Dua peserta terakhir menekan Selesai bersamaan** → putaran disimpulkan tepat sekali (satu transisi, satu notifikasi). Test di Task 4.
2. **Operator menambah reviewer setelah putaran selesai "siap"** → putaran penilaian baru terbuka, Setujui/Tolak kembali menunggu. Test di Task 3.
3. **Semua catatan ditutup selama NEEDS_REVISION** → pengiriman ulang membuka putaran pemeriksaan tanpa peserta, langsung selesai "siap", operator diberi tahu. Test di Task 4.
4. **Laporan monev** → penugasan reviewer siklus (`AssignCycleReviewer`) tidak membuat putaran; evaluasi monev tetap tersubmit dan `NotifyMonevEvaluationSubmitted` tetap jalan. Test di Task 3.
5. **Reviewer yang sudah Selesai lalu diganti** → penggantinya peserta belum selesai; putaran tidak selesai karena penggantian. Test di Task 3.

## Kesesuaian dengan BDD

Skenario `outcome.feature` ditulis ulang/ditambah **di task yang menulis test-nya**, sehingga `pending.txt` tetap `max: 0`. Tag mengikuti pola file itu (`@BR-… @M2 @<layar> @<jenis>`).

| ID | Isi (Given/When/Then ringkas) | BR | Task |
| --- | --- | --- | --- |
| `[REV-OUTCOME-01]` ditulis ulang | Semua peserta Selesai menilai tanpa catatan terbuka → status tetap UNDER_REVIEW, pengajuan siap diputuskan | BR-REV-05, BR-REV-16 | 4 |
| `[REV-OUTCOME-02]` ditulis ulang | Satu peserta belum Selesai menilai → putaran berjalan, tidak siap | BR-REV-16 | 4 |
| `[REV-OUTCOME-03]` ditulis ulang | Siap tapi ada catatan terbuka → Setujui ditolak menyebut jumlahnya | BR-REV-05 | 5 |
| `[REV-OUTCOME-04]` ditulis ulang | Peserta terakhir Selesai, catatan terbuka → NEEDS_REVISION, "Budi" menerima RevisionRequested | BR-REV-06 | 4 |
| `[REV-OUTCOME-05]` ditulis ulang | Menutup catatan terakhir tidak mengubah status; putaran pemeriksaan selesai tanpa catatan → siap | BR-REV-06, BR-REV-16 | 4 |
| `[REV-OUTCOME-06]` ditulis ulang | Peserta `not_required` dihitung selesai | BR-REV-16 | 4 |
| `[REV-OUTCOME-07]` tetap | Tolak manual beralasan — fixture kini siap diputuskan | BR-REV-07 | 5 |
| `[REV-OUTCOME-08]` tetap | Tidak ada kombinasi yang menghasilkan REJECTED — diikat ulang ke `ConcludeReviewRound` | BR-REV-14 | 4 |
| `[REV-OUTCOME-09]` ditulis ulang | Fase tanpa review: langsung siap diputuskan, tidak APPROVED otomatis | BR-REV-05 (ADR 0018) | 5 |
| `[REV-OUTCOME-10]` tetap | Fase butuh review tanpa reviewer tidak siap | BR-REV-05 | 5 |
| `[REV-OUTCOME-11]` baru | Operator Setujui pengajuan siap → APPROVED, peneliti & anggota menerima ProposalApproved | BR-REV-05 | 5 |
| `[REV-OUTCOME-12]` baru | Selama putaran berjalan, Setujui dan Tolak ditolak | BR-REV-05, BR-REV-07 | 5 |
| `[REV-OUTCOME-13]` baru | Reviewer terakhir membuka catatan setelah evaluasi `completed`, sebelum Selesai menilai → tetap dihitung, hasilnya NEEDS_REVISION | BR-REV-16, BR-FE-06 | 4 |
| `[REV-OUTCOME-14]` baru | Selesai menilai ditolak selama evaluasi belum `completed` | BR-REV-16 | 4 |
| `[REV-OUTCOME-15]` baru | Setelah Selesai, membuka catatan baru di putaran itu ditolak | BR-REV-17 | 4 |
| `[REV-OUTCOME-16]` baru | Kirim ulang → putaran pemeriksaan hanya untuk reviewer bercatatan terbuka; mereka menerima RecheckRequested | BR-REV-16 | 3 |
| `[REV-OUTCOME-17]` baru | Saat pemeriksaan, menutup satu catatan sementara catatan rekan terbuka tidak mengembalikan ke revisi | BR-REV-16 | 4 |
| `[REV-OUTCOME-18]` baru | Putaran pemeriksaan selesai dengan catatan masih terbuka → NEEDS_REVISION serentak | BR-REV-06 | 4 |
| `[REV-OUTCOME-19]` baru | Pengganti reviewer menjadi peserta belum selesai di putaran berjalan | BR-REV-10, BR-REV-16 | 3 |
| `[REV-OUTCOME-20]` baru | Operator menerima SubmissionReadyForDecision saat putaran selesai tanpa catatan terbuka | BR-REV-16 | 4 |
| `[REV-OUTCOME-21]` baru | Penyaring "Menunggu keputusan" hanya memuat pengajuan siap, tanpa laporan monev | BR-REV-05 | 8 |

BR baru (Task 1): **BR-REV-16** (putaran review dan tanda Selesai), **BR-REV-17** (catatan hanya dibuka peserta putaran berjalan yang belum Selesai). BR-REV-05 dan BR-REV-06 ditulis ulang di tempat (kodenya tetap, sehingga rujukan lama di kode tetap bermakna). `uncovered.txt` tidak berubah: BR-REV-16/17 lahir bersama skenarionya.

## Keputusan penulis rencana

- **P1-W1** — Fase tanpa review: operator tidak menerima SubmissionReadyForDecision terpisah, karena `ProposalSubmitted` ke operator sudah terkirim di momen yang sama (`SendProposalSubmittedNotification`). Dua pemberitahuan untuk satu kejadian adalah derau.
- **P1-W2** — Penugasan reviewer saat putaran pemeriksaan berjalan tidak menjadikannya peserta (ia tidak punya catatan untuk diperiksa). Penugasan saat tidak ada putaran berjalan membuka putaran penilaian baru berisi reviewer itu saja.
- **P1-W3** — `OpenReviewThread` untuk pengajuan utama mensyaratkan pembuka adalah peserta putaran berjalan yang belum Selesai (BR-REV-17). Tanpa ini, catatan yang dibuka setelah putaran selesai membuat pengajuan buntu: Setujui terkunci, revisi tidak terpicu.
- **P1-W4** — Backfill (Task 6) tidak memanggil Action dan tidak mengirim notifikasi. Di aturan lama, pengajuan menunggu dengan semua reviewer `completed` sudah pasti APPROVED atau NEEDS_REVISION, jadi putaran penilaian hasil backfill selalu punya peserta belum selesai; putaran pemeriksaan tanpa peserta ditutup "siap" langsung.

---

### Task 1: Dokumen kesepakatan — DDD dan ADR 0018

**Files:**
- Modify: `docs/requirement-analysis/ddd/core/02_review.md` (activity diagram "Alur Assignment & Evaluasi" dan "Alur Threaded Discussion (Revisi)", tabel Business Rules, Domain Events)
- Modify: `docs/requirement-analysis/ddd/02_ubiquitous_language.md`
- Modify: `docs/requirement-analysis/ddd/core/01_submission.md` (baris `ProposalApproved` di Domain Events)
- Modify: `docs/requirement-analysis/ddd/generic/03_notification.md` (tabel Events yang Dikonsumsi)
- Create: `docs/adr/0018-review-outcome-at-round-end-operator-approves.md`
- Modify: `docs/adr/0017-vacuous-approval-only-when-configured.md` (Status: "Digantikan ADR 0018"), `docs/adr/README.md`

Pemilik sudah menyetujui spec, termasuk §5 tabel dampak — itulah izin menyunting DDD.

- [ ] **Step 1: Tulis ADR 0018** — Konteks: tiga celah spec §1. Keputusan: hasil review dihitung di akhir putaran (BR-REV-16); APPROVED hanya aksi operator; fase tanpa review menunggu operator (menggantikan 0017). Alternatif ditolak: (B) catatan sebelum lembar dikirim, (C) membuka catatan memicu revisi — alasan dari spec R3.
- [ ] **Step 2: Tulis ulang BR-REV-05/06, tambah BR-REV-16/17** dengan bunyi:
  - BR-REV-05: "Submission menjadi APPROVED **hanya** lewat aksi Setujui Operator, dan hanya bila siap diputuskan (putaran review terakhir selesai, atau fase tanpa review) dan tidak ada ReviewSummary `open`."
  - BR-REV-06: "Saat putaran review selesai dan masih ada ReviewSummary `open`, Submission masuk NEEDS_REVISION otomatis — serentak untuk semua catatan putaran itu."
  - BR-REV-16: "Hasil review dihitung di akhir putaran. Putaran penilaian: peserta = semua SubmissionReviewer aktif; masing-masing menekan *Selesai menilai* setelah evaluasinya `completed`. Putaran pemeriksaan (setelah resubmit): peserta = reviewer aktif yang punya ReviewSummary `open`; masing-masing menekan *Selesai memeriksa*. `not_required` dihitung selesai. Tanda Selesai per reviewer per putaran, tidak bisa dibatalkan. Mengirim lembar penilaian atau menutup catatan tidak memicu hasil."
  - BR-REV-17: "ReviewSummary untuk Submission utama hanya boleh dibuka peserta putaran berjalan yang belum menekan Selesai."
  - BR-REV-07: tambahkan "…dan hanya bila Submission siap diputuskan."
- [ ] **Step 3: Activity diagram, Domain Events, ubiquitous language, notifikasi** — istilah: *Putaran Review*, *Putaran Penilaian*, *Putaran Pemeriksaan*, *Selesai menilai*, *Selesai memeriksa*, *Siap diputuskan*. Event baru di tabel notifikasi: `SubmissionReadyForDecision` → LPPM Operator, In-app; `RecheckRequested` → peserta putaran pemeriksaan, In-app + Email. `ProposalApproved` trigger "Operator menyetujui".
- [ ] **Step 4: Verifikasi** — `grep -n "BR-REV-1[67]" docs/requirement-analysis/ddd/core/02_review.md` menampilkan dua baris; ADR README memuat 0018.
- [ ] **Step 5: Commit** — `docs(ddd): putaran review dan persetujuan operator (ADR 0018)`

---

### Task 2: Lima notifikasi baru

**Files:**
- Create: `app/Notifications/SubmissionReadyForDecisionNotification.php`, `RevisionRequestedNotification.php`, `RecheckRequestedNotification.php`, `ProposalApprovedNotification.php`, `ProposalRejectedNotification.php`
- Modify: `app/Support/Notification/NotificationEventCatalog.php` (DEFAULTS)
- Create: `database/migrations/2026_11_11_000000_declare_review_decision_notification_channels.php` (pola `2026_10_24_130000_declare_review_discussion_notification_channels.php`)
- Modify: `app/Queries/Notification/NotificationDestination.php`
- Test: `tests/Feature/Notification/ReviewDecisionNotificationsTest.php`, `tests/Feature/Notification/NotificationDestinationTest.php`

**Interfaces:**
- Produces: kelima kelas, masing-masing `__construct(private readonly FormSubmission $submission)`, `EVENT_TYPE` = nama kelas tanpa sufiks `Notification`, `use UsesConfiguredChannels`, `toArray()` lewat `NotificationPayload` dengan context `['form_submission_id' => …]`. `RecheckRequested`, `RevisionRequested`, `ProposalApproved`, `ProposalRejected` → `[IN_APP, EMAIL]`; `SubmissionReadyForDecision` → `[IN_APP]`.

- [ ] **Step 1: Tulis test gagal** — `it('mendeklarasikan kanal kelima event keputusan review')`: untuk tiap EVENT_TYPE, `expect(NotificationChannels::for($type))->toBe(NotificationEventCatalog::defaults()[$type])`; `it('payload membawa form_submission_id')` untuk tiap kelas. Di `NotificationDestinationTest`: operator → halaman kelola pengajuan untuk `SubmissionReadyForDecision`; peserta → `reviewer.assignments.summary` untuk `RecheckRequested`; peneliti → halaman pengajuannya untuk tiga sisanya.
- [ ] **Step 2: Jalankan, pastikan gagal** (kelas tidak ada).
- [ ] **Step 3: Implementasi** — judul in-app: "Pengajuan siap diputuskan", "Pengajuan perlu revisi", "Revisi menunggu pemeriksaan Anda", "Pengajuan disetujui", "Pengajuan ditolak" (yang terakhir menyertakan `rejection_reason`).
- [ ] **Step 4: Jalankan** `tests/Feature/Notification` → PASS, termasuk `NotificationChannelTest` yang menolak event tanpa deklarasi.
- [ ] **Step 5: Commit** — `feat(notif): lima notifikasi keputusan review`

---

### Task 3: Putaran review — data, pembukaan, penggantian

**Files:**
- Create: `database/migrations/2026_11_11_100000_create_review_rounds_tables.php`
- Create: `app/Enums/ReviewRoundKind.php`, `app/Models/ReviewRound.php`, `app/Models/ReviewRoundParticipant.php`
- Create: `app/Actions/Review/JoinReviewRound.php`, `app/Actions/Review/StartRecheckRound.php`, `app/Actions/Review/HandOverRoundSeat.php`
- Modify: `app/Models/FormSubmission.php` (relasi), `app/Actions/Review/AssignReviewer.php`, `app/Actions/Review/ReplaceReviewer.php`, `app/Actions/FormSubmission/ResubmitFormSubmission.php`
- Modify: `tests/Feature/Review/EvaluateSubmissionOutcomeTest.php` → pindahkan `outcomeSubmission`, `reviewerOn`, `openSummaryOn` ke `tests/Support/fixtures.php`
- Test: `tests/Feature/Review/ReviewRoundTest.php`
- Modify: `docs/requirement-analysis/bdd/core/review/outcome.feature` (REV-OUTCOME-16, 19)

**Interfaces:**
- Produces:
  - Tabel `review_rounds`: `id`, `form_submission_id` (FK cascade), `number` smallint, `kind` string, `completed_at` timestamp null, `outcome` string null (`needs_revision`|`ready`), timestamps; unique (`form_submission_id`, `number`).
  - Tabel `review_round_participants`: `id`, `review_round_id` (FK cascade), `submission_reviewer_id` (FK), `finished_at` timestamp null, timestamps; unique (`review_round_id`, `submission_reviewer_id`).
  - `enum ReviewRoundKind: string { Assessment = 'assessment'; Recheck = 'recheck'; public function finishLabel(): string }` → "Selesai menilai" / "Selesai memeriksa".
  - `ReviewRound`: `kind` cast enum; `participants(): HasMany`; `isRunning(): bool` (`completed_at === null`); `unfinishedParticipants(): HasMany` — `finished_at IS NULL` **dan** seat `evaluation_status != 'not_required'`.
  - `FormSubmission::reviewRounds(): HasMany`; `FormSubmission::runningReviewRound(): ?ReviewRound`; `FormSubmission::latestReviewRound(): ?ReviewRound`.
  - `JoinReviewRound::run(SubmissionReviewer $seat): void` — no-op untuk laporan monev; putaran penilaian berjalan → tambah peserta; putaran pemeriksaan berjalan → no-op (P1-W2); tidak ada putaran berjalan → buka putaran penilaian baru (`number` = terakhir + 1) berisi seat itu.
  - `StartRecheckRound::run(FormSubmission $submission): ReviewRound` — peserta = seat aktif yang `reviewer_id`-nya punya `ReviewSummary` `open`; kirim `RecheckRequestedNotification` ke user peserta. Peserta kosong → Task 4 menyimpulkannya (lihat Interfaces Task 4).
  - `HandOverRoundSeat::run(SubmissionReviewer $outgoing, SubmissionReviewer $incoming): void` — di putaran berjalan: bila `outgoing` peserta, hapus barisnya dan pastikan `incoming` peserta belum selesai.

- [ ] **Step 1: Pindahkan helper** ke `tests/Support/fixtures.php`; `reviewerOn` memanggil `JoinReviewRound::run($seat)` setelah membuat seat, supaya fixture mengikuti jalur produksi.
- [ ] **Step 2: Tulis test gagal** di `ReviewRoundTest.php`:
  - `it('penugasan pertama membuka putaran penilaian berisi reviewer itu')` → `latestReviewRound()->kind === ReviewRoundKind::Assessment`, `number === 1`, satu peserta.
  - `it('penugasan setelah putaran selesai membuka putaran penilaian baru')` (Review Focus 2) → buat putaran 1 lalu set `completed_at`, tugaskan reviewer lain → `number === 2`, `isRunning()`.
  - `it('[REV-OUTCOME-16] kirim ulang membuka putaran pemeriksaan untuk reviewer bercatatan terbuka')` → dua seat `completed`, catatan terbuka hanya milik A, resubmit lewat `ResubmitFormSubmission` → putaran `Recheck` berpeserta A saja; `Notification::assertSentTo($a->reviewer->user, RecheckRequestedNotification::class)`; tidak terkirim ke B.
  - `it('[REV-OUTCOME-19] pengganti menjadi peserta belum selesai')` (Review Focus 5) → A sudah `finished_at`, `ReplaceReviewer::run(...)` A→C → peserta = {C} dengan `finished_at === null`, putaran tetap berjalan.
  - `it('penugasan reviewer siklus monev tidak membuat putaran')` (Review Focus 4) → `AssignCycleReviewer::run(...)` pada `monevFixture()` → `ReviewRound::count() === 0`.
- [ ] **Step 3: Jalankan, pastikan gagal.**
- [ ] **Step 4: Implementasi** — migration, enum, model, tiga Action; panggil `JoinReviewRound` di akhir `AssignReviewer::handle`, `HandOverRoundSeat` di `ReplaceReviewer` setelah `AssignReviewer::run`, `StartRecheckRound` di `ResubmitFormSubmission::returnToReviewQueue` setelah transisi ke UNDER_REVIEW. Komentar BR-REV-16 / BR-REV-10.
- [ ] **Step 5: Tulis skenario** REV-OUTCOME-16 dan 19 di `outcome.feature`.
- [ ] **Step 6: Jalankan** `tests/Feature/Review tests/Feature/Monev tests/Feature/Submission tests/Unit/Architecture` → PASS.
- [ ] **Step 7: Commit** — `feat(review): putaran review dibuka saat penugasan dan kirim ulang (BR-REV-16)`

---

### Task 4: Tanda Selesai dan kesimpulan putaran

**Files:**
- Create: `app/Actions/Review/FinishReviewRound.php`, `app/Actions/Review/ConcludeReviewRound.php`
- Create: `app/Http/Controllers/Review/ReviewRoundController.php`, route `reviewer.assignments.finish` (POST `/reviewer/assignments/{submission}/finish`, grup `can:reviewers.evaluate`)
- Modify: `app/Actions/Review/SubmitEvaluationForm.php`, `app/Actions/Review/ResolveReviewSummary.php` (hapus panggilan `EvaluateSubmissionOutcome`), `app/Actions/Review/OpenReviewThread.php` (BR-REV-17), `app/Actions/Review/AssignReviewers.php` (setelah set `not_required`), `app/Actions/Review/StartRecheckRound.php` (peserta kosong)
- Delete: `app/Actions/Review/EvaluateSubmissionOutcome.php`, `tests/Feature/Review/EvaluateSubmissionOutcomeTest.php` (skenarionya pindah)
- Test: `tests/Feature/Review/ReviewRoundOutcomeTest.php`; rewrite `tests/Feature/Review/ReviewOutcomeWiringTest.php`
- Modify: `outcome.feature` (REV-OUTCOME-01, 02, 04, 05, 06, 08, 13, 14, 15, 17, 18, 20)

**Interfaces:**
- Consumes: Task 3 (`ReviewRound`, `runningReviewRound()`, `unfinishedParticipants()`), Task 2 (`RevisionRequestedNotification`, `SubmissionReadyForDecisionNotification`).
- Produces:
  - `FinishReviewRound::run(SubmissionReviewer $seat, User $actor): ReviewRound` — tolak (`AuthorizationException`) bila seat bukan milik `$actor` atau tidak aktif; tolak (`ValidationException`, key `round`) bila tidak ada putaran berjalan, seat bukan peserta, sudah Selesai ("Anda sudah menyelesaikan putaran ini."), atau putaran penilaian dengan evaluasi belum `completed`/`not_required` ("Kirim lembar penilaian lebih dulu."). Mengisi `finished_at`, lalu `ConcludeReviewRound::run($round)`.
  - `ConcludeReviewRound::run(ReviewRound $round): bool` — kunci baris putaran (`lockForUpdate`); `false` bila sudah selesai atau masih ada peserta belum selesai. Selain itu isi `completed_at` + `outcome`: ada `ReviewSummary` `open` → transisi ke NEEDS_REVISION (lewat UNDER_REVIEW bila perlu, pola `settleInto()` lama) + `RevisionRequestedNotification` ke `submittedBy`; tidak ada → `outcome = ready` + `SubmissionReadyForDecisionNotification` ke `User::permission('submissions.view-all')`. Mengembalikan `true`. Tidak pernah ke APPROVED/REJECTED.
  - `OpenReviewThread` (pengajuan utama): tolak bila pembuka bukan peserta putaran berjalan yang belum Selesai — key `review_summary`, "Catatan hanya bisa dibuka selama putaran Anda berjalan."

- [ ] **Step 1: Tulis test gagal** di `ReviewRoundOutcomeTest.php` (helper `finishSeat(SubmissionReviewer $seat): ReviewRound` di fixtures):
  - `[REV-OUTCOME-01]` dua seat `completed`, keduanya Selesai, tanpa catatan → status tetap `UnderReview`, `latestReviewRound()->outcome === 'ready'`.
  - `[REV-OUTCOME-02]` satu Selesai, satu belum → `isRunning()`, status tetap.
  - `[REV-OUTCOME-04]` A buka catatan, A dan B Selesai → `NeedsRevision`; `RevisionRequestedNotification` ke `submittedBy`.
  - `[REV-OUTCOME-06]` A `completed` Selesai, B `not_required` tanpa aksi → putaran selesai `ready`.
  - `[REV-OUTCOME-08]` dataset kombinasi (catatan ada/tidak × not_required ada/tidak) → status tak pernah `Rejected`/`Approved`.
  - `[REV-OUTCOME-13]` A Selesai; B kirim evaluasi terakhir lewat `SubmitEvaluationForm` → status tetap; B buka catatan lalu Selesai → `NeedsRevision`.
  - `[REV-OUTCOME-14]` B evaluasi `pending` → `FinishReviewRound` melempar `ValidationException` key `round`.
  - `[REV-OUTCOME-15]` A Selesai lalu `OpenReviewThread` → `ValidationException` key `review_summary`.
  - `[REV-OUTCOME-17]` setelah resubmit, peserta A dan B; A menutup catatannya (`ResolveReviewSummary`) → status tetap `UnderReview`, putaran berjalan.
  - `[REV-OUTCOME-18]` lanjutan 17: A Selesai, B Selesai dengan catatannya masih terbuka → `NeedsRevision` sekali.
  - `[REV-OUTCOME-05]` lanjutan: semua catatan ditutup, semua peserta Selesai → status tetap, `outcome === 'ready'`.
  - `[REV-OUTCOME-20]` putaran selesai `ready` → `SubmissionReadyForDecisionNotification` ke operator; tidak terkirim saat hasilnya `needs_revision`.
  - `it('kesimpulan putaran terjadi sekali walau dipanggil dua kali')` (Review Focus 1) → panggil `ConcludeReviewRound::run` dua kali → `true` lalu `false`, satu notifikasi.
  - `it('pemeriksaan tanpa peserta langsung siap')` (Review Focus 3) → catatan ditutup selama NEEDS_REVISION, resubmit → `outcome === 'ready'`, operator diberi tahu.
  - `it('reviewer not_required di tengah putaran menyimpulkan putaran')` → `AssignReviewers` menjadikan seat terakhir `not_required` → putaran selesai.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasi** Action, controller (`FormRequest` tidak perlu: tanpa input; seat diambil lewat `app/Queries/Review/ActiveReviewerAssignment.php` seperti halaman summary, tidak ada → 403, BR-SM-07), route; cabut pemicu lama; hapus `EvaluateSubmissionOutcome` (**Remove Dead Code**).
- [ ] **Step 4: Tulis ulang `ReviewOutcomeWiringTest`** lewat HTTP: POST `reviewer.assignments.finish` oleh peserta terakhir → status sesuai; oleh non-peserta → 403/422.
- [ ] **Step 5: Tulis ulang/tambah skenario** di `outcome.feature`.
- [ ] **Step 6: Jalankan** `tests/Feature/Review tests/Feature/Monev tests/Feature/Notification tests/Unit` → PASS. `grep -rn "EvaluateSubmissionOutcome" app tests` → kosong.
- [ ] **Step 7: Commit** — `feat(review): hasil review dihitung di akhir putaran (BR-REV-06, BR-REV-16, BR-REV-17)`

---

### Task 5: Siap diputuskan, Setujui, Tolak diperketat

**Files:**
- Create: `app/Queries/Review/DecisionReadiness.php`, `app/Data/View/Review/DecisionReadinessData.php`, `app/Data/View/Review/ReviewRoundProgressData.php`
- Create: `app/Actions/Review/ApproveSubmission.php`
- Modify: `app/Policies/FormSubmissionPolicy.php` (`approve`, `reject`), `app/Http/Controllers/Submission/SubmissionDecisionController.php` (`approve`), `routes/web.php` (`admin.submissions.approve`, sebelah `admin.submissions.reject`), `app/Actions/Review/RejectSubmission.php` (kirim `ProposalRejectedNotification`), `app/Data/View/Submission/Manage/OperatorAbilitiesData.php` (`approve`), `app/Queries/Submission/OperatorSubmissionDetail.php` (prop `decision`)
- Test: `tests/Feature/Review/ApproveSubmissionTest.php`, `tests/Feature/Review/DecisionReadinessTest.php`; perbarui `RejectSubmissionTest`, `SubmissionActionsHttpTest`, `OperatorSubmissionDetailTest` dengan fixture siap
- Modify: `outcome.feature` (REV-OUTCOME-03, 07, 09, 10, 11, 12)

**Interfaces:**
- Consumes: Task 3/4 (`latestReviewRound()`, `runningReviewRound()`, `outcome`).
- Produces:
  - `DecisionReadiness::run(FormSubmission $submission): DecisionReadinessData` dengan `ready: bool`, `approvable: bool`, `blockers: list<string>`, `openThreadCount: int`, `round: ?ReviewRoundProgressData` (`kind: string`, `number: int`, `finished: int`, `total: int`).
  - `DecisionReadiness::whereReady(Builder $query): Builder` — padanan SQL `ready` untuk daftar/dashboard (Task 8).
  - Aturan `ready`: pengajuan utama; status `Submitted|UnderReview|Resubmitted`; fase `needs_review = false` → `true`; selain itu ≥1 seat aktif **dan** `latestReviewRound()` selesai dengan `outcome = 'ready'` **dan** tidak ada putaran berjalan. `approvable = ready && openThreadCount === 0`.
  - Teks `blockers` (urutan ini): "Belum ada reviewer.", "{n} dari {total} reviewer belum menyelesaikan putaran.", "{n} catatan reviewer masih terbuka."
  - `ApproveSubmission::run(FormSubmission $submission, User $operator): FormSubmission` — `AUDIT_DESCRIPTION = 'Pengajuan disetujui'`; `AuthorizationException` bila `!$operator->can('approve', $submission)`; `ValidationException` key `approve` dengan blocker pertama bila tidak `approvable`; lewat UNDER_REVIEW bila belum; transisi ke `Approved`; audit; `ProposalApprovedNotification` ke ketua + anggota (`ResearchMember` ber-user).
  - Policy: `approve` = `can('submissions.view-all') && DecisionReadiness::run($s)->ready`; `reject` = sama (menggantikan cek `UnderReview` saja; RejectSubmission tetap melewatkan ke UNDER_REVIEW bila perlu).
  - Route `POST /admin/submissions/{submission}/approve` → `SubmissionDecisionController@approve` → flash "Pengajuan disetujui."
- Fixture: `readyForDecision(): FormSubmission` di `tests/Support/fixtures.php` (satu seat `completed`, Selesai, putaran `ready`).

- [ ] **Step 1: Tulis test gagal:**
  - `[REV-OUTCOME-11]` `readyForDecision()` → `ApproveSubmission::run` → `Approved`, audit 'Pengajuan disetujui', `ProposalApprovedNotification` ke ketua dan anggota.
  - `[REV-OUTCOME-03]` siap + satu catatan terbuka (buat langsung dengan `ReviewSummary::create`) → `ValidationException` key `approve` berisi "1 catatan reviewer masih terbuka."
  - `[REV-OUTCOME-12]` putaran berjalan → `can('approve')` dan `can('reject')` false; POST approve/reject → 403.
  - `[REV-OUTCOME-09]` fase `needs_review = false`, `Submitted` → `ready === true`, status tidak berubah tanpa aksi; Setujui → `Approved`.
  - `[REV-OUTCOME-10]` fase butuh review tanpa seat → `ready === false`, blockers `['Belum ada reviewer.']`.
  - `[REV-OUTCOME-07]` (perbarui) tolak beralasan pada `readyForDecision()` → `Rejected`, `ProposalRejectedNotification` ke ketua.
  - `it('laporan monev tidak pernah siap diputuskan')` → `monevFixture()` laporan → `ready === false`.
  - `DecisionReadinessTest`: `whereReady` mengembalikan himpunan yang sama dengan `run()->ready` atas dataset campuran.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasi** Query, Data, Action, Policy, route, controller; `OperatorSubmissionDetail` menambah `decision` (DecisionReadinessData) dan `operatorCan.approve`.
- [ ] **Step 4: Perbarui test lama** yang menolak tanpa putaran (`RejectSubmissionTest`, `SubmissionActionsHttpTest`, `OperatorSubmissionDetailTest`) memakai `readyForDecision()`.
- [ ] **Step 5: Tulis ulang/tambah skenario** di `outcome.feature`.
- [ ] **Step 6: Jalankan** `tests/Feature tests/Unit` → PASS; `./vendor/bin/phpstan analyse -c phpstan-strict.neon --memory-limit=1G` → No errors.
- [ ] **Step 7: Commit** — `feat(review): Setujui oleh operator; tolak menunggu putaran selesai (BR-REV-05, BR-REV-07)`

---

### Task 6: Backfill putaran untuk pengajuan yang sedang berjalan

**Files:**
- Create: `database/migrations/2026_11_11_200000_backfill_review_rounds.php`
- Test: `tests/Feature/Migrations/BackfillReviewRoundsTest.php`

**Interfaces:**
- Consumes: tabel Task 3.
- Produces: untuk setiap pengajuan utama berstatus `submitted|under_review|resubmitted` dengan ≥1 seat aktif dan tanpa putaran:
  - pernah dikirim ulang (ada audit `'Pengajuan dikirim ulang setelah revisi'`) → satu putaran `recheck`, peserta = seat aktif bercatatan `open`, `finished_at` null; tanpa peserta → `completed_at = now()`, `outcome = 'ready'`.
  - selain itu → satu putaran `assessment`, peserta = semua seat aktif; seat ber-`evaluation_status` `completed` → `finished_at = now()` (spec §4.10).
  - Tanpa notifikasi, tanpa transisi status (P1-W4).

- [ ] **Step 1: Tulis test gagal** — jalankan `up()` migration langsung (pola `DeclareMissingNotificationChannelsTest`) atas: (a) under_review dua seat, satu completed → assessment, satu peserta Selesai, putaran berjalan; (b) resubmitted→under_review dengan audit kirim ulang dan satu catatan terbuka → recheck berpeserta pemilik catatan; (c) sama tanpa catatan → recheck selesai `ready`; (d) laporan monev → tanpa putaran; (e) approved → tanpa putaran; (f) idempoten: `up()` dua kali → jumlah putaran sama.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasi** dengan query builder (bukan Model/Action, supaya migration tak bergantung pada kode yang berubah kemudian).
- [ ] **Step 4: Jalankan** test → PASS.
- [ ] **Step 5: Gate penuh P1-a:** `composer run quality` lewat container (pint, phpstan dua tier, pest) → semua hijau.
- [ ] **Step 6: Commit** — `feat(review): backfill putaran review untuk pengajuan berjalan`

---

### Task 7: Frontend reviewer — tombol Selesai dan kemajuan putaran

**Files:**
- Modify: `app/Queries/Review/ReviewerSummary.php` (prop `round`, `canCreateThread` mengikuti BR-REV-17), `app/Data/View/Review/OutcomeConditionsData.php` → ganti dengan `ReviewerRoundData`
- Create: `app/Data/View/Review/ReviewerRoundData.php`
- Modify: `resources/js/types/review.ts`, `resources/js/Components/Review/OutcomeConditions.vue` → rename `ReviewRoundCard.vue`, `resources/js/Pages/Review/SummaryPage.vue`
- Test: `tests/Feature/Review/ReviewerSummaryPageTest.php`, `tests/frontend/components/Review/ReviewRoundCard.spec.ts` (ganti `OutcomeConditions.spec.ts`)

**Interfaces:**
- Consumes: Task 4 route `reviewer.assignments.finish`, Task 5 `ReviewRoundProgressData`.
- Produces: `ReviewerRoundData` = `{ kind: 'assessment'|'recheck', finishLabel: string, isParticipant: bool, finished: bool, canFinish: bool, reason: string|null, progress: ReviewRoundProgressData }` atau `null` bila tidak ada putaran berjalan.

- [ ] **Step 1: Tulis test gagal** — Pest: halaman summary peserta belum Selesai dengan evaluasi completed → `round.canFinish === true`, `round.finishLabel === 'Selesai menilai'`; evaluasi pending → `canFinish === false`, `reason === 'Kirim lembar penilaian lebih dulu.'`; sudah Selesai → `finished === true`, `canCreateThread === false`. Vitest `ReviewRoundCard.spec.ts`: `[REV-OUTCOME-14]` tombol disabled dengan reason; `[REV-OUTCOME-15]` setelah Selesai menampilkan "Anda sudah menyelesaikan putaran ini." tanpa tombol; tombol mengirim `router.post(route('reviewer.assignments.finish', id))` setelah konfirmasi; `[REV-OUTCOME-08]` tetap: tidak ada jalur tolak di layar reviewer.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasi** — kartu menampilkan "Putaran {number}: {finished} dari {total} reviewer selesai", tombol `finishLabel` dengan dialog konfirmasi ("Tanda ini tidak bisa dibatalkan."), banner kunci penolakan dipertahankan.
- [ ] **Step 4: Jalankan** test Pest terkait dan `npx vitest run tests/frontend/components/Review --maxWorkers=1` → PASS.
- [ ] **Step 5: Commit** — `feat(fe): tombol Selesai menilai/memeriksa di ringkasan reviewer`

---

### Task 8: Frontend operator — keputusan, daftar, dashboard

**Files:**
- Create: `resources/js/Components/Submission/Manage/DecisionReadinessCard.vue`, `resources/js/Components/Submission/Manage/ApproveDialog.vue`
- Modify: `resources/js/Components/Submission/Manage/OperatorActions.vue` (item `approve`), `resources/js/Pages/Submission/ManageShowPage.vue`, `resources/js/types/submission-manage.ts` (`approve`, `decision`)
- Modify: `app/Queries/Submission/OperatorSubmissionList.php` (filter `decision`), `resources/js/Pages/Submission/ManageIndexPage.vue`, `app/Queries/Dashboard/ManagementDashboardCards.php`
- Test: `tests/frontend/components/Submission/Manage/DecisionDialogs.spec.ts`, `tests/frontend/pages/ManageShowPage.spec.ts`, `tests/Feature/Submission/OperatorSubmissionListTest.php`, `tests/Feature/Dashboard/ManagementDashboardCardsTest.php` (atau file dashboard yang ada)
- Modify: `outcome.feature` (REV-OUTCOME-21)

**Interfaces:**
- Consumes: Task 5 `DecisionReadiness::whereReady`, `DecisionReadinessData`, route `admin.submissions.approve`.
- Produces: filter `Filters['decision']` bernilai `''|'awaiting'`; KPI `new KpiCardData('awaitingDecision', 'Menunggu Keputusan', …, 'Siap disetujui atau ditolak', route('admin.submissions.index', ['decision' => 'awaiting']))`.

- [ ] **Step 1: Tulis test gagal** — Pest `[REV-OUTCOME-21]`: daftar dengan `decision=awaiting` memuat `readyForDecision()`, tidak memuat pengajuan berputaran berjalan maupun laporan monev; KPI menghitung yang sama. Vitest: `DecisionReadinessCard` menampilkan setiap blocker; `ApproveDialog` mengirim `router.post(route('admin.submissions.approve', id))` setelah konfirmasi; item "Setujui" hanya ada bila `can.approve`; tombol Setujui disabled dengan blocker pertama bila `!decision.approvable`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasi** — kartu di tab Review halaman kelola (di atas `ReviewTab`) berisi status putaran ("Putaran {number}: {finished} dari {total} reviewer selesai", dari `decision.round`), jumlah catatan terbuka, dan blocker; select "Keputusan: Semua / Menunggu keputusan" di daftar; KPI di dashboard manajemen.
- [ ] **Step 4: Tulis skenario** REV-OUTCOME-21.
- [ ] **Step 5: Jalankan** Pest terkait dan `npx vitest run tests/frontend --maxWorkers=1` → PASS.
- [ ] **Step 6: Commit** — `feat(fe): Setujui, kesiapan keputusan, dan penyaring Menunggu keputusan untuk operator`

---

### Task 9: Penutup

- [ ] **Step 1:** `grep -rn "EvaluateSubmissionOutcome\|REV-OUTCOME" tests app resources | sort` — setiap ID di `outcome.feature` punya test; tidak ada sisa `EvaluateSubmissionOutcome`.
- [ ] **Step 2:** `free -m` ≥ 3 GB, lalu gate penuh satu per satu di bawah watchdog: pint, phpstan, phpstan strict, pest, `npm run quality` → semua hijau. Laporkan angka test.
- [ ] **Step 3:** Perbarui `docs/design/2026-11-10-keputusan-operator-design.md` baris Status: "Diimplementasikan (P1-a, P1-b)".
- [ ] **Step 4: Commit** — `docs(design): P1 selesai diimplementasikan`
