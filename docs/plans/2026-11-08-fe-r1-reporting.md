# FE v2 — Irisan R1: Statistik, Export, Audit Sistem — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ketiga frame laporan menjadi layar v2. `Reporting/StatsPage` (OPERATOR-07-01) menggantikan empat halaman statistik legacy. Atas permintaan pemilik, layar ini menjawab tiga pertanyaan: **apa yang sedang terjadi**, **apa yang sudah terjadi**, dan **apa tindakan berikutnya** (W152). `Reporting/ExportPage` (07-02) menyediakan kelima jenis ekspor DDD dengan filter dan format Excel/CSV. `Reporting/AuditPage` (07-03) menampilkan kolom, filter, dan alamat IP seperti frame.

**Architecture:** Pola O1/M1. Controller tipis → Query (`app/Queries/Reporting/*`, `implements ChangesNoState`) → `Data` di `app/Data/View/Reporting/*` → Inertia. Setiap angka statistik berangkat dari **satu basis** `ReportingSubmissions::base(StatisticsFilters)`, yaitu induk terkirim tanpa laporan monev, sama dengan baris `OperatorSubmissionList` (preseden W42). Akibatnya angka di statistik dan daftar yang dituju drill-down tidak pernah berselisih. Ekspor: satu `ExportDefinition` per jenis (Replace Conditional with Polymorphism menggantikan `match` di `ExportType`). Scope tetap dari `SubmissionVisibility`/`AuditScope` (BR-RPT-10). Audit: IP ditangkap middleware ke `Context` tersembunyi, lalu ditulis `AuditTrail` ke kolom baru.

**Tech Stack:** Laravel 13 / PHP 8.4, spatie/laravel-data, lorisleiva/laravel-actions, spatie/laravel-activitylog, maatwebsite/excel (sudah terpasang), Inertia + Vue 3 `<script setup>` + TS, shadcn-vue (`ui/chart-bar` di atas `@unovis/vue`, sudah terpasang), Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/fe-v2-design.md` §4.2–§4.5, baris R1 di §8, K1/K3 di §9. Frame `../simpas-wireframe/screens/{OPERATOR-07-01,OPERATOR-07-02,OPERATOR-07-03}/screen.js` + bagiannya di `docs/FRAME-AUDIT.md` / `docs/STATE-COVERAGE.md` (07-01 sengaja tanpa skenario; 07-02 enam state; 07-03 lima state). DDD `ddd/supporting/05_reporting.md` (BR-RPT-01..12, tabel Output Types). BDD `bdd/supporting/reporting.feature`, `bdd/supporting/audit.feature`. UX `ux/ia/07_reporting/index.md`, `ux/ux-patterns/07_reporting/index.md`. Skill `dataviz` (bentuk, warna, dan validator palet).

**Ukuran:** irisan besar, dipecah di §8 menjadi empat session:
- **R1-1 = Task 1–4**: basis statistik, tab Ringkasan (panel frame), dan penghapusan statistik legacy.
- **R1-2 = Task 5–7**: tab Saat Ini, Riwayat, dan Tindak Lanjut (W152).
- **R1-3 = Task 8–10**: ekspor.
- **R1-4 = Task 11–13**: audit dan penutupan irisan.

## Kesesuaian dengan DDD dan BDD (diperiksa 2026-11-08)

Ke-11 skenario `reporting.feature` dan ke-7 skenario `audit.feature` **sudah terikat** test Pest (`tests/Feature/Export/ExportTest.php`, `tests/Feature/Audit/AuditTrailTest.php`, `tests/Unit/Audit/AuditCoverageTest.php`). `pending.txt` tidak memuatnya. Backend ekspor dan audit sudah menegakkan ambang, retry, signed URL, retensi, notifikasi, dan append-only. Yang tertinggal dari DDD dan frame:

| Temuan | Sumber | Perubahan |
| --- | --- | --- |
| Hanya 2 dari 5 jenis ekspor (Rekap Submission, Daftar Luaran) | DDD tabel *Export Tabular* | Detail Anggaran, Rekap Monev, Audit Log ditambahkan (Task 8) |
| Isi ekspor = `attributesToArray()` mentah: semua kolom tabel ikut (JSON `rules`, flag internal, id asing), bukan kolom DDD | DDD *Rekap Submission: id, judul, status, scheme, lead researcher, tanggal submit, total budget* | Kolom eksplisit per jenis (W154, Task 8) |
| Tanpa filter rentang tahun / skema / status | DDD activity diagram, IA, frame | `ExportFilters` disimpan di `export_jobs.filters` (Task 8) |
| CSV saja; `maatwebsite/excel` terpasang tapi tak dipakai (`App\Exports\SubmissionSummaryExport` placeholder) | DDD "Package: maatwebsite/laravel-excel", frame `Excel / CSV` | Format `xlsx` (default) / `csv`; placeholder dihapus (Task 8) |
| Entri audit tidak menyimpan IP | DDD *Audit Log: siapa, aksi apa, kapan, dari IP mana*, frame kolom IP Address | Kolom `activity_log.ip_address` (W155, Task 11) |
| Statistik: empat layar per tabel, tanpa filter tahun/periode/skema, tanpa luaran per tipe dan anggaran disetujui | DDD tabel *Statistik Dashboard*, frame 07-01 | Satu layar ber-tab (Task 1–7) |

**Skenario baru** (ditulis di `.feature` pada commit yang sama dengan test Pest-nya; BddCoverageTest):
- `[RPT-EXPORT-12]` Filter ekspor tidak melebarkan scope peneliti (`@BR-RPT-10 @M5 @RESEARCHER-02-01 @boundary`): Budi memfilter status APPROVED tanpa skema; hasil memuat P-001, tidak memuat P-007 — Task 8.
- `[RPT-EXPORT-13]` Ambang dihitung dari baris setelah filter (`@BR-RPT-02 @M5 @OPERATOR-07-02 @boundary`): 101 pengajuan, filter skema menyisakan 40 → ekspor selesai langsung — Task 8.
- `[RPT-AUDIT-08]` Entri audit mencatat alamat IP pelaku (`@BR-RPT-09 @M5 @OPERATOR-07-03 @happy`): submit dari 10.10.2.14 → entri "Pengajuan disubmit" ber-IP 10.10.2.14 — Task 11.

Tidak ada amandemen DDD. OPERATOR-07-01 tetap tanpa skenario (`bdd/README.md`). Panel tambahan W152 hanya membaca data (BR-RPT-01), tidak menambah aturan bisnis, dan tidak menetapkan ambang atau SLA karangan (§2 non-tujuan).

## Keputusan penulis rencana (dicatat ke §9 di Task 4, 7, 10, 13)

- **W148 — Siapa membuka statistik.** Gate `view-statistics` (`app/Policies/StatisticsPolicy::view`) = `reporting.export` ∨ `reporting.view-audit-log`. Ini pemetaan `reporting.view` di §4.4 K1, sama dengan navigasi, sehingga menu dan route tidak berselisih. Daftar yang memuat **nama** pengajuan (tab Tindak Lanjut) juga butuh `submissions.view-all` (BR-SM-07). Tanpa izin itu, kartu tetap menampilkan angka, tetapi daftarnya disembunyikan (`can.viewSubmissions`).
- **W149 — Definisi "Submission" di statistik.** Pengajuan induk (`parent_submission_id` null), `is_submitted`, tanpa Draft (BR-RPT-06 legacy, `StatisticsAccuracyTest`). WITHDRAWN tetap dihitung. Tahun = tahun **submit pertama** (`FirstSubmitAt`, W7). Periode/skema = `submission_period_id`/`scheme_id`. Fakultas/prodi = unit **pengaju** di pohon organisasi (`UnitFaculty`, aturan legacy yang dipertahankan). Filter yang sama ditambahkan ke `OperatorSubmissionList` (param `year`), supaya drill-down membuka daftar yang barisnya sama dengan angkanya.
- **W150 — Definisi KPI.**
  - *Total Submission*: basis W149.
  - *Total Anggaran Disetujui*: `SUM(budget_line_items.total)` (tanpa baris soft-deleted) pada basis ber-state Approved.
  - *Luaran Terkumpul*: `research_outputs` pada basis.
  - *Reviewer Aktif*: `ReviewersWithinTenure` ∧ `is_active`, definisi yang sama dengan `ReviewerCandidates`. Meta "Internal n · Eksternal m". Tidak terfilter tahun, karena ini keadaan sekarang.
  - *Tingkat Persetujuan* (KPI kelima): Approved ÷ (Approved + Rejected). WITHDRAWN tidak masuk penyebut karena bukan keputusan review. Penyebut 0 → "—".
  - DDD "Total anggaran per periode" = KPI anggaran dengan filter Periode, ditambah kolom anggaran di kinerja skema (Task 6). DDD "Distribusi reviewer per submission" = panel "Cakupan Reviewer" di tab Saat Ini (Task 5).
- **W151 — Bentuk grafik (skill `dataviz`, frame kalah pada bentuk, tidak pada isi).**
  - *Per Status*: frame menggambar donut, dan drill-down-nya "klik segmen". `ui/chart-donut` vendored tidak memancarkan event klik, dan donut paling sulit dibandingkan. Diganti **segmented bar tipis + daftar baris**. Tiap baris = nama status, angka, persen, dan tautan ke `admin.submissions.index` berfilter.
  - *Per Skema per Tahun*: kolom berkelompok, **3 tahun terakhir** (batas seri yang lolos validator semua pasangan). Mengabaikan filter Tahun, karena perbandingan tahun adalah isinya; subjudul menyebutnya.
  - *Fakultas/Prodi*, *Luaran per Tipe*, dan *Beban Reviewer*: **batang horizontal berperingkat**, satu warna, terbesar dulu, maks 10 baris + "Lainnya".
  - *Tren Bulanan*: kolom, satu seri per grafik (small multiples, bukan dua sumbu).
  - Palet: token `--chart-1..5` bawaan shadcn **gagal** validator (lightness band, chroma, kontras), jadi tidak dipakai. Slot 1–3 palet acuan `dataviz` light (`#2a78d6`, `#eb6834`, `#1baf7a`) lolos dan ditaruh di `lib/chartPalette.ts`. Seri tunggal memakai slot 1. Tanpa dark mode (§2 non-tujuan).
  - Setiap grafik punya tooltip (bawaan `BarChart`) dan tombol "Lihat tabel".
