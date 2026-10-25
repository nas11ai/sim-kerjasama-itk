# FE v2 — Irisan S2a: Daftar & Detail Pengajuan Peneliti — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Peneliti membuka daftar datar pengajuannya (ketua atau anggota tim) dan detail satu pengajuan, termasuk tampilan Ditolak, di atas `AppLayout`, mengikuti RESEARCHER-02-01, 02-03, 02-12. `User/Submissions/IndexPage` dan `ShowSubmission` legacy terhapus.

**Architecture:** Dua read model baru di `app/Queries/Submission/` (`ResearcherSubmissionList`, `ResearcherSubmissionDetail`) menghasilkan `Data` class di `app/Data/View/Submission/`. Controller hanya merender `Submission/IndexPage` dan `Submission/ShowPage`. Frontend menyusun halaman tipis dari komponen `Components/Submission/*` dan komponen bersama baru `Components/app/{DataTable,PaginationBar,SectionCard,ActionPanel}` + `composables/useFilters`. Frontend tidak memutuskan otorisasi: tombol dibaca dari `can` (`SubmissionAbilitiesData`) dan `editUrl`, skor dari `VisibleReviewerScores`, kunci anggaran dari `BudgetLineItemPolicy`.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, spatie/laravel-activitylog (AuditTrail), Inertia v3 + Vue 3 `<script setup>` + TS, shadcn-vue (`Components/ui/`), Ziggy, Pest, Vitest.

**Spec:** `docs/design/fe-v2-design.md` (§4.1–§4.6, §6, baris S2a di §8). Frame: `../simpas-wireframe/screens/RESEARCHER-02-{01,03,12}/screen.js`; state wajib di `../simpas-wireframe/docs/STATE-COVERAGE.md`.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150; PHP baru ≤ 200, method ≤ 40 baris. Tanpa blok duplikat 6 baris dengan berkas mana pun, **termasuk legacy**. Tulis dari wireframe, jangan menyalin halaman lama.
- Baris pertama `<script setup>` halaman: `// Frame: RESEARCHER-02-0X`. Komponen yang menggambar konsekuensi aturan diberi komentar BR (`<!-- BR-BUD-02 — … -->`).
- Identifier Inggris; komentar Indonesia, prosa komentar maks 3 baris. Kosakata UI dari `02_ubiquitous_language.md`: "Ketua", "Co-Investigator", "Anggota", "Anggota Tim", "Diarsipkan".
- TS tanpa `any`/`console.log`/cast; PHPStan tanpa `@phpstan-ignore`, baseline, `assert()`, inline `@var`, cast. `app/Queries/`, `app/Data/` tier strict.
- Tanpa dependency baru. `@tanstack/vue-table` **tidak** dipakai di S2a (tabel ini tanpa sorting); `DataTable` membungkus `Components/ui/table` saja.
- PostgreSQL: pencarian lewat `whereLike` (ILIKE), bukan `LIKE`.
- Filter, pencarian, paginasi di server lewat `useFilters` (`preserveState: true, replace: true`). Mutasi lewat `router`; tanpa `axios`.
- `config/inertia.php` testing `ensure_pages_exist: true` — `->component('Submission/X')` gagal bila berkas halamannya tidak ada.
- Gate: `composer run quality` + `npm run quality`. Pest di container:
  `docker run --rm --network host -v /home/nas11ai/projects/valtech/sim-kerjasama-itk:/home/nas11ai/projects/valtech/sim-kerjasama-itk -w <worktree> -u 1000:1000 -e DB_PORT=55432 -e DB_PASSWORD=postgres simpas-ci:8.4 ./vendor/bin/pest <path>`.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.
- `User/Submissions/ShowPeriod.vue` dan route `user.submissions.period` **tetap**: langkah 1 wizard (RESEARCHER-02-02, S2b) yang menggantikannya.
- Orphan legacy Vue yang tidak digantikan irisan ini tidak dihapus (keputusan pemilik: bersih-bersih di F99).

## Review Focus

