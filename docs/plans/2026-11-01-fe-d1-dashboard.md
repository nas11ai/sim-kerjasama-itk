# FE v2 — Irisan D1: Dashboard & Notifikasi — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Satu `Dashboard/IndexPage` di `/dashboard` menggantikan tiga dasbor legacy (peneliti, reviewer, admin), dengan section per permission (SHARED-02-01..04) plus "Pengumuman terbaru". `Notification/IndexPage` (SHARED-02-05) menggantikan `Notifications/IndexPage`: notifikasi dibuka ke halaman yang relevan, bisa difilter, dan jumlah belum dibaca tampil sebagai badge di sidebar.

**Architecture:** `DashboardController` (invokable) hanya memanggil Query komposer `Queries/Dashboard/DashboardOverview`. Komposer itu menyusun bagian-bagian Query per section; tiap bagian bergerbang permission, sehingga user dua peran melihat gabungannya (§4.4 K4). Notifikasi tetap di `NotificationController` yang tipis. Bacaan pindah ke `Queries/Notification/*`. Tujuan "Buka" dihitung **saat diklik** dari `type` + konteks payload (`NotificationDestination`), lewat pemeriksaan akses pengajuan yang sama (BR-SM-07). Setiap pasangan dikerjakan FE dulu, baru BE (Task 2→3, 5→6), karena `ensure_pages_exist => true` di `config/inertia.php`. Halaman legacy dihapus di task BE yang berhenti merendernya.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, spatie/laravel-permission, Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue, Ziggy, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/fe-v2-design.md` §4.2–§4.5, baris D1 di §8, K1/K4/W32 di §9. Frame `../simpas-wireframe/screens/SHARED-02-0{1..5}/screen.js` + `screens/_lib/organisms.js` (`researcherDashboard`, `roleDashboard`), `docs/FRAME-AUDIT.md` dan `docs/STATE-COVERAGE.md` bagian SHARED-02-*. DDD `ddd/generic/03_notification.md` (BR-NOT-01..06 + activity diagram "Alur In-App Notification"), `ddd/02_ubiquitous_language.md` (**Active Submission**), BR-RPT-05/07, BR-SM-07, BR-REV-10.

## Kesesuaian dengan DDD dan BDD (diperiksa 2026-11-01)

- **Skenario yang mengikat frame ini sudah hijau di Pest** (`pending.txt` kosong): `NOT-INAPP-01..03`, `NOT-CHANNEL-01/02/05/06` (`tests/Feature/Notification/InAppNotificationTest.php` dkk.), `REV-ASSIGN-09`, `SM-ARCHIVE-02`, dan `IAM-EVENT-02..04`. Irisan ini mempertahankan semuanya. Yang wajib terlihat dari `STATE-COVERAGE.md` adalah `NOT-INAPP-03` (membuka daftar tidak mengisi `read_at`), dan itu digambar oleh Vitest Task 2 dan Pest Task 3. `NOT-CHANNEL-02/05/06` adalah perilaku pengiriman, bukan state layar, jadi tidak ada yang perlu digambar.
- **SHARED-02-03/04 sengaja tanpa skenario** (`bdd/README.md`: "angkanya turunan submission yang sudah dijaga M1–M2"). Karena itu angka dasbor **wajib memakai predikat yang sudah ada** dan tidak boleh mengarang definisi baru: Active Submission = `FormSubmission::scopeActive()` (status bukan REJECTED/WITHDRAWN), pengajuan terlihat = `SubmissionVisibility::ownedOrJoinedBy` (BR-SM-07), selesai dievaluasi = `ReviewerAssignmentCards` (sudah memakai `SETTLED_EVALUATION`, BR-MON-02), antrian verifikasi = `UserProfile::awaitingVerification()`, aktivitas = `AuditScope::forUser` (BR-RPT-05).
- **Tiga perilaku yang digambar DDD tapi belum punya skenario.** Activity diagram `03_notification.md` memuat "Redirect ke halaman yang relevan berdasarkan data.action_url" dan subgraph "Mark all as read". Kode hari ini mengabaikan keduanya: payload `NotificationPayload` tidak membawa `action_url` sehingga "Buka" berakhir di `back()`, dan komentar di `routes/web.php` menolak "tandai semua". Mengikuti preseden S4 (skenario ditulis bersama test Pest-nya), Task 3 menambah ke `bdd/generic/notification.feature`:
  - `[NOT-INAPP-04]` @BR-NOT-04 @happy: user menandai semua notifikasinya dibaca. Penghitungnya jadi 0, notifikasinya tetap di daftar (BR-NOT-01), dan penghitung user lain tidak berubah.
  - `[NOT-INAPP-05]` @BR-NOT-04 @happy: membuka notifikasi `ReviewerAssigned` mengisi `read_at` lalu membawa reviewer ke form evaluasi pengajuan itu.
  - `[NOT-INAPP-06]` @BR-NOT-04 @denial: notifikasi yang pengajuannya tak lagi bisa diakses penerimanya (penugasan diganti, BR-REV-10) tetap ditandai dibaca. Penerima tetap di daftar notifikasi dan tidak dibawa ke data pengajuan (BR-SM-07).
  Semuanya @M5 @SHARED-02-05. DDD tidak disunting.
- **Selisih wireframe↔DDD, DDD menang** (dicatat ke §9, Task 7): kartu "Submission Aktif" peneliti memakai predikat Active Submission DDD, sehingga DRAFT dan APPROVED ikut terhitung (fixture wireframe menghitung lebih sempit). Kualifikasi "Semester ini" pada "Evaluasi Selesai" dibuang karena DDD tidak mengenal semester.
- **Penanda baca tidak lewat Action dan tidak diaudit.** `read`/`read-all`/`destroy` tetap satu baris di controller, mengikuti preseden A1 (`markAsRead` pengumuman) dan catatan A1 tentang `read_at` notifikasi. Kotak masuk pribadi bukan state domain yang dipertanggungjawabkan seseorang (BR-RPT-09).

## Keputusan penulis rencana (dicatat ke §9 di Task 7, **minta konfirmasi pemilik** untuk W37)

- **W37** — "Tandai Semua Dibaca" dibangun (`POST notifications.read-all`). Dasarnya activity diagram DDD dan wireframe; urutan sumber §0 menempatkan DDD di atas komentar kode. BR-NOT-04 melarang penandaan saat *halaman dibuka*, bukan tindakan eksplisit. Komentar penolakan di `routes/web.php` diganti. **Kalau pemilik menolak:** hapus route/method `readAll` dan `NOT-INAPP-04` di Task 1 serta tombolnya di Task 2.
- **W38** — Item sidebar "Notifikasi" (ikon `Bell`, badge `navigation.unreadNotifications`) ditaruh di grup teratas, sesudah "Pengumuman". `sidebar.md` tidak memuatnya dan `topbar.md` melarang bell, sedangkan DDD menuntut "unread count badge di navbar". Di app ini sidebar adalah navbar, jadi pola W32 dipakai ulang. Tautan dari dasbor (kartu + aksi cepat) tetap ada, sesuai wireframe.
- **W39** — Tujuan notifikasi tidak disimpan (`action_url`) tapi dihitung saat diklik oleh `NotificationDestination` dari `type` + konteks. Hasilnya, akses dicek ulang pada saat itu (`NOT-INAPP-06`) dan tidak ada URL yang basi di DB. Dua kelas berbentuk payload lama (`ProposalSubmittedNotification` dengan `action_url` `/submissions/{id}` yang tidak pernah ada, dan `SubmissionPeriodActiveNotification`) dipindah ke `NotificationPayload`. Channel dan isi email tidak berubah.
- **W40** — Kategori notifikasi berupa enum backend `NotificationCategory` yang dipetakan per **kelas** notifikasi (kolom `notifications.type`), bukan per `data->type`. Chip filter hanya "Semua · Belum dibaca · Review · Monev" (wireframe). Test arsitektur memastikan setiap kelas di `app/Notifications/` punya kategori.
- **W41** — BR-NOT-05 diterapkan: `UnreadNotificationCount` di-cache per user dan dilupakan saat notifikasi `database` terkirim (listener `NotificationSent`), dibaca, dibaca semua, atau dihapus. Ini sekaligus menutup keluhan A1 soal satu query per request untuk badge.
- **W42** — Kartu operator "Total Submission Aktif" dan "Perlu Penugasan Reviewer" menghitung baris yang juga tampil di `OperatorSubmissionList` (`is_submitted`, induk saja). Draft tidak terlihat oleh Operator di mana pun, jadi tidak dihitung. "Perlu Penugasan Reviewer" = status SUBMITTED (meta wireframe "Status Disubmit") dan menaut ke daftar yang difilter `status=submitted`. "Export Selesai" = ekspor milik viewer yang selesai sejak awal minggu ini (BR-RPT-07: hanya peminta yang diberi tahu).
- **W43** — Satu ambang "mendekat": `DeadlineData::URGENT_DAYS = 5` (wireframe: 3 dan 5 hari mendesak, 6 hari tidak). Ambang yang sama dipakai meta "n mendekati deadline" reviewer.
- **W44** — "Aktivitas Terbaru" = 5 entri teratas `AuditScope::forUser` (BR-RPT-05, satu cakupan, tanpa aturan baru). Akibatnya reviewer murni hampir selalu melihat EmptyState, karena kerja evaluasinya bersubjek pengajuan orang lain. Memperluas cakupan audit untuk reviewer adalah aturan baru → **pertanyaan terbuka untuk pemilik** (alternatif: 5 notifikasi terbaru).
- **W45** — Satu halaman: banner sambutan → banner antrian (operator) → section kartu "Pengajuan Saya" (`submissions.view-own`), "Review" (`reviewers.evaluate` + baris `Reviewer`), "Manajemen" (kartu yang lolos permission masing-masing) → Aktivitas Terbaru + Deadline Mendekat (satu daftar gabungan, urut tenggat, maks 5) → Pengumuman terbaru (3) → Aksi Cepat. Aksi cepat diturunkan di frontend dari `auth.user.permissions` (`lib/dashboardActions.ts`), sama seperti sidebar (§4.4), tanpa duplikat.
- **W46** — Nama route `user.dashboard`, `reviewer.dashboard`, `admin.dashboard` tetap ada sebagai `Route::redirect` ke `/dashboard`. Legacy `Components/AppSidebar.vue` dan halaman legacy lain masih menautnya (`FrontendRouteNamesTest`), dan F99 menghapusnya. Pemanggil PHP dipindah ke `dashboard`. Item sidebar "Statistik" menaut `admin.stats.form-submission` sampai R1. `PostLoginDestination` mengembalikan `dashboard`.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris, CC ≤ 8, ≤ 4 parameter; tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy (jangan menyalin `User/DashboardPage.vue` atau `Notifications/IndexPage.vue`, tulis dari frame).
- Baris pertama `<script setup>`: `// Frame: SHARED-02-01..04` (dasbor) dan `// Frame: SHARED-02-05` (notifikasi).
- Teks UI diambil dari frame apa adanya: label kartu, "Aktivitas Terbaru", "Deadline Mendekat", "Aksi Cepat", "Semua Notifikasi", "Tandai Semua Dibaca", "Belum dibaca"/"Dibaca", dan dua banner "Bagaimana Notifikasi Bekerja". `stateGallery` di frame adalah anotasi wireframe dan **tidak** digambar.
- Frontend tidak memeriksa nama peran. Section dan kartu dikirim backend, aksi cepat dan sidebar dibaca dari permissions.
- Identifier Inggris; komentar Indonesia, prosa maks 3 baris, sebut kode BR. TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast.
- Mutasi lewat `router`, tanpa `axios`; filter di server lewat `useFilters` (`preserveState`, `replace`). Konfirmasi hapus lewat `ConfirmDialog`.
- Gate: `composer run quality` + `npm run quality`. Pest di container `simpas-ci:8.4` (Docker Desktop menyala, lihat memory/AGENTS.md), `-e DB_PORT=55432 -e DB_PASSWORD=postgres`; worktree butuh salinan `vendor/` asli dan `.env`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Pengguna yang biodatanya belum lengkap/pending membuka `/dashboard`** (tujuan redirect `CheckBiodata`): halaman tampil dengan banner biodata, tanpa loop redirect. → Task 6 `dasbor terbuka bagi biodata pending dan menampilkan statusnya`.
2. **Mengklik notifikasi lama yang tujuannya tak lagi boleh dibuka** (penugasan diganti, pengajuan pindah pemilik) → ditandai dibaca, kembali ke daftar, tanpa 403 dan tanpa data. → Task 3 `[NOT-INAPP-06]`.
3. **Badge basi**: notifikasi baru masuk, atau dibaca/dihapus/dibaca semua, padahal angkanya di-cache. → Task 1 `cache dilupakan saat notifikasi database terkirim`, Task 3 `penghitung turun setelah read, read-all, destroy`.
4. **Section tanpa data**: reviewer tanpa penugasan, operator di sistem kosong, user yang memegang `reviewers.evaluate` tanpa baris `Reviewer`. Semua kartu tampil `0`/"—", tanpa error null. Section reviewer dilewati bila baris `Reviewer` tidak ada. → Task 4 `reviewer tanpa penugasan`, `permission reviewer tanpa baris Reviewer tidak memunculkan section`.
5. **`?filter=` sembarang atau `?page=` di luar jangkauan** → diperlakukan "Semua" / halaman kosong dengan EmptyState, bukan 500. → Task 3 `filter tak dikenal dianggap semua`.