- **W152 — Permintaan pemilik 2026-11-08** ("statistik untuk tahu apa yang sedang terjadi, apa yang sudah terjadi, dan tindakan terbaik berikutnya"). Empat tab-route:
  - *Ringkasan* (frame): KPI + empat panel frame.
  - *Saat Ini*: pengajuan berjalan per tahap (Disubmit, Direview, Perlu Revisi, Resubmit) beserta umur terlama sejak submit pertama; status siklus monev (`MonevBoardStatus`); beban reviewer (evaluasi aktif yang belum selesai per reviewer, plus jumlah reviewer aktif tanpa tugas).
  - *Riwayat*: tren bulanan pengajuan disubmit dan luaran terdaftar pada tahun terpilih (per tahun bila "Semua"); kinerja per skema (diajukan, disetujui, ditolak, ditarik, tingkat persetujuan, anggaran disetujui).
  - *Tindak Lanjut*: tiga daftar, masing-masing berisi jumlah, maks 5 baris **terlama dulu**, dan tautan ke tempat aksinya: menunggu penugasan reviewer (state Submitted); menunggu perbaikan peneliti (NeedsRevision); disetujui tanpa luaran (Approved, 0 `research_outputs`).
  - Umur ditampilkan sebagai fakta ("12 hari sejak submit"), bukan peringatan berambang, karena SLA adalah aturan bisnis yang tidak ada di DDD. Dasbor D1 tetap memegang antrian hari ini; tab Tindak Lanjut menambahkan **urutan umur** dan cakupan filter yang tidak dimiliki dasbor.
- **W153 — Status monev agregat.** `MonevBoardStatus::forParent(FormSubmission)` diekstrak (Extract Method) dari `MonevCycleBoard::row`, lalu dipakai papan dan statistik. Satu definisi. N+1 `MonevCycles` per induk adalah minor yang sudah tercatat di M1-2. Skala SIMPAS membuatnya aman; tidak dioptimasi di R1.
- **W154 — Ekspor.**
  - Lima jenis DDD, masing-masing `ExportDefinition`: `label()`, `query(User, ExportFilters)`, `headings()`, `row(Model)`, `filterKeys()`.
  - Kolom dari DDD. *Rekap Submission*: ID, Judul, Status, Skema, Ketua, Tanggal Submit, Total Anggaran. *Detail Anggaran*: ID Pengajuan, Judul, Komponen, Item, Volume, Satuan, Harga Satuan, Total. *Daftar Luaran*: ID Pengajuan, Judul Pengajuan, Tipe Luaran, Judul Luaran, Dibuat. *Rekap Monev*: ID Pengajuan, Judul, Siklus, Status Siklus. *Audit Log*: Waktu, Subjek, Aksi, Pelaku, IP Address.
  - Filter: `year_from`/`year_to` (semua jenis; jenis berbasis pengajuan memakai tahun submit pertama, Audit Log memakai `created_at`), `scheme` dan `status` (jenis berbasis pengajuan saja).
  - Format `xlsx` (default) / `csv` lewat `maatwebsite/excel`. Nama berkas berekstensi sesuai format.
  - Ambang tetap dihitung dari query **setelah scope dan filter** (BR-RPT-02, `[RPT-EXPORT-13]`).
  - Route mengikuti IA: `GET /reporting/export` (`reporting.exports.index`), `POST /reporting/export` (`.store`), `GET /reporting/export/{export}/download` (`.download`, `signed`). `exports.*` dihapus. Notifikasi tidak menyimpan URL (D1), jadi tidak ada tautan lama yang patah. Hanya `NotificationDestination` yang dipetakan ulang.
- **W155 — Audit.**
  - IP: middleware web `CaptureAuditIp` menaruh `$request->ip()` ke `Context::addHidden('audit.ip')`. `AuditTrail::record` menulisnya ke `activity_log.ip_address` (string 45, nullable). Context ikut terbawa ke queued job, jadi entri dari background job mencatat IP peminta. Scheduler/CLI → null → "—". Entri lama tetap null, karena append-only (BR-RPT-04) melarang mengisi ulang.
  - Kolom frame: Waktu, Subjek ("{konteks} — {nama}" dari `AuditSubjectLabel`, cadangan "{Kelas} #{id}"), Aksi (`description`), Pelaku ("{nama} ({peran DDD})", "Sistem" bila tanpa aktor), IP.
  - Filter frame: cari subjek/pelaku; Waktu 7/30/90 hari/semua (default 7, seperti frame); Tipe Aksi (deskripsi yang ada); Pelaku = peran DDD atau "Sistem". Frame menulis "Pelaku: Semua". Daftar nama user bisa ribuan, jadi pencarian nama lewat kotak cari.
  - "Lihat" membuka dialog dengan nilai lama → baru (BUD-AUDIT-02, BUD-ITEM-06, RPT-AUDIT-06). Tidak ada aksi Hapus/Sunting sama sekali (RPT-AUDIT-02/03).
  - Selisih dengan UX pattern 07, frame menang (§1 urutan sumber): rentang waktu berupa preset, bukan date range picker; Tipe Aksi berupa satu pilihan, bukan multiselect; detail lewat dialog "Lihat", bukan baris yang dibentangkan. Yang diambil dari UX: kolom IP hanya untuk Operator/Admin (peneliti menerima `ipAddress` null), dan teks kosong "Tidak ada aktivitas di periode ini".
  - "Export Audit Log" → `reporting.exports.index?type=audit-log`.
  - Route mengikuti IA: `GET /reporting/audit` (`reporting.audit.index`); `audit-log.index` dihapus. Peneliti tetap boleh membuka route yang sama dengan cakupan miliknya (BR-RPT-05). Menunya tetap hanya untuk `reporting.view-audit-log`; pintu peneliti adalah tab Riwayat di detail pengajuan (S2c).
- **W156 — Route statistik** mengikuti IA: `GET /reporting/stats/{tab?}` (`reporting.stats`), dengan `tab ∈ overview|now|history|follow-up` (default `overview`, selain itu 404). Tab = route (preseden W86), dan hanya props tab aktif yang dihitung. `admin.stats.*`, `StatController`, dan kelima Query legacy dihapus.

## Global Constraints