1. **Anggota penelitian yang dinonaktifkan** (`research_members.status != 'active'`) tidak boleh lagi melihat pengajuan itu di daftar (BR-SM-07). → Task 2, test `[SM-ACCESS-03] …anggota nonaktif`.
2. **Mengganti filter saat berada di halaman 3** harus kembali ke halaman 1; parameter `page` lama tidak boleh terbawa sehingga menghasilkan halaman kosong. → Task 1, Vitest `useFilters` "membuang page saat filter berubah".
3. **Pengajuan lama tanpa periode atau tanpa fase** (`submission_period_id`/`form_phase_detail_id` null) harus tetap terbuka dengan `editUrl: null`, bukan 500. → Task 4, test "pengajuan tanpa periode dan fase".
4. **Pengajuan yang ditolak sebelum audit dicatat** (tanpa entri `Pengajuan ditolak`) tetap menampilkan alasan; "Diputuskan oleh" dan "Tanggal" tampil `—`. → Task 4 (`decidedBy`/`decidedAt` null) dan Task 6 (render `—`).
5. **Operator dengan `submissions.view-all`** yang membuka daftar ini hanya melihat pengajuan miliknya/yang ia ikuti, bukan seluruh sistem (daftar ini "Pengajuan saya"; seluruh sistem ada di S4). → Task 2, test "view-all tidak melebarkan daftar".

---

### Task 1: Komponen bersama daftar dan aksi

**Files:**
- Create: `resources/js/types/pagination.ts`, `resources/js/composables/useFilters.ts`, `resources/js/lib/format.ts`
- Create: `resources/js/Components/app/DataTable.vue`, `PaginationBar.vue`, `SectionCard.vue`, `ActionPanel.vue`
- Test: `tests/frontend/composables/useFilters.spec.ts`, `tests/frontend/lib/format.spec.ts`, `tests/frontend/components/app/ActionPanel.spec.ts`

**Interfaces:**
- Produces (TS):
  - `interface Paginated<T> { data: T[]; current_page: number; last_page: number; per_page: number; total: number; from: number | null; to: number | null; prev_page_url: string | null; next_page_url: string | null }` (bentuk `LengthAwarePaginator::toArray()`).
  - `useFilters<T extends Record<string, string>>(routeName: string, initial: T): { filters: T }` — `filters` reaktif; setiap perubahan memanggil `router.get(route(routeName), params, { preserveState: true, replace: true, preserveScroll: true })` dengan `params` = filter yang tidak kosong saja, **tanpa** `page`. Perubahan `search` di-debounce 300 ms, filter lain langsung. Tidak memanggil apa pun saat setup.
  - `formatDate(iso: string | null): string` → `'12 Jan 2026'` (`id-ID`, `day: '2-digit', month: 'short', year: 'numeric'`), `'—'` untuk null. `formatRupiah(amount: number): string` → `'Rp 48.500.000'`.
  - `DataTable.vue` generic `T extends { id: number }`: props `{ rows: T[]; columns: { key: string; label: string }[] }`, scoped slot `#cell-<key>="{ row }"`, slot `#empty` saat `rows` kosong.
  - `PaginationBar.vue`: props `{ page: Paginated<unknown> }`; teks `Menampilkan {from}–{to} dari {total}`, `Link` Sebelumnya/Berikutnya dari `prev_page_url`/`next_page_url` (`preserveState`), tersembunyi bila `last_page <= 1`.
  - `SectionCard.vue`: props `{ title: string; description?: string }`, slot default, slot `#actions`.
  - `ActionPanel.vue`: props `{ actions: ActionItem[] }` dengan `interface ActionItem { key: string; label: string; icon?: LucideIcon; href?: string; onSelect?: () => void; available: boolean; hint: string }` (diekspor dari `types/action.ts`). Tersedia + `href` → `Button as-child` + `Link`; tersedia + `onSelect` → `Button @click`; tidak tersedia → `Button disabled`. `hint` selalu dirender di bawah tombolnya (`.text-xs text-muted-foreground`).

- [ ] **Step 1: Tulis Vitest yang gagal.**
  - `useFilters`: mock `router.get` dan `route` (pola mock di `tests/frontend/composables/useFlashToasts.spec.ts`); case `tidak memanggil router saat setup`, `status berubah → router.get dengan { status: 'draft' } dan opsi preserveState/replace`, `membuang filter kosong` (`{ search: '', status: 'draft' }` → `{ status: 'draft' }`), `membuang page saat filter berubah` (initial `{ search: '', page: '3' }` diubah `search` → params tanpa `page`), `search di-debounce 300 ms` (`vi.useFakeTimers`, dua ketikan → satu panggilan).
  - `format`: `formatDate('2026-01-12T08:00:00')` → `'12 Jan 2026'`, `formatDate(null)` → `'—'`, `formatRupiah(48500000)` → `'Rp 48.500.000'` (normalisasi spasi non-breaking di assertion).
  - `ActionPanel`: `menautkan aksi tersedia ke href`, `memanggil onSelect`, `menonaktifkan aksi tidak tersedia tanpa href aktif`, `selalu menampilkan hint`.