---

### Task 1: Dukungan notifikasi: kategori, tujuan, hitungan ber-cache, tandai semua (BE)

**Files:**
- Create: `app/Enums/NotificationCategory.php`, `app/Queries/Notification/NotificationDestination.php`, `app/Queries/Notification/UnreadNotificationCount.php`, `app/Listeners/ForgetUnreadNotificationCount.php`
- Modify: `app/Notifications/ProposalSubmittedNotification.php`, `app/Notifications/SubmissionPeriodActiveNotification.php` (ke `NotificationPayload`, W39), `app/Http/Middleware/HandleInertiaRequests.php` (`navigation.unreadNotifications`), `app/Http/Controllers/Notification/NotificationController.php` (`readAll`, plus `forget` di `read`/`destroy`), `routes/web.php:550-559` (route `read-all`; komentar penolakan lama diganti rujukan W37/BR-NOT-04), `docs/requirement-analysis/bdd/generic/notification.feature` (`NOT-INAPP-04`)
- Test: `tests/Feature/Notification/NotificationDestinationTest.php`, `tests/Feature/Notification/UnreadNotificationCountTest.php`, `tests/Feature/Notification/InAppNotificationTest.php`, `tests/Unit/Architecture/NotificationCategoryTest.php`

Route `read-all` lahir di sini, bukan di Task 3, karena Task 2 sudah menautnya dan `FrontendRouteNamesTest` harus hijau di setiap commit.