- Berkas Vue baru ≤ 250 baris, sasaran < 150. PHP baru ≤ 200 baris, method ≤ 40 baris, CC ≤ 8 (`??` ikut terhitung), ≤ 4 parameter. Tanpa blok duplikat 6 baris dengan berkas mana pun, termasuk legacy: tulis dari frame, jangan menyalin `Statistics/*.vue`, `Exports/IndexPage.vue`, atau `Audit/IndexPage.vue`.
- Baris pertama `<script setup>` halaman: `// Frame: OPERATOR-07-01` / `// Frame: OPERATOR-07-02` / `// Frame: OPERATOR-07-03`.
- Teks UI dari frame apa adanya. Judul/subjudul: "Dashboard Statistik" / "Statistik ditampilkan langsung dari data terkini dan bersifat hanya-baca."; "Export Data" / "Ekspor diproses di latar belakang — Anda diberi tahu saat file siap diunduh."; "Audit Log" / "Catatan seluruh aktivitas sistem — bersifat permanen dan tidak dapat dihapus.". Kartu: "Total Submission", "Total Anggaran Disetujui", "Luaran Terkumpul", "Reviewer Aktif", "Submission per Status", "Submission per Skema per Tahun", "Submission per Fakultas / Prodi", "Luaran per Tipe", "Request Export Baru", "Riwayat Export". Tombol: "Request Export", "Unduh", "Coba Lagi", "Ekspor Ulang", "Minta Tautan Baru", "Export Audit Log". Banner frame dipakai apa adanya (mis. "Tautan unduhan sudah kedaluwarsa. Minta tautan baru — berkasnya belum terhapus."). Catatan kaki ekspor: "Link unduhan berlaku 24 jam sejak file siap. Ekspor yang gagal dapat dijalankan ulang lewat tombol \"Coba Lagi\"." Label tab W152: "Ringkasan", "Saat Ini", "Riwayat", "Tindak Lanjut".
- Frontend tidak menghitung angka, persen, umur, label status, maupun tautan drill-down. Semuanya dikirim backend (`value`, `share`, `ageDays`, `href`). Logika UI hanya pemformatan (`lib/statistics.ts`) dan buka/tutup tampilan tabel.
- Identifier Inggris; komentar Indonesia, maks 3 baris, sebut kode BR/W. TS tanpa `any`/`console.log`/cast. PHPStan tanpa ignore/baseline/`assert()`/inline `@var`/cast. SQL PostgreSQL (`EXTRACT(YEAR FROM …)`, `date_trunc`, tanpa `YEAR()`; kolom non-agregat ikut `GROUP BY`).
- Query baru `implements ChangesNoState` (AuditCoverageTest). `RequestExport` tetap mengaudit.
- `quality-budget.txt` hanya boleh menyusut. Task 4 menghapus 4 baris `Statistics/*.vue` dari `quality-legacy.txt` dan menurunkan `max.legacy.files` sebanyak 4. `Exports/IndexPage.vue` dan `Audit/IndexPage.vue` tidak ada di daftar legacy. `max.mixed` tidak boleh naik: `row(Model)` mengembalikan `list<string|int|null>`.
- Migration hanya ke DB dev/test. Langkah deploy pemilik: `php artisan migrate` (kolom `activity_log.ip_address`).
- Gate: `composer run quality` + `npm run quality`. Pest di container `simpas-ci:8.4`, `-e DB_PORT=55432 -e DB_PASSWORD=postgres`, **serial**. Vitest tidak dijalankan bersamaan dengan container Pest. Perintah docker ditulis ke skrip di `$CLAUDE_JOB_DIR/tmp`. Worktree butuh salinan `vendor/` asli, `node_modules` (symlink boleh), dan `.env`, dan base-nya dicek (`git reset --hard feat/init-be-development`). Docker Desktop harus menyala.
- Commit satu baris, Indonesia, conventional, **tanpa** trailer `Co-Authored-By`.

## Review Focus

1. **Angka statistik vs daftar drill-down.** Filter Tahun 2026 + skema DIPA; kartu "Disetujui" menunjukkan 7. Tautannya membuka `admin.submissions.index?status=approved&year=2026&scheme={id}` dengan tepat 7 baris, termasuk pengajuan yang punya dua kontrol akses dan pengajuan dari form tingkat fakultas. Laporan monev anak tidak terhitung di keduanya. → Task 2 `angka per status sama dengan baris daftar drill-down`.
2. **Peneliti dengan filter ekspor.** Budi memfilter `status=approved` dan `scheme={skema Sari}`. Hasilnya kosong, bukan P-007, dan tidak error. Jenis Audit Log untuk Budi hanya memuat entri P-001 dan entri dirinya. → Task 8 `[RPT-EXPORT-12]` + `ekspor audit peneliti ter-scope AuditScope`.
3. **Tautan unduhan basi di layar yang dibiarkan terbuka.** Halaman ekspor dibuka, 25 jam kemudian tombol Unduh ditekan. Backend 403 (RPT-EXPORT-04, tidak diubah). Layar menggambar banner kedaluwarsa + "Minta Tautan Baru" (partial reload `jobs`) begitu `linkExpiresAt` lewat, tanpa menunggu klik. → Task 9 `[RPT-EXPORT-04] tautan lewat masa berlaku diganti Minta Tautan Baru`.
4. **Data kosong.** DB tanpa pengajuan terkirim, atau filter yang tidak menyisakan apa-apa. Semua kartu "0"/"—", tingkat persetujuan "—" (bukan NaN/Infinity), tiap widget menampilkan "Tidak ada data" di dalam kartunya sendiri (UX pattern 07: bukan empty state satu halaman), dan tidak ada pembagian dengan nol di SQL. → Task 2 `statistik kosong tidak membagi nol`, Task 3 `widget kosong menampilkan Tidak ada data`.
5. **Entri audit dari background job dan scheduler.** Ekspor besar yang diminta Operator dari 10.10.2.14 → entri job ber-IP 10.10.2.14. `exports:prune` dari scheduler → IP null, tampil "—", Pelaku "Sistem". Entri lama ber-IP null tidak membuat layar error. → Task 11 `IP peminta terbawa ke queued job` + `entri tanpa request tanpa IP`.

---

## R1-1 — Basis statistik dan tab Ringkasan

### Task 1: Route, otorisasi, filter, dan basis — BE (W148, W149, W156)

**Files:**
- Create: `app/Policies/StatisticsPolicy.php`, `app/Data/Reporting/StatisticsFilters.php`, `app/Queries/Reporting/ReportingSubmissions.php`, `app/Queries/Submission/SubmissionYear.php`, `app/Queries/Reporting/StatisticsFilterOptions.php`, `app/Http/Controllers/Reporting/StatisticsController.php`, `app/Enums/StatisticsTab.php`
- Modify: `app/Providers/AppServiceProvider.php` (`Gate::define('view-statistics', [StatisticsPolicy::class, 'view'])`), `routes/web.php` (grup `['auth','can:view-statistics']`, prefix `reporting`), `app/Queries/Submission/OperatorSubmissionList.php` + `app/Http/Controllers/Submission/OperatorSubmissionController.php` (filter `year`), `resources/js/types/submission-manage.ts` (filter `year`)
- Test: `tests/Feature/Reporting/StatisticsPageTest.php`, `tests/Feature/Submission/OperatorSubmissionListTest.php` (berkas yang ada)

**Interfaces:**
- Produces: `StatisticsFilters` (Data) `{ ?int $year, ?int $period, ?int $scheme }`, `StatisticsFilters::fromRequest(Request): self`. Nilai bukan angka → null; `year` default = tahun berjalan bila param tidak ada, `year=all` → null.
- Produces: `SubmissionYear::expression(): Expression` = `EXTRACT(YEAR FROM (FirstSubmitAt::subquery()))`, dan `SubmissionYear::apply(Builder<FormSubmission>, ?int $year): Builder<FormSubmission>`. Dipakai `ReportingSubmissions` dan `OperatorSubmissionList`.
- Produces: `ReportingSubmissions::base(StatisticsFilters $f, bool $withYear = true): Builder<FormSubmission>` = `is_submitted` ∧ `parent_submission_id` null ∧ filter. `withYear: false` untuk panel "keadaan sekarang".
- Produces: `StatisticsFilterOptions::run(): array{yearOptions: list<Option>, periodOptions: list<Option>, schemeOptions: list<Option>}`. Tahun = tahun-tahun yang punya entri "Pengajuan disubmit", menurun, plus `{value:'all', label:'Semua tahun'}`. Periode/skema dari `OperatorSubmissionList::periodAndSchemeOptions()`.
- Produces: enum `StatisticsTab: string { Overview='overview'; Now='now'; History='history'; FollowUp='follow-up' }` + `label()` ("Ringkasan", "Saat Ini", "Riwayat", "Tindak Lanjut") + `filterKeys(): list<'year'|'period'|'scheme'>` (Now/FollowUp tanpa `year`).
- Produces: route `reporting.stats` → `StatisticsController@show(Request, ?string $tab = null)` merender `Reporting/StatsPage` dengan props `{ tab, tabs: list<{value,label,href}>, filters, filterKeys, yearOptions, periodOptions, schemeOptions, can: {viewSubmissions: bool}, ...propsTabAktif }`. Task 1 hanya merender kerangka; isi tab diisi Task 2/5/6/7.

- [ ] **Step 1: Tulis test yang gagal** di `StatisticsPageTest.php`:
  - `it('Operator membuka statistik')`: `assignRole('operator')` → `get(route('reporting.stats'))` 200, `component('Reporting/StatsPage')`, `where('tab','overview')`, `has('tabs', 4)`, `where('filters.year', now()->year)`.
  - `it('peneliti dan reviewer tidak membuka statistik')` (dataset `researcher`, `reviewer_internal`) → 403.
  - `it('pemegang reporting.view-audit-log saja membuka statistik tanpa daftar pengajuan')` → 200, `where('can.viewSubmissions', false)`.
  - `it('tab tak dikenal 404')`: `reporting.stats` dengan `tab=foo` → 404.
  - `it('tahun all berarti tanpa filter tahun')`: `?year=all` → `where('filters.year', null)`.
  - Di `OperatorSubmissionListTest.php`: `it('filter tahun memakai tahun submit pertama')`. Dua pengajuan, submit pertama 2025 dan 2026 (entri audit `SubmitFormSubmission::AUDIT_DESCRIPTION` ber-`created_at` diatur); `?year=2025` → 1 baris.