- [ ] **Step 2: Jalankan, pastikan gagal.** `npx vitest run tests/frontend/composables/useFilters.spec.ts tests/frontend/lib/format.spec.ts tests/frontend/components/app/ActionPanel.spec.ts` → FAIL (modul tidak ada).
- [ ] **Step 3: Implementasikan** berkas di atas. `useFilters` memakai `watch(filters, …, { deep: true })` dan membandingkan `search` lama/baru untuk memutuskan debounce.
- [ ] **Step 4: Jalankan, pastikan lulus.** Perintah yang sama → PASS.
- [ ] **Step 5: Commit** `feat(fe): komponen tabel, paginasi, panel aksi, dan useFilters`

### Task 2: Read model daftar pengajuan peneliti

**Files:**
- Create: `app/Queries/Submission/ResearcherSubmissionList.php`, `app/Data/View/Submission/SubmissionRowData.php`
- Modify: `app/Support/Reporting/SubmissionVisibility.php` (Extract Method `ownedOrJoinedBy`), `app/Actions/FormSubmission/SubmitFormSubmission.php` dan `app/Actions/Review/RejectSubmission.php` (konstanta deskripsi audit)
- Test: `tests/Feature/Submission/ResearcherSubmissionListTest.php`

**Interfaces:**
- Produces (PHP):
  - `SubmissionVisibility::ownedOrJoinedBy(User $user): Builder<FormSubmission>` — klausa `submitted_by` ∪ anggota aktif yang sekarang ada di dalam `for()`; `for()` memakainya untuk cabang non-view-all. Tidak mengenal `view-all`.
  - `SubmitFormSubmission::AUDIT_DESCRIPTION = 'Pengajuan disubmit'`, `RejectSubmission::AUDIT_DESCRIPTION = 'Pengajuan ditolak'`; kedua `AuditTrail::record` memakai konstanta itu.
  - `SubmissionRowData(int $id, string $title, ?string $scheme, ?string $period, SubmissionStatusData $status, ?string $submittedAt, bool $isLead, bool $isArchived)`; `title` = `form.title`, `submittedAt` = ISO 8601 entri audit `AUDIT_DESCRIPTION` **pertama** (subquery `min(created_at)` pada `activity_log`, `subject_type = FormSubmission::class`, `log_name = AuditTrail::LOG_NAME`), `isLead` = `submitted_by === viewer`, `isArchived` = `resolveIsArchived()`.
  - `ResearcherSubmissionList::run(User $viewer, array{search: string, status: string, period: string} $filters): array{submissions: LengthAwarePaginator<int, SubmissionRowData>, statusOptions: list<array{value: string, label: string}>, periodOptions: list<array{value: string, label: string}>}` — basis `ownedOrJoinedBy`, eager load `form:id,title`, `scheme:id,name`, `submissionPeriod`; `search` → `whereHas('form', whereLike('title', "%…%"))`; `status` hanya bila termasuk nilai `SubmissionStatusChoices::run()`; `period` → `submission_period_id` (abaikan bila bukan angka); urut `updated_at desc`; 15 per halaman; `withQueryString()`; baris lewat `->through(…)`. `statusOptions` = `SubmissionStatusChoices::run()`; `periodOptions` = periode yang dipakai pengajuan dasar (tanpa filter), `value` = id string, `label` = `name`, urut terbaru.

- [ ] **Step 1: Tulis Pest yang gagal** (`ResearcherSubmissionListTest.php`, panggil `ResearcherSubmissionList::run` langsung, filter default `['search' => '', 'status' => '', 'period' => '']`):
  - `[SM-ACCESS-01] memuat pengajuan yang ia ketuai` → satu baris, `isLead` true
  - `[SM-ACCESS-02] memuat pengajuan yang ia ikuti sebagai anggota aktif` → `isLead` false
  - `[SM-ACCESS-03] tidak memuat pengajuan peneliti lain` dan `[SM-ACCESS-03] tidak memuat pengajuan setelah keanggotaannya nonaktif`
  - `view-all tidak melebarkan daftar` → user ber-`submissions.view-all` dengan satu pengajuan sendiri dari tiga di DB → total 1
  - `[SM-SUBMIT-03] dua draft ke skema berbeda tampil bersamaan` → total 2, keduanya `status.key === 'draft'`
  - `[SM-ARCHIVE-01] draft dari periode tertutup ditandai diarsipkan` (`is_force_closed => true`) → `isArchived` true
  - `tanggal submit dari entri audit submit pertama` (dua entri `AuditTrail::record(SubmitFormSubmission::AUDIT_DESCRIPTION, …)` dengan `created_at` berbeda via `travelTo`) → yang lebih awal; draft → null
  - `mencari judul tanpa peka huruf` (`Biochar Kaltim`, cari `biochar`) → 1; `menyaring status` dan `menyaring periode`; `mengabaikan status yang tidak dikenal` (`'x'`) → semua baris
  - `menawarkan periode dari pengajuannya sendiri saja`