**Interfaces:**
- Produces: `enum NotificationCategory: string { Review='review'; Monev='monev'; Submission='submission'; Period='period'; Account='account'; Export='export' }` dengan `label(): string` (Review, Monev, Pengajuan, Periode, Akun, Ekspor), `static forClass(string $class): self`, `classes(): list<class-string>`. Peta: Review ← ReviewerAssigned, ReviewThreadOpened, ReviewCommentAdded, RevisionResolved, ReviewerAppointed; Monev ← MonevStageOpened, MonevEvaluationSubmitted; Submission ← ProposalSubmitted, PhaseOverrideGranted, FormChanged; Period ← SubmissionPeriodActive, SubmissionPeriodClosed, SubmissionDeadlineExtended; Account ← NidnVerified, NidnVerificationRejected, AccountDeactivated, NewRegistrationAwaitingVerification; Export ← ExportCompleted, ExportFailed.
- Produces: `NotificationDestination::run(DatabaseNotification $notification, User $recipient): ?string` (URL absolut).
- Produces: `UnreadNotificationCount::run(User $user): int`, `UnreadNotificationCount::forget(User $user): void`.
- Produces: shared prop `navigation.unreadNotifications: int`; route `POST /notifications/read-all` → `notifications.read-all` (`readAll`: `$user->unreadNotifications()->update(['read_at' => now()])`, `forget`, `back()->with('success', 'Semua notifikasi ditandai dibaca.')`).