- [ ] **Step 2: Jalankan, pastikan gagal** — `pest tests/Feature/Reporting/StatisticsPageTest.php` → FAIL (route `reporting.stats` tidak ada).
- [ ] **Step 3: Implementasikan** daftar Interfaces di atas. Komentar `// W148 — pemetaan reporting.view (§4.4 K1)` di Policy, `// W149` di basis.
- [ ] **Step 4: Jalankan lagi** → PASS, ditambah `pest tests/Feature/Submission` → PASS.
- [ ] **Step 5: Commit** `feat: kerangka statistik pelaporan dan filter tahun submit`

### Task 2: Panel Ringkasan — BE (W150, W151)

**Files:**
- Create: `app/Queries/Reporting/StatisticsOverview.php`, `app/Queries/Reporting/StatusBreakdown.php`, `app/Queries/Reporting/SchemeYearCounts.php`, `app/Queries/Reporting/OrganizationCounts.php`, `app/Queries/Reporting/OutputTypeCounts.php`, `app/Data/View/Reporting/{StatusShareData,RankedCountData,SchemeYearData}.php`
- Modify: `app/Http/Controllers/Reporting/StatisticsController.php` (tab `overview`), `tests/Feature/Reporting/StatisticsAccuracyTest.php` (dipindah ke Query baru)
- Test: `tests/Feature/Reporting/StatisticsOverviewTest.php`, `tests/Feature/Reporting/StatisticsAccuracyTest.php`

**Interfaces:**
- Consumes: `ReportingSubmissions::base`, `SubmissionYear`, `SubmissionStatusBuckets` **tidak** dipakai (statistik v2 memakai 7 state backend, label dari `SubmissionStatusData`), `UnitFaculty`, `ReviewersWithinTenure`, `KpiCardData` (dari D1).
- Produces: `StatisticsOverview::run(StatisticsFilters): array{kpis: list<KpiCardData>, byStatus: list<StatusShareData>, bySchemeYear: SchemeYearData, byFaculty: list<RankedCountData>, byStudyProgram: list<RankedCountData>, byOutputType: list<RankedCountData>}`.
- `StatusShareData { string $key, string $label, string $variant, int $count, float $share, string $href }`: `share` 0–1, 0 bila total 0; urutan = urutan alur state (Disubmit, Direview, Perlu Revisi, Resubmit, Disetujui, Ditolak, Ditarik); `href` = `route('admin.submissions.index', [status, year, period, scheme])` tanpa kunci null.
- `RankedCountData { string $label, int $count, ?string $href }`: terurut menurun, maks 10; sisanya dilipat menjadi satu baris `label: 'Lainnya'`.
- `SchemeYearData { list<int> $years, list<array{scheme: string, counts: array<string,int>}> $rows }`: `years` = 3 tahun submit terakhir yang punya data (menaik), dan `counts` dikunci `(string) $year`. Mengabaikan `filters.year` (W151).
- Produces: `App\Support\Format\Rupiah::format(int $amount): string` → "Rp 6.500.000" (`number_format($amount, 0, ',', '.')`). Belum ada helper rupiah di PHP; dipakai juga Task 6 dan ekspor. FE tidak memformat ulang nilai KPI.
- KPI `key`: `submissions`, `approvedBudget` (value `Rupiah::format`, angka utuh, bukan "Rp 2,4 M", karena angka yang dipertanggungjawabkan tidak dibulatkan), `outputs`, `activeReviewers` (meta "Internal n · Eksternal m"), `approvalRate` ("64%" atau "—"). `href` KPI submissions → `admin.submissions.index` berfilter; lainnya null.

- [ ] **Step 1: Tulis test yang gagal** di `StatisticsOverviewTest.php`:
  - `it('angka per status sama dengan baris daftar drill-down')`. Pengajuan Approved 2026/DIPA ×2 (satu punya 2 kontrol akses, satu dari form tingkat fakultas), 1 Approved 2025, 1 Draft, 1 laporan monev anak Approved. Dengan filter 2026+DIPA, `byStatus` Approved = 2, dan `get($href)` daftar operator → `submissions.total` 2.
  - `it('statistik kosong tidak membagi nol')` → semua `count` 0, `share` 0.0, KPI `approvalRate` "—", `byFaculty` [].
  - `it('tingkat persetujuan tidak menghitung yang ditarik')`: 3 Approved, 1 Rejected, 2 Withdrawn → "75%".
  - `it('anggaran disetujui menjumlah item yang tidak terhapus pada pengajuan disetujui')`: item 4.000.000 + 2.500.000 (+ 1 soft-deleted 9.000.000) pada Approved, 5.000.000 pada UnderReview → value "Rp 6.500.000".
  - `it('reviewer aktif mengikuti masa tugas dan status aktif')`: 1 internal dalam masa tugas, 1 eksternal dalam masa tugas, 1 lewat `end_date`, 1 `is_active=false` → value "2", meta "Internal 1 · Eksternal 1".
  - `it('skema per tahun memuat tiga tahun terakhir dan mengabaikan filter tahun')`: data 2022–2026, filter 2024 → `years` [2024, 2025, 2026].
  - `it('lebih dari sepuluh unit dilipat menjadi Lainnya')`: 12 prodi → 11 baris, baris terakhir "Lainnya" = jumlah dua terkecil.
  - Pindahkan aturan `StatisticsAccuracyTest` (draft tidak dihitung; fakultas menurut unit pengaju dan posisinya di pohon, aktif atau tidak; dua kontrol akses dihitung sekali) ke `StatisticsOverview::run`. Hapus test yang hanya menguji Query legacy (`FormPhaseStatistics`, `UserStatistics`).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan.** Satu `GROUP BY status` untuk `byStatus`. Fakultas lewat `joinSub(UnitFaculty::run())` seperti legacy, `COUNT(DISTINCT form_submissions.id)`. Luaran per tipe: `research_outputs` join `output_type_definitions` (label = `name`), `whereIn form_submission_id` basis.
- [ ] **Step 4: Jalankan** `pest tests/Feature/Reporting` → PASS.
- [ ] **Step 5: Commit** `feat: panel ringkasan statistik`

### Task 3: Tab Ringkasan — FE

**Files:**
- Create: `resources/js/types/reporting.ts`, `resources/js/lib/chartPalette.ts`, `resources/js/lib/statistics.ts`, `resources/js/Pages/Reporting/StatsPage.vue`, `resources/js/Components/Reporting/{StatsTabs,StatsFilterBar,StatusBreakdownCard,RankedBarList,ColumnChartCard,ChartTableToggle}.vue`
- Move: `resources/js/Components/Dashboard/KpiGrid.vue` → `resources/js/Components/app/KpiGrid.vue` (Move Method; dipakai dua konteks), lalu perbarui impor `Dashboard/IndexPage`
- Test: `tests/frontend/lib/statistics.spec.ts`, `tests/frontend/components/Reporting/{StatusBreakdownCard,RankedBarList}.spec.ts`, `tests/frontend/pages/Reporting/StatsPage.spec.ts`

**Interfaces:**
- Consumes: props Task 1–2 (`StatsPageProps` di `types/reporting.ts` mengikuti Data class satu-satu), `useFilters`, `FilterToolbar`, `FilterSelect`, `SectionCard`, `EmptyState`, `PageHeader`, `ui/chart-bar` `BarChart`.
- Produces: `lib/chartPalette.ts` `export const SERIES = ['#2a78d6', '#eb6834', '#1baf7a'] as const` (komentar: lolos `validate_palette.js --mode light`, W151). `lib/statistics.ts` `formatShare(share: number): string` ("38%", 0 → "0%"), `formatAge(days: number): string` ("hari ini", "1 hari", "12 hari"). Keduanya juga dipakai Task 7.
- Produces: `RankedBarList` props `{ rows: RankedCount[]; emptyText?: string }`. Batang HTML, lebar = `count / max`, warna `SERIES[0]`, label dan angka di luar batang dengan token teks, baris ber-`href` menjadi `Link`. Dipakai ulang Task 5.
- Produces: `ColumnChartCard` props `{ title: string; subtitle?: string; index: string; categories: string[]; data: Record<string, string|number>[] }`. Memakai `BarChart` dengan `colors` dari `SERIES`, legenda hanya bila `categories.length ≥ 2`, `ChartTableToggle` menampilkan `<table>` berisi data yang sama. Dipakai ulang Task 6.

- [ ] **Step 1: Tulis test yang gagal**:
  - `statistics.spec.ts`: `formatShare(0.384)` → "38%"; `formatShare(0)` → "0%"; `formatAge(0)` → "hari ini"; `formatAge(12)` → "12 hari".
  - `StatusBreakdownCard.spec.ts`: tiga status → tiga `a[href]` dengan href dari props, teks "Disetujui", "7", "38%"; total 0 → `it('widget kosong menampilkan Tidak ada data')` (teks "Tidak ada data" di dalam kartu, tanpa segmented bar).
  - `RankedBarList.spec.ts`: baris pertama punya lebar 100%; baris tanpa `href` bukan tautan.
  - `StatsPage.spec.ts` (stub `BarChart`): judul "Dashboard Statistik", subjudul frame, 4 tab ber-`href` dari props, filter Tahun/Periode/Skema hanya yang ada di `filterKeys`, lima KPI.