- [ ] **Step 2: Jalankan, pastikan gagal.** `… ./vendor/bin/pest tests/Feature/Submission/ResearcherSubmissionListTest.php` → FAIL (kelas tidak ada).
- [ ] **Step 3: Implementasikan** `ownedOrJoinedBy`, konstanta audit, `SubmissionRowData::fromModel(FormSubmission $s, User $viewer): self` (baca `submitted_at` hasil subquery lewat `getAttribute` + guard `is_string`), lalu Query.
- [ ] **Step 4: Jalankan test ini plus test lama yang menyentuh berkas yang diubah** — `ResearcherSubmissionListTest.php`, `tests/Feature/Reporting`, `tests/Feature/Audit`, `tests/Feature/Submission`, `tests/Feature/Review` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model daftar pengajuan peneliti`

### Task 3: Halaman Daftar Pengajuan (RESEARCHER-02-01)

**Files:**
- Modify: `app/Http/Controllers/SubmissionViewController.php` (`userIndex`), `routes/web.php` (route `user.submissions.index` + `can:submissions.view-own`)
- Delete: `app/Queries/Submission/ResearcherPeriodOverview.php`, `resources/js/Pages/User/Submissions/IndexPage.vue`
- Create: `resources/js/types/submission-page.ts`, `resources/js/Pages/Submission/IndexPage.vue`, `resources/js/Components/Submission/SubmissionTable.vue`
- Test: `tests/Feature/Submission/SubmissionIndexPageTest.php`; Modify `tests/Feature/Submission/SubmitUniquenessTest.php:129`

**Interfaces:**
- Consumes: Task 1 (`DataTable`, `PaginationBar`, `useFilters`, `formatDate`, `Paginated`), Task 2 (`ResearcherSubmissionList::run`).
- Produces (TS, `types/submission-page.ts`): `FilterOption { value: string; label: string }`, `SubmissionRow` (cermin `SubmissionRowData`, `status: SubmissionStatusView`), `SubmissionIndexFilters { search: string; status: string; period: string }`, `SubmissionIndexPageProps { submissions: Paginated<SubmissionRow>; filters: SubmissionIndexFilters; statusOptions: FilterOption[]; periodOptions: FilterOption[] }`.
- `userIndex` mengirim `filters` dengan ketiga kunci selalu ada (string kosong bila tak diisi), lalu `...ResearcherSubmissionList::run($viewer, $filters)`.

- [ ] **Step 1: Tulis Pest kontrak yang gagal**: `peneliti membuka Submission/IndexPage` → 200, `->component('Submission/IndexPage')->has('submissions.data', 1)->has('filters')->has('statusOptions')->has('periodOptions')`; `user tanpa submissions.view-own ditolak` → 403. Ubah `SubmitUniquenessTest` ke `Submission/IndexPage` dan, bila ia membaca `submissionPeriods`, ke `submissions.data`.
- [ ] **Step 2: Jalankan, pastikan gagal** (`SubmissionIndexPageTest.php`, `SubmitUniquenessTest.php`).
- [ ] **Step 3: Implementasikan controller + route**, hapus `ResearcherPeriodOverview`.
- [ ] **Step 4: Tulis halaman dan tabel.** `IndexPage.vue` (`// Frame: RESEARCHER-02-01`, `AppLayout`, `PageHeader` judul "Daftar Pengajuan", subjudul "Semua pengajuan di mana Anda menjadi ketua atau anggota tim."), baris filter: `Input` cari (placeholder "Cari judul pengajuan..."), `Select` Status ("Semua status" = `''`), `Select` Periode ("Semua periode"); `SubmissionTable` + `PaginationBar`. Kolom: Judul (tautan `route('user.submissions.show', id)`, badge outline "Anggota Tim" bila `!isLead` dengan `title` "Anda anggota tim pada pengajuan ini — hanya ketua yang dapat mengubah."), Skema, Status (`StatusBadge` + badge outline "Diarsipkan" bila `isArchived`, `title` "Draft dari periode yang sudah ditutup — tidak dapat diedit lagi." — `<!-- BR-SM-10 -->`), Tanggal Submit (`formatDate`), aksi "Detail". `#empty`: `Empty` "Belum ada pengajuan" (dengan filter aktif: "Tidak ada pengajuan yang cocok dengan filter"). Tombol "Buat Pengajuan Baru" **tidak** digambar (S2b).
- [ ] **Step 5: Hapus `User/Submissions/IndexPage.vue`**, jalankan Pest Step 1 + `npx vue-tsc --noEmit` + `npx eslint resources/js/Pages/Submission resources/js/Components/Submission` → PASS.
- [ ] **Step 6: Commit** `feat(fe): daftar pengajuan peneliti v2`