- [ ] **Step 1: Tulis test yang gagal.** `NotificationCategoryTest`: setiap kelas di `app/Notifications/*.php` (Finder, kecuali `Concerns/`) punya `forClass` tanpa exception. `NotificationDestinationTest`, satu `it` per baris tabel tujuan:
  - `reviewer_assigned` → `reviewer.assignments.evaluation`.
  - `review_thread_opened`/`review_comment_added`/`revision_resolved`/`phase_override_granted`/`monev_*`/`proposal_submitted` → halaman pengajuan menurut perspektif penerima, diperiksa berurutan: pemilik/anggota (`user.submissions.show`) → `submissions.view-all` (`admin.submissions.show`) → reviewer aktif (`reviewer.assignments.summary`) → `null`.
  - Tipe periode dan `form_changed` → `user.submissions.index`.
  - `nidn_*` → `verification.status`; `new_registration_awaiting_verification` → `admin.verifications.index`; `export_*` → `exports.index`; `reviewer_appointed` → `reviewer.assignments.index`; `account_deactivated` dan tipe tak dikenal → `null`.
  - Ditambah `it('penugasan yang diganti tidak membuka pengajuan')` (BR-REV-10 → `null`).
  `UnreadNotificationCountTest`: `it('cache dilupakan saat notifikasi database terkirim')` (hitung → 1, kirim notifikasi kedua → 2) dan `it('forget membuat hitungan dibaca ulang')`. Tambah skenario `NOT-INAPP-04` ke `notification.feature` beserta `it('[NOT-INAPP-04] …')` di `InAppNotificationTest`: dua notifikasi Budi jadi terbaca, satu milik Dewi tetap belum, ketiganya masih ada, dan hitungan Budi 0. Ditambah satu test payload: `ProposalSubmittedNotification` menyimpan `type: proposal_submitted` + `form_submission_id`, tanpa `action_url`.
- [ ] **Step 2: Jalankan, pastikan gagal** (`./vendor/bin/pest tests/Feature/Notification tests/Unit/Architecture/NotificationCategoryTest.php`): kelas tidak ditemukan.
- [ ] **Step 3: Implementasi.** `NotificationDestination` membaca `form_submission_id` dari `data`, memuat pengajuan, lalu menggerbang dengan `$submission->isAccessibleBy($recipient)` (AGENTS aturan 2; ia sudah hanya menghitung penugasan `active`, jadi reviewer yang diganti tertolak di sini) sebelum memilih route. Lolos gerbang tapi bukan pemilik/anggota dan tanpa `submissions.view-all` berarti reviewer aktif. Kode BR wajib di komentar. Cache: `Cache::rememberForever("notifications.unread.{id}")`. Listener menangani `Illuminate\Notifications\Events\NotificationSent` bila `$event->channel === 'database'` dan notifiable `User`. Ikuti cara listener lain didaftarkan (auto-discovery atau provider).
- [ ] **Step 4: Jalankan, pastikan lulus**, plus `tests/Feature/Notification` lengkap (payload lama tidak dipakai test lain; perbaiki bila ada).
- [ ] **Step 5: Commit** `feat(be): kategori, tujuan, hitungan, dan tandai semua notifikasi`