- [ ] **Step 2: Jalankan** `npx vitest run tests/frontend/lib/statistics.spec.ts tests/frontend/components/Reporting tests/frontend/pages/Reporting` → FAIL.
- [ ] **Step 3: Implementasikan.** Tata letak frame: header + filter di aksi header; `KpiGrid` (`xl:grid-cols-5`); dua baris `simpas-dashboard-split` (`grid gap-4 lg:grid-cols-2`): [Per Status | Per Skema per Tahun], [Fakultas/Prodi (toggle `Tabs` Fakultas/Prodi) | Luaran per Tipe]. `StatsPage` < 150 baris dan hanya memilih section menurut `tab`. Section tab lain diisi Task 5–7, sampai saat itu `EmptyState`.
- [ ] **Step 4: Jalankan** → PASS, lalu `npm run quality` → exit 0.
- [ ] **Step 5: Commit** `feat(fe): tab ringkasan dashboard statistik v2`

### Task 4: Hapus statistik legacy dan tutup R1-1

**Files:**
- Delete: `resources/js/Pages/Statistics/{FormPhaseStats,FormSubmissionStats,SubmissionReviewerStats,UserStats}.vue`, `resources/js/types/statistics.d.ts` (bila tak ada pemakai lain), `app/Http/Controllers/StatController.php`, `app/Queries/Reporting/{FormPhaseStatistics,FormPhaseStatusCounts,FormSubmissionStatistics,SubmissionReviewerStatistics,UserStatistics,OrganizationReferences}.php` dan `SubmissionStatusBuckets.php` (bila tak ada pemakai lain; periksa dengan `grep -rn`), `tests/Feature/StatControllerTest.php`
- Modify: `routes/web.php` (hapus 4 route `admin.stats.*`), `resources/js/lib/navigation.ts` (Statistik → `routeName: 'reporting.stats'`, `activePattern: 'reporting.stats'`, hapus komentar R1), `resources/js/Components/AppSidebar.vue` legacy (empat tautan statistik → satu `route('reporting.stats')`), `quality-legacy.txt` (−4 baris), `quality-budget.txt` (`max.legacy.files` −4), `docs/design/fe-v2-design.md` (§8 R1-1 ☑, §9 W148–W151 + W156)
- Test: `tests/frontend/lib/navigation.spec.ts`, `tests/Unit/Architecture/FrontendRouteNamesTest.php` (tanpa perubahan; harus hijau)

- [ ] **Step 1: Ubah test** `navigation.spec.ts`: item "Statistik" ber-`routeName` `reporting.stats`. Tambahkan `it('rute statistik legacy sudah tidak terdaftar')` (dataset 4 nama `admin.stats.*`) → `Route::has` false.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Hapus dan ubah** berkas di atas. Hapus juga `UnitFaculty` **hanya** bila Task 2 tidak memakainya (seharusnya dipakai).
- [ ] **Step 4: Gate penuh** `composer run quality` dan `npm run quality` → keduanya exit 0. Laporkan jumlah test.
- [ ] **Step 5: Commit** `feat(fe): statistik v2 menggantikan empat layar statistik legacy`, lalu commit dokumen `docs: irisan R1-1 statistik selesai`.

## R1-2 — Saat ini, riwayat, tindak lanjut (W152)

### Task 5: Tab Saat Ini — BE + FE

**Files:**
- Create: `app/Queries/Reporting/StageBacklog.php`, `app/Queries/Reporting/MonevStatusCounts.php`, `app/Queries/Reporting/ReviewerWorkload.php`, `app/Queries/Reporting/ReviewerCoverage.php`, `app/Data/View/Reporting/{StageBacklogData,ReviewerWorkloadData}.php`, `resources/js/Components/Reporting/{StageBacklogList,NowSection}.vue`
- Modify: `app/Enums/MonevBoardStatus.php` (+ `forParent(FormSubmission): self`, W153), `app/Queries/Monev/MonevCycleBoard.php` (memakai `forParent`), `StatisticsController` (tab `now`), `types/reporting.ts`, `Pages/Reporting/StatsPage.vue`
- Test: `tests/Feature/Reporting/StatisticsNowTest.php`, `tests/Feature/Monev/MonevManageIndexTest.php` (yang ada; harus hijau setelah `forParent` diekstrak), `tests/frontend/components/Reporting/StageBacklogList.spec.ts`

**Interfaces:**
- Consumes: `ReportingSubmissions::base($f, withYear: false)`, `FirstSubmitAt`, `RankedBarList` (Task 3), `formatAge` (Task 3).
- Produces: `StageBacklogData { string $key, string $label, int $count, ?int $oldestAgeDays, string $href }` untuk Submitted, UnderReview, NeedsRevision, Resubmitted, dalam urutan itu. `oldestAgeDays` = hari penuh sejak `FirstSubmitAt` terlama, null bila count 0.
- Produces: `MonevStatusCounts::run(StatisticsFilters): list<RankedCountData>`, empat baris `MonevBoardStatus` dalam urutan enum (bukan peringkat). `href` → `monev.manage.index` berfilter periode/skema.
- Produces: `ReviewerWorkload::run(): ReviewerWorkloadData { list<RankedCountData> $rows, int $idleCount }`. `rows` = reviewer aktif (definisi W150) dengan jumlah `SubmissionReviewer::active()->unsettledEvaluation()` > 0, label "{nama} (Internal|Eksternal)", maks 10 + "Lainnya", `href` null. `idleCount` = reviewer aktif dengan 0 tugas.
- Produces: `ReviewerCoverage::run(StatisticsFilters): list<RankedCountData>` (DDD "distribusi reviewer per submission"). Empat baris tetap, urutan bukan peringkat: "Tanpa reviewer", "1 reviewer", "2 reviewer", "3+ reviewer". Isinya pengajuan berstate Submitted/UnderReview/NeedsRevision/Resubmitted menurut jumlah `SubmissionReviewer::active()`. `href` null.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('tahap berjalan menghitung umur terlama sejak submit pertama')`: Submitted submit 12 hari lalu dan 3 hari lalu → `count` 2, `oldestAgeDays` 12; tahap kosong → `oldestAgeDays` null.
  - `it('saat ini tidak memakai filter tahun')`: `?year=2020` tidak mengubah angka.
  - `it('status monev agregat sama dengan papan monev')`: 3 induk berstatus papan berbeda → hitungan sama dengan `MonevCycleBoard::run` per baris.
  - `it('beban reviewer menghitung evaluasi aktif yang belum selesai')`: R1 punya 3 aktif belum selesai + 1 selesai + 1 diganti (`status` bukan active) → 3; R2 aktif tanpa tugas → `idleCount` 1; reviewer lewat masa tugas tidak muncul.
  - `it('cakupan reviewer menghitung reviewer aktif per pengajuan berjalan')`: pengajuan dengan 0, 1, 3 reviewer aktif (+ 1 diganti) → baris "Tanpa reviewer" 1, "1 reviewer" 1, "3+ reviewer" 1; Approved tidak ikut.
  - `StageBacklogList.spec.ts`: baris "Direview · 4 · terlama 12 hari" menaut ke `href`; `oldestAgeDays` null → tanpa teks umur.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan.** Umur lewat `MIN(first_submit)` dan `now()->diffInDays()` di PHP (satu query `GROUP BY status`). Section: [Pengajuan Berjalan | Siklus Monev], lalu [Cakupan Reviewer | Beban Reviewer]. Beban Reviewer menampilkan dengan kalimat "{n} reviewer aktif belum punya tugas evaluasi." bila `idleCount` > 0.
- [ ] **Step 4: Jalankan** `pest tests/Feature/Reporting tests/Feature/Monev` + Vitest berkas baru → PASS.
- [ ] **Step 5: Commit** `feat: tab saat ini di dashboard statistik`

### Task 6: Tab Riwayat — BE + FE

**Files:**
- Create: `app/Queries/Reporting/MonthlyTrend.php`, `app/Queries/Reporting/SchemePerformance.php`, `app/Data/View/Reporting/{TrendData,SchemePerformanceData}.php`, `resources/js/Components/Reporting/{HistorySection,SchemePerformanceTable}.vue`
- Modify: `StatisticsController` (tab `history`), `types/reporting.ts`, `StatsPage.vue`
- Test: `tests/Feature/Reporting/StatisticsHistoryTest.php`, `tests/frontend/components/Reporting/SchemePerformanceTable.spec.ts`

**Interfaces:**
- Consumes: `ReportingSubmissions::base`, `SubmissionYear`, `ColumnChartCard` (Task 3).
- Produces: `MonthlyTrend::run(StatisticsFilters): array{submitted: TrendData, outputs: TrendData}`. `TrendData { string $unit ('month'|'year'), list<array{label: string, count: int}> $points }`. Dengan `year` terisi: 12 titik "Jan".."Des" (bulan tanpa data = 0). Dengan `year` null: satu titik per tahun yang punya data. Luaran memakai `research_outputs.created_at`, terbatas pada `form_submission_id` basis periode/skema.
- Produces: `SchemePerformance::run(StatisticsFilters): list<SchemePerformanceData>`. `SchemePerformanceData { string $scheme, int $submitted, int $approved, int $rejected, int $withdrawn, string $approvalRate, string $approvedBudget, string $href }`. Teks `approvalRate`/`approvedBudget` memakai definisi W150, terurut `submitted` menurun.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('tren bulanan mengisi bulan kosong dengan nol')`: submit Maret ×2 dan Juli ×1 pada 2026 → 12 titik, Mar = 2, Jul = 1, lainnya 0.
  - `it('tren tanpa tahun dikelompokkan per tahun')`: data 2025 dan 2026, `year=all` → `unit` 'year', 2 titik.
  - `it('kinerja skema memakai definisi KPI yang sama')`: hasil per skema dijumlahkan sama dengan KPI Ringkasan untuk filter yang sama.
  - `SchemePerformanceTable.spec.ts`: header "Skema, Diajukan, Disetujui, Ditolak, Ditarik, Tingkat Persetujuan, Anggaran Disetujui"; nama skema menaut ke `href`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan.** `date_trunc('month', …)`/`EXTRACT(MONTH FROM …)`. Section: dua `ColumnChartCard` berdampingan ("Pengajuan Disubmit per Bulan", "Luaran Terdaftar per Bulan"; satu seri, small multiples W151), lalu tabel kinerja skema.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `feat: tab riwayat di dashboard statistik`