### Task 4: Read model detail pengajuan

**Files:**
- Create: `app/Queries/Submission/ResearcherSubmissionDetail.php`, `app/Queries/Submission/SubmissionHistory.php`, `app/Queries/Submission/SubmissionBudgetSummary.php`
- Create (`app/Data/View/Submission/`): `SubmissionDetailData.php`, `SubmissionAnswerData.php`, `SubmissionMemberData.php`, `SubmissionTargetOutputData.php`, `SubmissionBudgetData.php`, `BudgetItemData.php`, `SubmissionHistoryEntryData.php`, `SubmissionDecisionData.php`
- Modify: `app/Policies/BudgetLineItemPolicy.php` (`isLockedByState` → `public static function lockedByState(FormSubmission $submission): bool`)
- Test: `tests/Feature/Submission/ResearcherSubmissionDetailTest.php`

**Interfaces:**
- Produces (PHP, semua properti camelCase):
  - `SubmissionDetailData(int $id, string $title, ?string $scheme, ?string $period, ?string $trl, string $leadName, SubmissionStatusData $status, ?string $submittedAt, bool $isArchived, string $viewerRole, string $viewerRoleLabel, ?string $editUrl)`. `viewerRole` ∈ `lead_researcher|co_investigator|member|viewer`, label `Ketua|Co-Investigator|Anggota|Tidak terlibat`. `editUrl` = `route('user.form-phase', [periodId, formPhaseId])` hanya bila `can('update')` dan periode + fase ada, selain itu null. `trl` = label TRL bila ada.
  - `SubmissionAnswerData(string $label, ?string $value, bool $isFile)` — field terurut `order`; nilai `select`/`radio` diganti label opsinya, `checkbox` → `Ya`/`Tidak`; `isFile` dari nama `fieldType` `file`.
  - `SubmissionMemberData(string $name, string $roleLabel)` — ketua dulu, lalu anggota **aktif**.
  - `SubmissionTargetOutputData(string $type, ?string $description)` — `type` = nama dari `TargetOutputTypeChoices`, fallback kode.
  - `SubmissionBudgetData(list<BudgetItemData> $items, int $total, bool $editable, ?string $lockReason)`; `BudgetItemData(string $component, string $item, int $volume, string $unit, int $unitPrice, int $total)`. `editable` = `can('update-budget')`; `lockReason` = null bila editable, `'terminal'` bila `BudgetLineItemPolicy::lockedByState`, selain itu `'read_only'`.
  - `SubmissionHistoryEntryData(string $description, ?string $actor, string $at)`; `SubmissionHistory::run(FormSubmission): list<SubmissionHistoryEntryData>` — `AuditEntry::auditOnly()` subjek pengajuan ini, terbaru dulu, aktor = nama causer.
  - `SubmissionDecisionData(?string $reason, ?string $decidedBy, ?string $decidedAt)` — hanya untuk status `Rejected`; `decidedBy/At` dari entri `RejectSubmission::AUDIT_DESCRIPTION` terbaru.
  - `ResearcherSubmissionDetail::run(User $viewer, FormSubmission $submission): array{submission: SubmissionDetailData, answers: list<SubmissionAnswerData>, members: list<SubmissionMemberData>, targetOutputs: list<SubmissionTargetOutputData>, budget: SubmissionBudgetData, history: list<SubmissionHistoryEntryData>, decision: SubmissionDecisionData|null, scores: array{visible: bool, reason: string|null, scores: list<Score>}, phaseAccess: PhaseAccess, printableDocuments: list<array{value: string, label: string}>, can: SubmissionAbilitiesData}` — `scores` = `VisibleReviewerScores::run`, `phaseAccess` = `PhaseAccessGate::presentFor(…, $viewer->can('viewOverrideReason', $submission))`, `printableDocuments` = `PrintableDocumentChoices::run`. Komentar BR-REV-12 dan BR-FE-08 dipindah dari `ResearcherSubmissionView` bersama kodenya.