### Task 2: `Notification/IndexPage` dan badge sidebar (FE)

**Files:**
- Create: `resources/js/types/notification.ts`, `resources/js/Pages/Notification/IndexPage.vue`, `resources/js/Components/Notification/NotificationItem.vue`
- Modify: `resources/js/lib/navigation.ts` (item "Notifikasi", `countKey: 'unreadAnnouncements' | 'unreadNotifications'`), tipe `NavigationFlags` di `resources/js/types/`
- Test: `tests/frontend/components/Notification/NotificationItem.spec.ts`, `tests/frontend/pages/NotificationIndexPage.spec.ts`, `tests/frontend/lib/navigation.spec.ts`

**Interfaces:**
- Produces: `type NotificationFilter = 'all' | 'unread' | 'review' | 'monev'`; `interface NotificationRow { id: string; title: string; message: string; category: string; categoryLabel: string; createdAt: string; isRead: boolean }`; `interface NotificationIndexPageProps { notifications: <tipe paginasi yang sudah ada di types/pagination.ts><NotificationRow>; filter: NotificationFilter; unreadCount: number }`.
- Consumes: route `notifications.index`, `notifications.read`, `notifications.read-all` (Task 1), `notifications.destroy`.

- [ ] **Step 1: Tulis test yang gagal.**
  - `[NOT-INAPP-03] memuat halaman tidak mengirim read`: mount page, `router.post` tidak terpanggil.
  - `[NOT-INAPP-03] Buka mengirim read untuk notifikasi itu`: `router.post(route('notifications.read', id))`.
  - `[NOT-INAPP-02] Hapus lewat ConfirmDialog`: delete hanya setelah konfirmasi.
  - `Tandai Semua Dibaca tersembunyi saat unreadCount 0`.
  - Chip aktif mengikuti `filter` dan memanggil `useFilters` dengan `{ filter }`.
  - Badge "Belum dibaca"/"Dibaca" mengikuti `isRead`; meta "<categoryLabel> · <waktu relatif>" lewat `lib/format.ts`.
  - EmptyState saat daftar kosong.
  - `navigation.spec`: item Notifikasi terlihat untuk semua pengguna dan menampilkan badge saat `unreadNotifications > 0`.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/components/Notification tests/frontend/pages/NotificationIndexPage.spec.ts tests/frontend/lib/navigation.spec.ts`: gagal.
- [ ] **Step 3: Implementasi.** Halaman: `PageHeader` (judul/subjudul frame) → `SectionCard` "Semua Notifikasi" (aksi "Tandai Semua Dibaca", chip, daftar `NotificationItem`, `PaginationBar`) → `SectionCard` "Bagaimana Notifikasi Bekerja" berisi dua `AppBanner`. Ikon per kategori dipetakan di `NotificationItem` (presentasi, bukan aturan).
- [ ] **Step 4: Jalankan, pastikan lulus**; `npm run lint && npm run typecheck`.
- [ ] **Step 5: Commit** `feat(fe): layar notifikasi dan badge sidebar`

### Task 3: Kontrak layar notifikasi dan tujuan Buka (BE)

**Files:**
- Create: `app/Queries/Notification/NotificationInbox.php`, `app/Data/View/Notification/NotificationRowData.php`
- Modify: `app/Http/Controllers/Notification/NotificationController.php`, `docs/requirement-analysis/bdd/generic/notification.feature`
- Delete: `resources/js/Pages/Notifications/IndexPage.vue` (+ barisnya di `quality-legacy.txt`)
- Test: `tests/Feature/Notification/InAppNotificationTest.php` (diperbarui), `tests/Feature/Notification/NotificationInboxTest.php`

**Interfaces:**
- Consumes: Task 1 seluruhnya; Task 2 nama komponen `Notification/IndexPage`.
- Produces: `NotificationInbox::run(User $user, string $filter): array{notifications: LengthAwarePaginator<int, NotificationRowData>, filter: string, unreadCount: int}`, `PER_PAGE = 20`. Filter tak dikenal menjadi `all`; `review`/`monev` menyaring `whereIn('type', NotificationCategory::X->classes())`.

- [ ] **Step 1: Tambah skenario `NOT-INAPP-05/06`** ke `notification.feature` (teks di §Kesesuaian, Given/When/Then dengan reviewer "Rina", pengajuan "P-001").
- [ ] **Step 2: Tulis test yang gagal.**
  - Kontrak: `component('Notification/IndexPage')->has('notifications.data')->where('filter','all')->has('unreadCount')`.
  - `[NOT-INAPP-03]`: pakai komponen baru; `unreadCount` tetap 1 setelah kunjungan.
  - `[NOT-INAPP-05]`: redirect ke `reviewer.assignments.evaluation`.
  - `[NOT-INAPP-06]`: redirect kembali ke `notifications.index`, `read_at` terisi.
  - `filter tak dikenal dianggap semua`; `filter review hanya kategori Review`.
  - `penghitung turun setelah read, read-all, destroy`: `navigation.unreadNotifications` di request berikutnya.
  - `read pada notifikasi orang lain 404` (sudah ada, tetap).
- [ ] **Step 3: Implementasi.**
  - a. `index` → `NotificationInbox::run($user, $request->string('filter'))`.
  - b. `read` → `markAsRead`, `UnreadNotificationCount::forget`, lalu `redirect(NotificationDestination::run(...) ?? route('notifications.index'))`.
  - Hapus halaman legacy dan barisnya di `quality-legacy.txt`, lalu turunkan `max.legacy.files`.
- [ ] **Step 4: Jalankan** `tests/Feature/Notification`, `tests/Unit/Bdd`, `tests/Unit/Architecture`: lulus.
- [ ] **Step 5: Commit** `feat(be): kontrak layar notifikasi`

### Task 4: Query dasbor (BE)

**Files:**
- Create: `app/Data/View/Dashboard/{KpiCardData,DashboardSectionData,PendingWorkData,DeadlineData,ActivityData,DashboardOverviewData}.php`, `app/Queries/Dashboard/{ResearcherDashboardCards,ReviewerDashboardCards,ManagementDashboardCards,DashboardDeadlines,RecentActivity,DashboardOverview}.php`, `app/Queries/Announcement/LatestAnnouncements.php`
- Test: `tests/Feature/Dashboard/{ResearcherDashboardCardsTest,ReviewerDashboardCardsTest,ManagementDashboardCardsTest,DashboardDeadlinesTest,DashboardOverviewTest}.php`

**Interfaces:**
- Produces Data (camelCase ke TS):
  - `KpiCardData(string $key, string $label, string $value, ?string $meta, ?string $href)`
  - `DashboardSectionData(string $key /* researcher|reviewer|management */, string $title, list<KpiCardData> $cards)`
  - `PendingWorkData(int $awaitingReviewer, int $awaitingVerification)`
  - `DeadlineData(string $title, string $closesAt, int $daysLeft, bool $urgent, ?string $href)` dengan `URGENT_DAYS = 5`
  - `ActivityData(string $description, ?string $actorName, string $at)`
  - `DashboardOverviewData(?string $subtitle, list<DashboardSectionData> $sections, ?PendingWorkData $pending, list<ActivityData> $activities, list<DeadlineData> $deadlines, list<AnnouncementSummaryData> $announcements)`
- Produces Query:
  - `ResearcherDashboardCards::run(User): DashboardSectionData` — "Pengajuan Saya". Kartu "Submission Aktif" (`ownedOrJoinedBy` + `whereNull(parent_submission_id)` + `active()`, meta "{n} dalam review" dari UNDER_REVIEW), "Dalam Revisi" (NEEDS_REVISION, "Butuh respon"), "Disetujui" (APPROVED, "Total selesai"), "Notifikasi" (`UnreadNotificationCount`, "Belum dibaca", href `notifications.index`).
  - `ReviewerDashboardCards::run(Reviewer): array{section: DashboardSectionData, deadlines: list<DeadlineData>}` — dari `ReviewerAssignmentCards`, dihitung per **`state`**, bukan per keranjang: `needsAction` juga memuat `discussion_open` (evaluasi sudah selesai, diskusi masih terbuka), dan menghitungnya sebagai "perlu dievaluasi" melanggar `SETTLED_EVALUATION` (BR-MON-02) yang dijaga test legacy. Kartu "Perlu Dievaluasi" (`state === 'evaluation_pending'`, meta "{n} mendekati deadline"), "Evaluasi Selesai" (selain itu, "Total"), "Deadline Terdekat" ("{d} hari"/"Hari ini"/"—", meta judul). Deadline = kartu `evaluation_pending` yang punya `dueDate`, href `reviewer.assignments.evaluation`.
  - `ManagementDashboardCards::run(User): array{section: ?DashboardSectionData, pending: ?PendingWorkData}` — kartu per permission sesuai W42: `submissions.view-all` → dua kartu pengajuan; `users.verify` → "Perlu Verifikasi User" ("Antrian NIDN", `admin.verifications.index`); `reporting.export` → "Export Selesai" ("Minggu ini", `exports.index`); `users.manage` → "User Aktif" (`is_active`, "Semua role", `admin.users.index`). Section `null` bila tak ada kartu.
  - `DashboardDeadlines::run(User, list<DeadlineData> $reviewerDeadlines): list<DeadlineData>` — `submissions.create` → `ResearcherStartOptions` (`closesAt`, "Periode {nama} ditutup"); `periods.manage` → semua periode `SubmissionPeriodGate::isOpen` ("Tutup periode {nama}"). Digabung dengan deadline reviewer, dedupe per judul, urut naik, maks 5.
  - `RecentActivity::run(User): list<ActivityData>` — `AuditScope::forUser($user)->with('causer')->latest()->limit(5)`.
  - `LatestAnnouncements::run(User, int $limit = 3): list<AnnouncementSummaryData>` — `VisibleAnnouncements`, dengan pemetaan yang sama dengan `ReaderAnnouncementList` (pakai ulang factory-nya, jangan menyalin).
  - `DashboardOverview::run(User): DashboardOverviewData` — subtitle = `ResearcherStudyProgram` bila ada. Section reviewer hanya bila `can('reviewers.evaluate')` **dan** baris `Reviewer` ada.

- [ ] **Step 1: Tulis test yang gagal**, menyebut BR di deskripsi.
  - Researcher: anggota tim ikut terhitung (BR-SM-07); REJECTED/WITHDRAWN tidak, DRAFT dan APPROVED ya (Active Submission); anak monev tidak terhitung.
  - Reviewer: port empat aturan `tests/Feature/Reviewer/ReviewerDashboardTest.php` (`not_required` = selesai sejajar `canProceed`; penugasan nonaktif diabaikan; reviewer lain tak tercampur; penugasan tanpa ReviewSummary terhitung); `reviewer tanpa penugasan` → "0"/"—"; `mendekati deadline memakai URGENT_DAYS`.
  - Management: tiap kartu hilang tanpa permission-nya; draft tidak terhitung; ekspor minggu lalu atau milik Operator lain tidak terhitung (BR-RPT-07).
  - Deadlines: urut, maks 5, periode tertutup tidak muncul.
  - Overview: `permission reviewer tanpa baris Reviewer tidak memunculkan section`; peneliti+reviewer mendapat dua section; aktivitas mengikuti `AuditScope` (peneliti tidak melihat entri pengajuan orang lain, BR-RPT-05).
- [ ] **Step 2: Jalankan** `tests/Feature/Dashboard`: gagal.
- [ ] **Step 3: Implementasi** sesuai Interfaces; guard clause, kode BR di komentar.
- [ ] **Step 4: Jalankan, lulus**, plus `composer run analyse:strict`.
- [ ] **Step 5: Commit** `feat(be): query dasbor per section`

### Task 5: `Dashboard/IndexPage` (FE)

**Files:**
- Create: `resources/js/types/dashboard.ts`, `resources/js/lib/dashboardActions.ts`, `resources/js/Pages/Dashboard/IndexPage.vue`, `resources/js/Components/Dashboard/{WelcomeBanner,KpiGrid,ActivityList,DeadlineList,LatestAnnouncements,QuickActions}.vue`
- Test: `tests/frontend/lib/dashboardActions.spec.ts`, `tests/frontend/components/Dashboard/*.spec.ts` (yang berlogika saja), `tests/frontend/pages/DashboardIndexPage.spec.ts`

**Interfaces:**
- Produces: `DashboardIndexPageProps` = bentuk TS dari `DashboardOverviewData` (Task 4), memakai ulang tipe pengumuman dari `types/announcement.ts`.
- Produces: `quickActions(permissions: string[]): QuickAction[]`, `interface QuickAction { label: string; routeName: string; params?: Record<string, string>; icon: LucideIcon; primary: boolean }`. Urutan dan permission:
  - Buat Pengajuan Baru → `user.submissions.create` (`submissions.create`)
  - Lihat Daftar Pengajuan → `user.submissions.index` (`submissions.view-own`)
  - Lihat Penugasan Review → `reviewer.assignments.index`, Riwayat Review → `reviewer.history.index` (`reviewers.evaluate`)
  - Semua Pengajuan → `admin.submissions.index` (`submissions.view-all`)
  - Tugaskan Reviewer → `admin.submissions.index` `{status:'submitted'}` (`reviewers.assign`)
  - Antrian Verifikasi → `admin.verifications.index` (`users.verify`)
  - Kelola Skema → `admin.schemes.index`, Form Builder → `admin.forms.index`, Konfigurasi Umum → `admin.reference.index` (`schemes.manage`)
  - Notifikasi → `notifications.index` (semua)
  Hanya item pertama yang lolos yang `primary`. Tulis `routeName: '…'` literal supaya `FrontendRouteNamesTest` memeriksanya.

- [ ] **Step 1: Tulis test yang gagal.**
  - `dashboardActions`: user dua peran tanpa duplikat; tanpa permission hanya "Notifikasi"; tepat satu `primary`.
  - Page: section dirender sesuai `sections` (tanpa memeriksa peran); banner antrian hanya bila `pending` ada dengan teks "<n> pengajuan butuh reviewer · <m> user menunggu verifikasi NIDN".
  - Page: banner biodata dari shared `biodataStatus` bila `required && !completed`, menaut `user.biodata.index`.
  - `DeadlineList`: kelas mendesak mengikuti `urgent`.
  - EmptyState untuk aktivitas/deadline/pengumuman kosong.
- [ ] **Step 2: Jalankan**: gagal.
- [ ] **Step 3: Implementasi.** Urutan section W45. Kartu memakai `SectionCard` dan primitive `ui/card`; kartu ber-`href` dirender sebagai `Link`.
- [ ] **Step 4: Jalankan, lulus**; lint + typecheck.
- [ ] **Step 5: Commit** `feat(fe): layar dasbor`

### Task 6: Kontrak dasbor, satu route, hapus legacy (BE)

**Files:**
- Create: `app/Http/Controllers/DashboardController.php` (invokable)
- Modify: `routes/web.php` (`dashboard` → controller dengan `['auth','verified','check_biodata']`; `user.dashboard`/`reviewer.dashboard`/`admin.dashboard` → `Route::redirect` ke `/dashboard`), `app/Http/Middleware/CheckBiodata.php` (`ALLOWED_ROUTES` dan target redirect `dashboard`), `app/Queries/Identity/PostLoginDestination.php` (guard clause: bukan `users.manage` + profil belum boleh submit → `verification.status`; selain itu `dashboard`), pemanggil `route('user.dashboard')` di `app/Http/Controllers/Auth/*`, `BiodataController`, `CheckRole` (`'/user/dashboard'` literal), `SubmissionPeriodActiveNotification`, `resources/js/Pages/Auth/VerificationStatusPage.vue`, `resources/js/lib/navigation.ts` ("Statistik" → `admin.stats.form-submission`), test terkait (`AuthenticationTest`, `CheckBiodataGateTest`, `BiodataSubmissionStatusTest`, `SaveBiodataTest`, `AccountVerificationTest`, `EmailVerificationTest`, `StatControllerTest`, `ResearcherWizardPagesTest`)
- Delete: `resources/js/Pages/{DashboardPage,User/DashboardPage,Reviewer/DashboardPage}.vue`, `resources/js/Components/StatisticSection.vue` (hanya dipakai `DashboardPage`; coret dari kolom legacy R1), `UserFormController::dashboard`, `StatController::dashboard`, `SubmissionViewController::reviewerDashboard`/`reviewerOf`, `app/Actions/Review/BuildReviewerDashboard.php`, `app/Queries/Submission/ResearcherDashboardPeriods.php` (beserta test-nya bila ada), `tests/Feature/Reviewer/ReviewerDashboardTest.php` (aturannya sudah diport di Task 4). Route redirect `reviewer.submissions.show` dihapus bila grep tidak menemukan pemakai lain (carry-over memory D1).
- Test: `tests/Feature/Dashboard/DashboardPageTest.php`

- [ ] **Step 1: Tulis test yang gagal.**
  - Kontrak: `component('Dashboard/IndexPage')->has('sections')->has('activities')->has('deadlines')->has('announcements')`.
  - 200 untuk peneliti, reviewer, operator, admin; tamu → login.
  - `dasbor terbuka bagi biodata pending dan menampilkan statusnya`: 200, bukan redirect, dengan shared `biodataStatus`.
  - Tiga nama lama → redirect ke `/dashboard`.
  - `PostLoginDestination`: admin → `dashboard`; peneliti pending → `verification.status`; peneliti aktif → `dashboard`.
- [ ] **Step 2: Jalankan**: gagal.
- [ ] **Step 3: Implementasi + hapus legacy.** Perbarui `quality-legacy.txt` / `max.legacy.files`. Lalu `grep -rn "user.dashboard\|reviewer.dashboard\|admin.dashboard" app` harus kosong; yang tersisa hanya legacy Vue dan route redirect.
- [ ] **Step 4: Jalankan Pest penuh** (container) dan `npm run quality`: lulus.
- [ ] **Step 5: Commit** `feat(be): kontrak dasbor satu halaman`

### Task 7: Catat keputusan, gate penuh, cek browser

**Files:** `docs/design/fe-v2-design.md` (§8 D1 → ☑ dengan rujukan rencana ini; R1 kolom legacy tanpa `StatisticSection`; §9 W37–W46), memory hand-off.

- [ ] **Step 1:** Tulis W37–W46 ke §9 (satu baris per keputusan, isi dari bagian Keputusan di atas, termasuk jawaban pemilik untuk W37/W44 bila sudah ada).
- [ ] **Step 2:** `composer run quality` dan `npm run quality` (container). Laporkan hasilnya apa adanya.
- [ ] **Step 3: Cek browser** (resep memory: `simpas_browser`, `php -S` dari `public/`, Playwright). (a) Peneliti+reviewer: kedua section, aksi cepat tanpa duplikat. (b) Operator: banner antrian, klik "Perlu Penugasan Reviewer" → daftar terfilter. (c) Notifikasi: buka `ReviewerAssigned` → form evaluasi, badge sidebar turun; "Tandai Semua Dibaca" → badge hilang; hapus lewat dialog.
- [ ] **Step 4: Commit** `feat(fe): irisan D1 dasbor dan notifikasi selesai`, lalu sarankan session baru dengan irisan berikutnya (I1).