### Task 7: Tab Tindak Lanjut — BE + FE, tutup R1-2

**Files:**
- Create: `app/Queries/Reporting/FollowUpLists.php`, `app/Data/View/Reporting/{FollowUpListData,FollowUpItemData}.php`, `resources/js/Components/Reporting/{FollowUpSection,FollowUpListCard}.vue`
- Modify: `StatisticsController` (tab `follow-up`), `types/reporting.ts`, `StatsPage.vue`, `docs/design/fe-v2-design.md` (§8 R1-2 ☑, §9 W152/W153)
- Test: `tests/Feature/Reporting/StatisticsFollowUpTest.php`, `tests/frontend/components/Reporting/FollowUpListCard.spec.ts`

**Interfaces:**
- Consumes: `ReportingSubmissions::base($f, withYear: false)`, `FirstSubmitAt`, `formatAge`.
- Produces: `FollowUpLists::run(StatisticsFilters, bool $withItems): list<FollowUpListData>`. `FollowUpListData { string $key ('awaitingReviewer'|'awaitingRevision'|'approvedWithoutOutput'), string $title, string $hint, int $count, list<FollowUpItemData> $items, ?string $href }`.
  - `FollowUpItemData { int $id, string $title, string $leader, ?string $scheme, int $ageDays, string $href }`: maks 5, terlama dulu; `href` → `admin.submissions.show`; `items` [] bila `$withItems` false (W148).
  - Judul dan hint: "Menunggu Penugasan Reviewer" / "Tugaskan reviewer dari detail pengajuan."; "Menunggu Perbaikan Peneliti" / "Peneliti sedang memperbaiki; perpanjang batas bila diminta."; "Disetujui tanpa Luaran" / "Ingatkan tim untuk mendaftarkan luaran."
  - `href` daftar: status Submitted / NeedsRevision → `admin.submissions.index` berfilter. "Disetujui tanpa Luaran" → null, karena daftar operator tidak punya filter luaran dan tidak ditambah (YAGNI).

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('daftar tindak lanjut terurut terlama dan dibatasi lima')`: 7 Submitted dengan umur berbeda → `count` 7, 5 item, item pertama terlama.
  - `it('disetujui tanpa luaran tidak memuat yang sudah punya luaran')`.
  - `it('tanpa submissions.view-all hanya angka yang dikirim')`: pemegang `reporting.view-audit-log` saja → `items` [] di ketiga daftar, `count` tetap.
  - `FollowUpListCard.spec.ts`: item menampilkan judul, ketua, "12 hari sejak submit", dan menaut ke `href`; `count` 0 → "Tidak ada yang menunggu.".
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan.** Tiga kartu dalam grid `lg:grid-cols-3`.
- [ ] **Step 4: Gate penuh** `composer run quality` + `npm run quality` → exit 0.
- [ ] **Step 5: Commit** `feat: tab tindak lanjut di dashboard statistik`, lalu `docs: irisan R1-2 statistik selesai`.

## R1-3 — Ekspor

### Task 8: Lima jenis ekspor, filter, dan format — BE (W154)

**Files:**
- Create: `app/Support/Reporting/Exports/{ExportDefinition,SubmissionRecapExport,BudgetDetailExport,ResearchOutputListExport,MonevRecapExport,AuditLogExport,TabularSheet}.php`, `app/Data/Reporting/{ExportFilters,ExportRequestData}.php`, `app/Http/Requests/Reporting/ExportRequest.php`, `app/Enums/ExportFormat.php`
- Modify: `app/Support/Reporting/ExportType.php` (registry `definition(string): ExportDefinition`, `all()`, `label()`; `query()` dihapus), `app/Support/Reporting/ExportWriter.php` (menulis lewat `Excel::store(new TabularSheet(...), $path, self::DISK, $format->writerType())`), `app/Actions/Export/RequestExport.php` (`handle(User, ExportRequestData): ExportJob`; menyimpan `filters` + `format`), `app/Queries/Reporting/ExportHistory.php` (baris + `format`, `filters` terbaca, `link_expires_at`), migration `2026_11_09_000000_add_format_to_export_jobs` (`format` string 8 default `xlsx`), `app/Models/ExportJob.php`, `docs/requirement-analysis/bdd/supporting/reporting.feature` (`[RPT-EXPORT-12]`, `[RPT-EXPORT-13]`)
- Delete: `app/Exports/SubmissionSummaryExport.php`
- Test: `tests/Feature/Export/ExportTest.php`, `tests/Feature/Export/ExportDefinitionsTest.php`

**Interfaces:**
- Produces: `interface ExportDefinition { label(): string; filterKeys(): list<'year'|'scheme'|'status'>; query(User $u, ExportFilters $f): Builder<covariant Model>; headings(): list<string>; row(Model $m): list<string|int|null>; }`. Builder berbasis pengajuan selalu berangkat dari `SubmissionVisibility::for($u)`; `AuditLogExport` dari `AuditScope::forUser($u)` (BR-RPT-10).
- Produces: `ExportFilters { ?int $yearFrom, ?int $yearTo, ?int $scheme, ?string $status }`, disimpan dan dibaca lewat `export_jobs.filters`. Kunci di luar `filterKeys()` jenisnya dibuang saat `ExportRequestData::from`.
- Produces: `ExportFormat: string { Xlsx='xlsx'; Csv='csv' }` + `writerType(): string` (konstanta `Maatwebsite\Excel\Excel::XLSX/CSV`) + `extension()`.
- Produces: `ExportRequest` rules: `type` in `ExportType::all()`, `format` in enum, `year_from`/`year_to` `nullable|integer|between:2000,2100`, `year_to` `gte:year_from`, `scheme` `nullable|exists:schemes,id`, `status` `nullable|in:` nilai `SubmissionStatusChoices` tanpa Draft.
- Produces: `ExportType` konstanta `SUBMISSIONS='submissions'`, `BUDGET='budget'`, `RESEARCH_OUTPUTS='research-outputs'`, `MONEV='monev'`, `AUDIT_LOG='audit-log'`, label sesuai DDD.

- [ ] **Step 1: Tulis test yang gagal**:
  - `ExportDefinitionsTest.php`: tiap jenis (dataset 5) → `headings()` persis kolom W154; `row()` pengajuan contoh → nilai yang diharapkan (mis. Rekap Submission `[id, 'Biochar Kaltim', 'Disetujui', 'DIPA', 'Budi', '2026-02-12', 6500000]`).
  - `it('isi ekspor tidak memuat kolom di luar definisi')`: baca xlsx yang dihasilkan (`Excel::toArray`) → baris header = `headings()`.
  - `it('[RPT-EXPORT-12] filter ekspor tidak melebarkan scope peneliti')` (skenario baru, ditulis di `.feature` pada commit yang sama).
  - `it('[RPT-EXPORT-13] ambang dihitung dari baris setelah filter')`: 101 pengajuan, 40 di skema A, filter skema A → `status` completed, `Queue::assertNothingPushed()`.
  - `it('ekspor audit peneliti ter-scope AuditScope')`.
  - `it('format csv menghasilkan berkas .csv')`, `it('kunci filter di luar jenisnya dibuang')` (Audit Log + `status` → `filters` tanpa `status`).
  - Sesuaikan RPT-EXPORT-01..11 yang ada ke `route('reporting.exports.*')` dan `RequestExport::run($user, ExportRequestData::from([...]))`. Perilakunya tidak berubah.
- [ ] **Step 2: Jalankan** `pest tests/Feature/Export` → FAIL.
- [ ] **Step 3: Implementasikan.** Route W154 (grup `auth`, tanpa permission, BR-RPT-10), komentar BR dipindah dari route lama. `TabularSheet implements FromArray, WithHeadings` (strict tier di `app/Support`). Ketua = `submittedBy.name`, Judul = `form.title`, Tanggal Submit = `FirstSubmitAt` (Y-m-d), Status = label state.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `feat: lima jenis ekspor dengan filter dan format excel`

### Task 9: Layar Export Data — FE

**Files:**
- Create: `resources/js/Pages/Reporting/ExportPage.vue`, `resources/js/Components/Reporting/{ExportRequestCard,ExportHistoryTable,ExportJobStatus}.vue`, `resources/js/lib/exportJob.ts`
- Modify: `app/Http/Controllers/Export/ExportController.php` → pindah ke `app/Http/Controllers/Reporting/ExportController.php` (render `Reporting/ExportPage`, props `types: list<{value,label,filterKeys}>`, `formats`, `schemeOptions`, `statusOptions`, `yearOptions`, `threshold`, `retentionHours`, `jobs`, `initialType` dari `?type=`), `types/reporting.ts`
- Test: `tests/Feature/Export/ExportPageTest.php`, `tests/frontend/lib/exportJob.spec.ts`, `tests/frontend/components/Reporting/ExportHistoryTable.spec.ts`

**Interfaces:**
- Consumes: `ExportHistory` (Task 8, baris + `link_expires_at`, `file_is_gone`, `download_url`, `attempts`, `failure_reason`), `useForm`, `router.reload({ only: ['jobs'] })`.
- Produces: `lib/exportJob.ts` `jobState(job: ExportJobRow, now: Date): 'queued'|'processing'|'ready'|'linkExpired'|'gone'|'failed'`. Urutan: `failed` → `gone` (`file_is_gone`) → `linkExpired` (`download_url` ada dan `now > link_expires_at`) → `ready` → status mentah. Ini satu-satunya logika waktu di FE, karena tautan basi hanya bisa diketahui di klien saat layar dibiarkan terbuka.
- Badge per state (UX pattern 07): `queued` "Antrian" (abu, spinner), `processing` "Memproses" (kuning, spinner), `ready` "Selesai" (hijau), `failed` "Gagal" (merah), `linkExpired`/`gone` "Kadaluarsa" (abu). Badge selalu berlabel teks, tidak hanya warna.
- Aksi per state: `queued`/`processing` → banner frame RPT-EXPORT-02 "Ekspor dijadwalkan. Anda akan diberi tahu saat berkasnya siap — layar ini boleh ditutup."; `ready` → "Unduh" (`a[href=download_url]`) + "Kadaluarsa dalam {n} jam" (sisa jam penuh hingga `completed_at + retentionHours`, BR-RPT-03 UX); `linkExpired` → banner kuning + "Minta Tautan Baru" (reload `jobs`); `gone` → banner frame + "Ekspor Ulang" (POST ulang dengan `type`, `format`, dan `filters` baris itu); `failed` → banner "Export gagal — coba lagi. Sistem tidak akan mengulang sendiri; jalankan ulang secara manual." + "Coba Lagi" (POST ulang sama).
- Baris cetak PDF (`ExportJob.type` ∈ `PrintableDocument::all()`) ikut tampil karena tabel ekspor menampungnya (BR-RPT-11). Labelnya `PrintableDocument::label`, dan `ExportHistory` membedakannya dengan `kind: 'export'|'print'`. Baris cetak `gone`/`failed` **tidak** menawarkan "Ekspor Ulang"/"Coba Lagi", karena POST ke `reporting.exports.store` dengan tipe cetak akan 422. Gantinya teks "Cetak ulang dari halaman pengajuan." bertaut `admin.submissions.show` atau `submissions.show` sesuai `can` baris.
- Polling: `usePoll(10000, { only: ['jobs'] })` Inertia, aktif hanya selama ada baris `queued`/`processing` (UX pattern 07), berhenti bila tidak ada.

- [ ] **Step 1: Tulis test yang gagal**:
  - `ExportPageTest.php`: Operator → 200 `Reporting/ExportPage`, `has('types', 5)`; peneliti → 200 (BR-RPT-10); `?type=audit-log` → `initialType` 'audit-log'; `?type=foo` → `initialType` null.
  - `exportJob.spec.ts`: lima kasus `jobState`, termasuk `[RPT-EXPORT-04]` (link lewat → 'linkExpired'), `[RPT-EXPORT-10]` (gone menang atas link), `[RPT-EXPORT-11]` (23 jam → 'ready').
  - `ExportHistoryTable.spec.ts`: `it('[RPT-EXPORT-02] ekspor yang dijadwalkan menampilkan banner latar belakang')`; `it('baris cetak yang gagal tidak menawarkan Coba Lagi')`; `it('sisa masa simpan ditampilkan dalam jam')` (selesai 21 jam lalu → "Kadaluarsa dalam 3 jam"); `it('[RPT-EXPORT-04] tautan lewat masa berlaku diganti Minta Tautan Baru')`; `it('[RPT-EXPORT-06] gagal setelah 3 percobaan menawarkan Coba Lagi')` (teks "3 dari 3"); `it('[RPT-EXPORT-10] berkas terhapus menawarkan Ekspor Ulang')`.
  - `ExportRequestCard`: field Skema/Status hanya tampil untuk jenis yang `filterKeys`-nya memuatnya; hint ambang "Lebih dari 100 baris diproses di latar belakang." dari `threshold`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan** tata letak frame: kartu "Request Export Baru" (grid 2 kolom + segmented Excel/CSV memakai `RadioGroup` + tombol), kartu "Riwayat Export", catatan kaki. Toast dari flash yang ada.
- [ ] **Step 4: Jalankan** → PASS.
- [ ] **Step 5: Commit** `feat(fe): layar export data v2`

### Task 10: Tutup R1-3

**Files:**
- Delete: `resources/js/Pages/Exports/IndexPage.vue`, `app/Http/Controllers/Export/ExportController.php` (sudah dipindah)
- Modify: `routes/web.php` (hapus `exports.*`), `app/Queries/Notification/NotificationDestination.php` (`export_completed`/`export_failed` → `reporting.exports.index`), `app/Queries/Dashboard/ManagementDashboardCards.php` (href kartu ekspor), `app/Queries/Reporting/ExportHistory.php` (`temporarySignedRoute('reporting.exports.download', …)`), `resources/js/lib/navigation.ts` (Export Data → `reporting.exports.index`), `resources/js/Pages/Submission/ManageIndexPage.vue` (tombol Export), `docs/design/fe-v2-design.md` (§8 R1-3 ☑, §9 W154)
- Test: `tests/Feature/Notification/NotificationDestinationTest.php`, `tests/Feature/Dashboard/ManagementDashboardCardsTest.php`, `tests/frontend/lib/navigation.spec.ts`

- [ ] **Step 1: Ubah test** yang menyebut `exports.index` ke `reporting.exports.index`. Tambahkan `it('rute ekspor lama sudah tidak terdaftar')` (dataset `exports.index`, `exports.store`, `exports.download`).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Ubah** berkas di atas.
- [ ] **Step 4: Gate penuh** → exit 0.
- [ ] **Step 5: Commit** `feat(fe): export data v2 menggantikan layar ekspor legacy`, lalu `docs: irisan R1-3 ekspor selesai`.

## R1-4 — Audit

### Task 11: Alamat IP di jejak audit — BE (W155)

**Files:**
- Create: `app/Http/Middleware/CaptureAuditIp.php`, migration `2026_11_09_100000_add_ip_address_to_activity_log` (`ip_address` string 45 nullable, setelah `causer_id`)
- Modify: `bootstrap/app.php` (middleware di grup `web`), `app/Support/Audit/AuditTrail.php` (`->tap(fn (Activity $a) => $a->setAttribute('ip_address', Context::getHidden(self::IP_CONTEXT)))`, konstanta `IP_CONTEXT = 'audit.ip'`), `app/Models/AuditEntry.php` (`@property string|null $ip_address`), `docs/requirement-analysis/bdd/supporting/audit.feature` (`[RPT-AUDIT-08]`)
- Test: `tests/Feature/Audit/AuditIpTest.php`

**Interfaces:**
- Produces: `AuditTrail::IP_CONTEXT`; setiap entri yang ditulis dalam request web atau job yang di-dispatch dari request web membawa IP. Tanpa request → null.

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('[RPT-AUDIT-08] entri audit mencatat alamat IP pelaku')`: `withServerVariables(['REMOTE_ADDR' => '10.10.2.14'])->post(route('user.submissions.wizard.submit', …))` → entri "Pengajuan disubmit" `ip_address` '10.10.2.14'.
  - `it('IP peminta terbawa ke queued job')`: `config(['queue.default' => 'database'])`. Operator dari 10.10.2.14 menulis entri lewat job uji kecil yang di-dispatch dari route test (`Route::post` di test memanggil `dispatch(fn () => AuditTrail::record(...))`). Lalu `Context::flush()` dan `Artisan::call('queue:work', ['--once' => true])`. Entri ber-IP 10.10.2.14. Dengan `sync` (bawaan `phpunit.xml`) test ini lolos tanpa membuktikan apa pun, karena Context tidak pernah melewati payload.
  - `it('entri tanpa request tanpa IP')`: `AuditTrail::record` dari test tanpa HTTP → null.
  - `it('IP tidak muncul di log aplikasi')`: `Context::getHidden` dipakai, `Context::get('audit.ip')` null.