- [ ] **Step 1: Tulis Pest yang gagal** (panggil Query langsung):
  - `peran penonton: ketua, co-investigator, anggota, tidak terlibat` (dataset 4 baris → `viewerRole` + label)
  - `[REV-VIS-04] skor ditahan selama UNDER_REVIEW` → `scores.visible` false, `reason` tidak null
  - `[REV-VIS-06] skor terbuka dan keputusan tercatat setelah REJECTED` → `scores.visible` true; `decision.reason` = `rejection_reason`, `decision.decidedBy` = nama operator, `decidedAt` tidak null (entri lewat `AuditTrail::record(RejectSubmission::AUDIT_DESCRIPTION, $s, $operator)`)
  - `penolakan tanpa entri audit tetap membawa alasan` → `decidedBy`/`decidedAt` null
  - `decision null untuk status selain REJECTED`
  - `[BUD-LOCK-01] anggaran terkunci pada status terminal` → `editable` false, `lockReason` `'terminal'`; `[BUD-WHO-03] anggota biasa hanya membaca anggaran` → `'read_only'`; ketua draft → `editable` true, `lockReason` null; `total` = jumlah `total` item
  - `editUrl hanya bila boleh disunting`: ketua draft → `route('user.form-phase', …)`; anggota → null
  - `pengajuan tanpa periode dan fase tetap terbaca` → `editUrl` null, `period` null, tanpa exception
  - `jawaban select ditampilkan sebagai label opsi` dan `field berkas ditandai isFile`
  - `anggota nonaktif tidak tercantum`
  - `riwayat memuat entri audit pengajuan ini, terbaru dulu, tanpa entri pengajuan lain`
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan** Data class, dua Query pembantu, lalu `ResearcherSubmissionDetail`; ubah `BudgetLineItemPolicy` (Extract Method → public static, `update` tetap memakainya).
- [ ] **Step 4: Jalankan** test ini + `tests/Feature/Budget` + `SuperAdminSubmitDivergenceTest.php` → PASS.
- [ ] **Step 5: Commit** `feat(be): read model detail pengajuan peneliti`

### Task 5: Halaman Detail Pengajuan (RESEARCHER-02-03)

**Files:**
- Modify: `app/Http/Controllers/SubmissionViewController.php` (`userShowSubmission` → `Submission/ShowPage`, `...ResearcherSubmissionDetail::run`)
- Delete: `app/Queries/Submission/ResearcherSubmissionView.php`, `resources/js/Pages/User/Submissions/ShowSubmission.vue`
- Create: `resources/js/Pages/Submission/ShowPage.vue`; `resources/js/Components/Submission/{InfoSection,MembersSection,TargetOutputsSection,BudgetSection,FilesSection,HistoryTimeline,SubmissionActions}.vue`; tipe di `types/submission-page.ts`
- Test: `tests/Feature/Submission/SubmissionShowPageTest.php`, `tests/frontend/components/Submission/SubmissionActions.spec.ts`; Modify `tests/Feature/Submission/SubmissionAbilitiesPayloadTest.php`, `tests/Feature/FormEngine/PhaseOverrideTest.php:242,272`

**Interfaces:**
- Consumes: Task 1 (`SectionCard`, `ActionPanel`, `ActionItem`, `InfoGrid`, `formatDate`, `formatRupiah`), Task 4 (bentuk props).
- Produces (TS): `SubmissionDetail`, `SubmissionAnswer`, `SubmissionMember`, `SubmissionTargetOutput`, `SubmissionBudget`, `BudgetItem`, `SubmissionHistoryEntry`, `SubmissionDecision`, `ReviewerScore` (`{ reviewer: string; reviewer_type: string; submitted_at: string | null; answers: { label: string; value: string | null }[] }`), `PhaseAccess` (cermin `@phpstan-type PhaseAccess`), `SubmissionAbilities { view; update; submit; resubmit; withdraw: boolean }`, `SubmissionShowPageProps` (kunci sama dengan `ResearcherSubmissionDetail::run`).
- `SubmissionActions.vue`: props `{ submission: SubmissionDetail; can: SubmissionAbilities; printableDocuments: FilterOption[] }`, menyusun `ActionItem[]`:
  - edit: label `can.resubmit ? 'Mulai Revisi' : 'Edit Pengajuan'`, `available: can.update && submission.editUrl !== null`, `href: editUrl`; hint `submission.isArchived ? 'Draft dari periode yang sudah ditutup — tidak dapat diedit lagi.' : can.resubmit ? 'Tersedia saat status Perlu Revisi.' : 'Tersedia saat pengajuan masih berstatus Draft.'`
  - satu aksi per dokumen cetak: `Cetak <label>`, `onSelect` → `router.post(route('submissions.print', [id, value]), {}, { preserveScroll: true })`, hint "Berkas cetak masuk ke daftar ekspor Anda."
  - bila `!can.withdraw`: `AppBanner tone="info"` "Untuk menarik pengajuan, hubungi Operator LPPM." (`<!-- BR-SM-11 -->`)
  - **Tidak pernah** aksi Submit (BR-SM-04: submit ada di wizard, S2b).

- [ ] **Step 1: Tulis test yang gagal.**
  - Pest kontrak: `pemilik membuka Submission/ShowPage` → `component('Submission/ShowPage')->has('submission')->has('can')->has('budget')->has('scores')->has('history')`; `[SM-ACCESS-03] peneliti lain ditolak` → 403. Pindahkan `SubmissionAbilitiesPayloadTest` dan dua assertion `PhaseOverrideTest` ke komponen baru; jalur prop `abilities.*` → `can.*`, `phaseAccess.*` tetap. **Tidak** ada assertion yang dihapus.
  - Vitest `SubmissionActions`: `menautkan Edit Pengajuan ke editUrl saat can.update`, `menamai aksi Mulai Revisi saat can.resubmit`, `[SM-ARCHIVE-01] draft terarsip: edit nonaktif dengan alasan arsip`, `[SM-SUBMIT-10] [SM-SUBMIT-11] tidak pernah menawarkan Submit`, `[SM-WITHDRAW-02] peneliti diarahkan ke Operator untuk penarikan`, `cetak memanggil router.post ke submissions.print`.
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan controller**, hapus `ResearcherSubmissionView`. `grep -rn ResearcherSubmissionView app tests` harus kosong.
- [ ] **Step 4: Tulis komponen dan halaman.** `ShowPage.vue` (`// Frame: RESEARCHER-02-03, RESEARCHER-02-12`): `PageHeader` (back "Daftar Pengajuan" → `user.submissions.index`, judul = `submission.title`, subjudul `"{scheme} · Ketua: {leadName}"`, `#badge` `StatusBadge`); banner perpanjangan bila `phaseAccess.override?.status === 'active'`: "Akses fase ini diperpanjang Operator sampai {formatDate(expires_at)}." (`<!-- BR-FE-08 — alasan hanya untuk Operator/Admin -->`); grid dua kolom `lg:grid-cols-[1fr_20rem]`. Kiri `Tabs`: Informasi (`InfoSection`: InfoGrid Judul/Skema/Periode/TRL/Tanggal Submit + jawaban non-berkas), Anggota, Target Luaran, Anggaran (`BudgetSection`: tabel komponen/item/volume/satuan/harga/total, Total Anggaran, "Status Anggaran" `Dapat diubah` / `Terkunci — hanya baca` (`terminal`, `<!-- BR-BUD-02 -->`) / `Hanya baca` (`read_only`, `<!-- BR-BUD-09 -->`), catatan "Anggaran terkunci otomatis setelah pengajuan disetujui, ditolak, atau ditarik."), Berkas (`FilesSection`: jawaban `isFile`, nama berkas saja, tanpa tombol unduh), Riwayat (`HistoryTimeline`, semua entri). Kanan: `SectionCard "Aksi"` + `SubmissionActions`, `SectionCard "Timeline Status"` + `HistoryTimeline` 3 entri teratas. Setiap tab kosong memakai `Empty` dengan kalimat sendiri.
- [ ] **Step 5: Hapus `User/Submissions/ShowSubmission.vue`**, jalankan test Step 1 + `npx vue-tsc --noEmit` → PASS.
- [ ] **Step 6: Commit** `feat(fe): detail pengajuan peneliti v2`

### Task 6: Skor reviewer dan tampilan Ditolak (RESEARCHER-02-12)

**Files:**
- Create: `resources/js/Components/Submission/ScoreSection.vue`, `DecisionSection.vue`
- Modify: `resources/js/Pages/Submission/ShowPage.vue`
- Test: `tests/frontend/components/Submission/ScoreSection.spec.ts`, `DecisionSection.spec.ts`