- [ ] **Step 2: Jalankan** → FAIL (kolom tidak ada).
- [ ] **Step 3: Implementasikan.** Komentar `// W155 — DDD Reporting: Audit Log "dari IP mana"`. Pastikan `ModelSchemaDriftTest` hijau.
- [ ] **Step 4: Jalankan** `pest tests/Feature/Audit tests/Unit/Audit tests/Feature/ModelSchemaDriftTest.php` → PASS.
- [ ] **Step 5: Commit** `feat: jejak audit mencatat alamat IP`

### Task 12: Daftar audit terfilter — BE (W155)

**Files:**
- Create: `app/Queries/Reporting/AuditLogList.php`, `app/Support/Audit/AuditSubjectLabel.php`, `app/Data/View/Reporting/{AuditEntryRowData,AuditFilters}.php`, `app/Http/Controllers/Reporting/AuditController.php`
- Modify: `routes/web.php` (`reporting.audit.index`; `audit-log.index` dihapus, komentar BR dipindah), `app/Support/Reporting/Exports/AuditLogExport.php` (memakai `AuditSubjectLabel` dan label pelaku yang sama)
- Delete: `app/Http/Controllers/Audit/AuditLogController.php`
- Test: `tests/Feature/Audit/AuditTrailTest.php` (route baru), `tests/Feature/Audit/AuditLogListTest.php`