**Interfaces:**
- Consumes: Task 5 (`ReviewerScore`, `SubmissionDecision`).
- `ScoreSection.vue`: props `{ scores: { visible: boolean; reason: string | null; scores: ReviewerScore[] } }`. `visible` false → `AppBanner tone="info"` berisi `reason` (`<!-- BR-REV-12 — skor ditahan sampai keputusan final -->`); true + kosong → "Belum ada evaluasi yang disubmit."; true → `SectionCard "Hasil Evaluasi Reviewer"` dengan `InfoGrid` per reviewer (label = `reviewer`, item = jawaban) dan catatan "Skor evaluasi baru terbuka setelah pengajuan dinyatakan disetujui atau ditolak."
- `DecisionSection.vue`: props `{ decision: SubmissionDecision }`. `AppBanner tone="denial"`: "Pengajuan ini ditolak Operator LPPM pada {formatDate(decidedAt)}. Penolakan selalu merupakan keputusan manual — tidak pernah terjadi otomatis dari hasil penilaian reviewer." (`<!-- BR-REV-07 -->`; tanpa `decidedAt` kalimat pertama menjadi "Pengajuan ini ditolak Operator LPPM."); `SectionCard "Alasan Penolakan"` dengan InfoGrid "Diputuskan oleh" / "Tanggal" (`—` bila null) dan alasan `whitespace-pre-line`; `SectionCard "Yang Dapat Anda Lakukan"` + `AppBanner tone="info"` "Pengajuan yang ditolak tidak dapat diubah atau dikirim ulang. Anda tetap dapat mengajukan proposal baru ke skema yang sama pada periode ini."
- `ShowPage`: `DecisionSection` di atas Tabs bila `decision !== null`; `ScoreSection` di kolom kanan.

- [ ] **Step 1: Tulis Vitest yang gagal**: `[REV-VIS-04] [RPT-PRINT-03] menampilkan alasan penahanan, tanpa skor`, `[REV-VIS-05] menampilkan skor per reviewer`, `[REV-VIS-06] menampilkan tanggal dan pemutus penolakan`, `menampilkan tanda pisah bila pemutus tidak tercatat`, `tidak merender alasan sebagai HTML` (`reason: '<b>x</b>'` → teks literal).
- [ ] **Step 2: Jalankan, pastikan gagal.**
- [ ] **Step 3: Implementasikan dan pasang di `ShowPage`.**
- [ ] **Step 4: Jalankan** `npx vitest run tests/frontend/components/Submission` → PASS.
- [ ] **Step 5: Commit** `feat(fe): skor reviewer dan tampilan pengajuan ditolak`

### Task 7: Tutup irisan

**Files:**
- Modify: `quality-legacy.txt`, `quality-budget.txt` (`max.legacy.files`), `tests/Unit/Architecture/FrontendRouteNamesTest.php` (bila ada nama route yang hanya dipakai halaman terhapus), `.github/CODING_STANDARDS.md` (§Utang), `docs/design/fe-v2-design.md` (§8, §9), memori hand-off

- [ ] **Step 1:** Hapus baris `User/Submissions/IndexPage.vue` dan `ShowSubmission.vue` dari `quality-legacy.txt`, turunkan `max.legacy.files` sebanyak baris yang hilang. Berkas baru tidak boleh masuk daftar itu.
- [ ] **Step 2:** `CODING_STANDARDS.md` §Utang: hapus baris `Gate::before` Super Admin (sudah terselesaikan ADR 0015, ditegakkan `SuperAdminSubmitDivergenceTest`). Ini memenuhi syarat §4.4 dokumen desain.
- [ ] **Step 3:** `fe-v2-design.md`: S2a → `☑`; kolom legacy S2b ditambah `User/Submissions/ShowPeriod` + route `user.submissions.period`; §9 ditambah:
  - **W7**: "Tanggal Submit" tanpa kolom, diturunkan dari entri audit `Pengajuan disubmit` pertama.
  - **W8**: "Buat Pengajuan Baru" (S2b), "Tambah Luaran" (O1), tombol ekspor RPT-EXPORT-08/09 (R1; batas scope-nya sudah dijaga `SubmissionVisibility`), dan unduhan berkas (belum ada route; tombol legacy tanpa href; S2b, wizard upload ADR 0012) tidak digambar di S2a.
  - **W9**: Edit → RESEARCHER-02-04 dan Mulai Revisi → 02-05 (S2c) sementara menaut ke `user.form-phase` legacy.
  - **W10**: denial SM-ACCESS-03 tampil sebagai halaman 403 bawaan Laravel, bukan shell v2 → halaman error Inertia di F99.
- [ ] **Step 4:** Carry-over S2a: cek di browser bahwa toast tampil setelah navigasi legacy → v2 dengan flash (mis. `submissions.print` dari detail lalu kembali ke daftar). Kalau tidak bisa dijalankan di environment ini, laporkan apa adanya dan pindahkan item ke S2b.
- [ ] **Step 5:** `composer run quality` dan `npm run quality` (Pest lewat container). Baca hasilnya; laporkan kegagalan apa adanya.
- [ ] **Step 6:** Perbarui memori hand-off FE v2 (S2a selesai; item S2b: tombol Buat Pengajuan Baru di daftar, unduhan berkas, hapus `ShowPeriod`; F99: halaman error Inertia).
- [ ] **Step 7: Commit** `feat(fe): irisan S2a daftar dan detail pengajuan peneliti selesai`