**Interfaces:**
- Consumes: `AuditScope::forUser` (BR-RPT-05), `App\Support\Identity\RoleCatalog::label()`/`options()` (label lima peran DDD, sumber yang sama dengan layar Identity I1).
- Produces: `AuditFilters { string $search, string $range ('7d'|'30d'|'90d'|'all', default '7d'), string $action, string $actor ('' | nama peran DDD | 'system') }`.
- Produces: `AuditLogList::run(User, AuditFilters): array{entries: LengthAwarePaginator<int, AuditEntryRowData>, actionOptions: list<Option>, actorOptions: list<Option>, rangeOptions: list<Option>, seesEverything: bool}`. 25 per halaman, `latest('id')`. `actionOptions` = deskripsi berbeda dalam scope penanya (urut abjad). `actorOptions` = lima peran DDD + "Sistem".
- Produces: `AuditEntryRowData { int $id, string $createdAt, string $subject, string $action, string $actor, ?string $ipAddress, ?array<string,string|int|float|bool|null> $old, ?array<string,string|int|float|bool|null> $new }` (nilai bersarang di-`json_encode` menjadi teks). `actor` = "{nama} ({label semua peran DDD-nya, dipisah koma, urutan `RoleCatalog`})", "{nama}" bila tanpa peran DDD, atau "Sistem". Satu label pilihan untuk user dua peran akan salah separuh waktu: peneliti yang sedang bertindak sebagai reviewer tercatat "Peneliti".
- Produces: `AuditSubjectLabel::for(AuditEntry): string`. Peta tipe subjek → konteks: FormSubmission "Submission" (nama = `form.title`), User "User" (`name`), UserProfile "Profil", SubmissionPeriod "Periode" (`name`), Scheme "Skema" (`name`), Form "Form" (`title`), SubmissionReviewer "Penugasan Reviewer", ResearchOutput "Luaran", ExportJob "Ekspor", Announcement "Pengumuman" (`title`). Cadangan "{class_basename} #{id}". Subjek yang sudah terhapus → "{konteks} #{id}".

- [ ] **Step 1: Tulis test yang gagal**:
  - `it('filter waktu bawaan tujuh hari')`: entri 3 hari dan 10 hari lalu → 1 baris tanpa param, 2 baris dengan `range=all`.
  - `it('pencarian mencocokkan subjek atau nama pelaku tanpa peka huruf')`: `search=biochar` mencocokkan judul form; `search=siti` mencocokkan pelaku.
  - `it('filter pelaku Sistem memuat entri tanpa aktor')`.
  - `it('label subjek dan pelaku')`: entri submit P-001 oleh Budi (researcher) → subject "Submission — Biochar Kaltim", actor "Dr. Budi Santoso (Peneliti)".
  - `it('[RPT-AUDIT-05] pilihan tipe aksi peneliti tidak membocorkan aksi di luar scope')`.
  - `it('[BUD-ITEM-06] baris audit membawa nilai lama dan baru')`.
  - `it('peneliti tidak menerima alamat IP')`: peneliti membuka daftar → `ipAddress` null di setiap baris, termasuk entrinya sendiri (UX pattern 07: kolom IP hanya Operator/Admin).
  - Pindahkan RPT-AUDIT-04/05 dan test layar yang ada ke `route('reporting.audit.index')` + `component('Reporting/AuditPage')`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan.** Pencarian: `whereLike` pada `description`, `causer.name` (`whereHasMorph` User), dan judul form subjek FormSubmission. Rentang memakai `created_at >= now()->subDays(n)`.
- [ ] **Step 4: Jalankan** `pest tests/Feature/Audit` → PASS.
- [ ] **Step 5: Commit** `feat: daftar audit terfilter dengan subjek, pelaku, dan IP`

### Task 13: Layar Audit Log — FE, tutup irisan R1

**Files:**
- Create: `resources/js/Pages/Reporting/AuditPage.vue`, `resources/js/Components/Reporting/{AuditTable,AuditEntryDialog,AuditChangeList}.vue`
- Delete: `resources/js/Pages/Audit/IndexPage.vue`
- Modify: `resources/js/lib/navigation.ts` (Audit Log → `reporting.audit.index`), `types/reporting.ts`, `docs/design/fe-v2-design.md` (§8 R1 ☑ semua sub-baris, §9 W155), memori hand-off di luar repo (bukan bagian commit)
- Test: `tests/frontend/components/Reporting/{AuditTable,AuditEntryDialog}.spec.ts`, `tests/frontend/pages/Reporting/AuditPage.spec.ts`, `tests/frontend/lib/navigation.spec.ts`

**Interfaces:**
- Consumes: props Task 12, `DataTable`, `FilterToolbar`, `FilterSelect`, `useFilters`, `ActionDialog`/`Dialog`, `InfoGrid`, `PaginationBar`, `formatDateTime`.

- [ ] **Step 1: Tulis test yang gagal**:
  - `AuditTable.spec.ts`: kolom "Waktu, Subjek, Aksi, Pelaku, IP Address"; IP null → "—"; `seesEverything` false → tanpa kolom IP (UX pattern 07: IP hanya untuk Operator/Admin; backend juga mengirim `ipAddress` null untuk peneliti, Task 12); daftar kosong → "Tidak ada aktivitas di periode ini"; `it('[RPT-AUDIT-02] baris audit tidak punya aksi hapus')` dan `it('[RPT-AUDIT-03] baris audit tidak punya aksi sunting')`: tidak ada tombol bernama "Hapus"/"Sunting"/"Edit", yang ada hanya "Lihat".
  - `AuditEntryDialog.spec.ts`: `it('[BUD-AUDIT-02] detail menyebut pelaku dan waktu')` + `it('[BUD-ITEM-06] nilai lama → baru per kunci')` ("Rp 4.000.000 → Rp 6.500.000" bila kuncinya `total`/`unit_price`, selain itu nilai mentah); entri tanpa `old`/`new` → "Tidak ada perubahan nilai yang tercatat.".
  - `AuditPage.spec.ts`: judul/subjudul frame; tombol "Export Audit Log" menaut `route('reporting.exports.index', { type: 'audit-log' })`; `seesEverything` false → banner "Daftar ini hanya memuat aktivitas pada pengajuan Anda dan akun Anda sendiri." (BR-RPT-05) dan judul tetap.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasikan** tata letak frame: header + aksi, toolbar (cari "Cari subjek / pelaku...", Waktu, Tipe Aksi, Pelaku), tabel berpaginasi server, dan dialog "Lihat". `RPT-AUDIT-07` tidak digambar sebagai panel. Ia anotasi arsitektur yang dijaga `AuditCoverageTest`.
- [ ] **Step 4: Gate penuh** `composer run quality` + `npm run quality` → exit 0. Cek browser Playwright (resep di memori): Operator membuka ketiga layar; tab Ringkasan → klik "Disetujui" → daftar operator berfilter sama; ekspor kecil xlsx terunduh; audit filter Pelaku "Sistem"; dialog Lihat menampilkan nilai lama → baru.
- [ ] **Step 5: Commit** `feat(fe): audit log v2 menggantikan layar audit legacy`, lalu `docs: irisan R1 laporan selesai`. Sarankan session baru untuk F99.
