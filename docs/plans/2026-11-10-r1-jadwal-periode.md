# R1 — Jadwal Milik Periode — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Langkah fase tidak lagi menunjuk tanggal; ia menyebut jenis tenggat + siklusnya, dan setiap gerbang waktu membaca tanggalnya dari kalender **periode milik pengajuan** — sehingga satu fase bisa dipakai banyak periode dengan tanggal masing-masing, tanpa angkatan lama kehilangan tanggalnya.

**Architecture:** Expand → pindahkan pembaca → contract. Task 2 menambah `form_phase_details.deadline_type` + `deadline_cycle` (diisi dari tanggal yang sekarang dirujuk) dan memaku invarian "pengajuan bertahap selalu berperiode". Task 3–7 memindahkan setiap pembaca dan penulis ke satu pintu baru, `SubmissionPeriod::deadlineFor(FormPhaseDetail, DeadlineType)`. Task 8 menghapus tiga kolom FK tanggal. Tidak ada migrasi data: aplikasi belum produksi (pemilik, 2026-11-10), jadi migrasi hanya mengubah skema dan DB dev di-reset dengan `migrate:fresh --seed`.

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.3, §5 butir 1–2, §6. Peta irisan: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (keputusan K4, K5, K6). DDD `ddd/generic/01_form_engine.md` (BR-FE-07, 17, 21, 25, 26, 27), `ddd/supporting/02_monev.md`, `ddd/supporting/04_scheme.md` (BR-SCH-07). ADR 0014. BDD `bdd/generic/form_engine/phase_window.feature`.

**Ukuran:** dua session. **R1-a = Task 1–5** (dokumen + skema + semua pembaca), **R1-b = Task 6–9** (penulis admin + FE + contract + penutup). `composer run quality` hijau di akhir R1-a; kedua gate hijau di akhir R1-b.

## Global Constraints

- Satu pintu tanggal: hanya `SubmissionPeriod::deadlineFor()` yang menerjemahkan (langkah, jenis) menjadi `SubmissionDate`. Tidak ada kode lain yang mencari `submission_dates` berdasarkan jenis/siklus langkah.
- Pemetaan (BR-FE-26 versi baru): `Submit`/`Output` → kalender (`step.deadline_type`, `step.deadline_cycle`); `Evaluation` → (evaluation, `step.deadline_cycle`); `Revision` → (revision, `step.deadline_cycle`), bila tak ada kembali ke tanggal kerja langkah.
- **Tanggal yang tidak ada di kalender: tertutup**, kecuali Batas Evaluasi (tetap "tak bertenggat", seperti `evaluation_date_id` null sekarang). Override (BR-FE-27) tetap bisa membuka; force close (BR-SCH-07) tidak.
- `deadline_type` langkah hanya `submit` atau `output`; `output` selalu tanpa siklus; `deadline_cycle` ≥ 1 atau null (K5).
- Invarian basis data: `form_submissions.form_phase_detail_id IS NULL OR submission_period_id IS NOT NULL`.
- Laporan monev dan Upload Luaran memakai periode induknya (sudah begitu di `StartChildSubmission`).
- Teks UI persis:
  - siklus/langkah tanpa tanggal: `"Jadwal {nama} belum diatur di periode ini. Hubungi Operator LPPM."`
  - pengajuan baru ke fase yang tidak ditautkan periodenya: `"Tahap ini tidak dibuka di periode ini."`
  - label pemilih jenis langkah: `"Pengajuan"`, `"Laporan Monev I"` … (pakai `DeadlineType::cycleName()`), `"Upload Luaran"`.
  - banner celah jadwal di kartu fase: `"Periode {nama} belum punya {n} tanggal yang dibutuhkan tahap ini."`
- **Tanpa migrasi data** (pemilik 2026-11-10: belum produksi). Migrasi tidak mem-backfill dan tidak memeriksa data lama; `EndToEndFlowSeeder` wajib jalan di skema baru karena ia satu-satunya sumber data dev.
- Gate via container (AGENTS.md); periksa `free -m` ≥ 3 GB atau jalankan di bawah watchdog, satu langkah berat sekaligus.

## Review Focus

1. **Monev II angkatan 2025 berjalan di 2026** — fase yang sama ditautkan ke periode 2026 dengan tanggal berbeda; laporan Monev II pengajuan 2025 tetap memakai tanggal periode 2025, pengajuan baru 2026 memakai tanggal 2026. Test di Task 4 (`[FE-WINDOW-15]`).
2. **Kalender periode tak punya Batas Laporan Monev n** → siklus itu tertutup dengan pesan jadwal, bukan terbuka selamanya (null lama = "tak bertenggat"). Test di Task 3 dan Task 4.
3. **Pengajuan NEEDS_REVISION di periode tanpa Batas Revisi** → kembali ke Batas Submit periode itu, bukan tertutup. Test di Task 3.
4. **Force close periode A tidak menutup pengajuan periode B** pada fase yang sama (dulu force close dibaca dari periode pemilik tanggal detail). Test di Task 3.
5. **Fase ditautkan ke periode yang belum punya tanggal apa pun** → Form-nya tidak ditawarkan sebagai pengajuan baru dan halaman mulai tidak 500. Test di Task 5.

## Kesesuaian dengan BDD

Skenario ditulis/ditulis ulang di task yang menulis test-nya; `pending.txt` tetap `max: 0`. Tag mengikuti pola `phase_window.feature`.

| ID | Isi | BR | Task |
| --- | --- | --- | --- |
| `[FE-WINDOW-04]` ditulis ulang | Dua langkah bersiklus sama di satu periode membaca satu tanggal yang sama | BR-FE-26 | 3 |
| `[FE-WINDOW-08]` ditulis ulang | Langkah yang direview di periode tanpa Batas Evaluasi siklusnya muncul sebagai celah jadwal fase | BR-FE-26 | 6 |
| `[FE-WINDOW-09]` ditulis ulang | Langkah Upload Luaran tidak bisa bersiklus dan tidak bisa direview | BR-FE-26 | 6 |
| `[FE-WINDOW-11]` ditulis ulang | Siklus Monev memakai tanggal siklusnya sendiri di kalender periode | BR-FE-26, BR-MON-05 | 4 |
| `[FE-WINDOW-13]` ditulis ulang | Pengajuan baru ditolak di periode yang tidak menautkan fasenya | BR-FE-17, BR-FE-01 | 5 |
| `[FE-WINDOW-14]` baru | Satu fase di dua periode: tiap pengajuan digerbangi tanggal periodenya | BR-FE-26 | 3 |
| `[FE-WINDOW-15]` baru | Menyiapkan periode baru tidak mengubah tanggal siklus monev angkatan lama | BR-FE-26, BR-MON-05 | 4 |
| `[FE-WINDOW-16]` baru | Tanggal kerja yang tidak ada di kalender periode menutup langkah itu; Batas Evaluasi yang tidak ada berarti tak bertenggat | BR-FE-26 | 3 |
| `[FE-WINDOW-17]` baru | Force close periode A tidak menutup pengajuan periode B pada fase yang sama | BR-SCH-07 | 3 |

FE-WINDOW-01/02/03/05/06/07/10/12 tetap; fixture-nya mengikuti skema baru di Task 3. Tidak ada BR baru (BR-FE-26 ditulis ulang di tempat), jadi `uncovered.txt` tidak berubah.

## Keputusan penulis rencana

- **R1-W1** — Tanggal kerja yang hilang dari kalender **menutup** langkah. Null lama tidak mungkin (FK NOT NULL); di skema baru "tidak ada" berarti operator belum mengisi, dan membiarkannya terbuka selamanya adalah kegagalan senyap.
- **R1-W2** — Pengajuan baru mensyaratkan baris `submission_period_phases` (fase ditautkan ke periode itu). Ini menggantikan cek "tenggat milik periode ini" (K9b) yang tidak bermakna lagi.
- **R1-W3** — Tidak ada validasi urutan tanggal kalender di irisan ini. Sekarang pun tidak ada (`AssertPhaseDeadlines` hanya memeriksa jenis/periode/siklus); urutan masuk cek kesiapan R8.

---

### Task 1: Dokumen kesepakatan — ADR 0019 dan revisi DDD (disetujui pemilik 2026-11-10)

**Files:**
- Create: `docs/adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md`
- Modify: `docs/adr/README.md` (indeks), `docs/adr/0014-…` (catatan "sumbu kapan dibaca dari jadwal periode", status tetap)
- Modify: `docs/requirement-analysis/ddd/generic/01_form_engine.md` — BR-FE-07, 17, 21, 26, 27; class diagram `FormPhaseDetail` (tiga FK tanggal → `deadline_type`, `deadline_cycle`)
- Modify: `docs/requirement-analysis/ddd/supporting/02_monev.md` (diagram "Satu FormPhase, Satu Lifecycle": tenggat dari kalender periode), `ddd/supporting/04_scheme.md` (BR-SCH-07 dibaca terhadap periode pengajuan)
- Modify: `docs/requirement-analysis/ddd/02_ubiquitous_language.md` — **Angkatan**, **Kalender periode / Jadwal**, **Langkah** (padanan `FormPhaseDetail`)

- [ ] **Step 1: Baca §6 spec dan roadmap.** Perubahan DDD/ADR §6 sudah disetujui sekaligus (pemilik 2026-11-10); tidak perlu menunggu. Tulis hanya bagian yang dibutuhkan R1 (BR-FE-07/17/21/26/27, istilah) ditambah ADR 0019 untuk seluruh desain.
- [ ] **Step 2: Tulis ADR 0019** untuk seluruh desain (bukan hanya R1): konteks = spec §1, keputusan = spec §7 + roadmap K1–K6, konsekuensi = spec §6. Bagian "Status implementasi" mendaftar R1–R8, R1 "berjalan".
- [ ] **Step 3: Tulis ulang BR di `01_form_engine.md`** — kode BR tetap supaya rujukan di kode tetap bermakna:
  - BR-FE-07: setiap langkah menyebut jenis tenggat (`submit`/`output`) + siklus.
  - BR-FE-17: jendela kerja aktor dibaca dari kalender periode pengajuan untuk langkah yang dikerjakan.
  - BR-FE-21: tanggal periode tidak bisa dihapus selama ada fase tertaut yang membutuhkan (jenis, siklus)-nya.
  - BR-FE-26: isi Global Constraints baris "Pemetaan" + "Tanggal yang tidak ada di kalender".
  - BR-FE-27: override tetap per pengajuan + langkah.
- [ ] **Step 4: Commit** — `git commit -m "docs: ADR 0019 dan jadwal milik periode di DDD"`

### Task 2: Skema expand — jenis tenggat di langkah, invarian periode pengajuan

**Files:**
- Create: `database/migrations/2026_11_12_100000_add_deadline_kind_to_form_phase_details.php`
- Modify: `app/Models/FormPhaseDetail.php` (fillable, casts, `@property`), `database/factories/FormPhaseDetailFactory.php`
- Modify: `app/Data/Request/FormPhaseDetailInput.php` (juga menghasilkan `deadline_type`/`deadline_cycle` dari `SubmissionDate` terpilih — tulis-ganda sampai Task 6)
- Test: `tests/Feature/Migrations/AddDeadlineKindToFormPhaseDetailsTest.php`

**Interfaces:**
- Produces: kolom `form_phase_details.deadline_type` (string NOT NULL, cast `DeadlineType`), `deadline_cycle` (smallint NULL, cast `integer`); `submission_date_id` menjadi nullable; `FormPhaseDetail::$deadline_type: DeadlineType`, `$deadline_cycle: ?int`; check constraint `form_submissions_detail_has_period` = `form_phase_detail_id IS NULL OR submission_period_id IS NOT NULL`.

- [ ] **Step 1: Tulis test skema:** `it('menolak pengajuan bertahap tanpa periode')` — insert `form_submissions` dengan detail dan `submission_period_id` null → `QueryException`; `it('menyimpan jenis dan siklus langkah')` — factory menghasilkan `deadline_type = DeadlineType::Submit`, `deadline_cycle` null.
- [ ] **Step 2: Jalankan** `./vendor/bin/pest tests/Feature/Migrations/AddDeadlineKindToFormPhaseDetailsTest.php` → FAIL.
- [ ] **Step 3: Implementasi migrasi** — skema saja, tanpa backfill. `down()` menghapus constraint dan dua kolom, `submission_date_id` kembali NOT NULL.
- [ ] **Step 4: Model + factory + tulis-ganda** (factory mengisi jenis/siklus sesuai tanggal yang ia buat).
- [ ] **Step 5: Jalankan seluruh Pest.** Fixture yang membuat pengajuan bertahap tanpa periode gagal oleh constraint; perbaiki fixture-nya (beri periode), jangan longgarkan constraint.
- [ ] **Step 6: Commit** — `feat(form-engine): jenis tenggat di langkah fase dan periode wajib bagi pengajuan bertahap`

### Task 3: Satu pintu tanggal dan gerbang inti

**Files:**
- Modify: `app/Models/SubmissionPeriod.php`, `app/Models/FormPhaseDetail.php`, `app/Support/SubmissionPeriodGate.php`, `app/Support/PhaseAccessGate.php`
- Modify: `app/Queries/Review/EvaluationDueDate.php`, `app/Queries/Submission/EvaluationDueDate.php`, `app/Queries/Submission/OperatorSubmissionDetail.php` (label tahap), `app/Queries/Submission/ResearcherSubmissionDetail.php`
- Modify: `docs/requirement-analysis/bdd/generic/form_engine/phase_window.feature`
- Test: `tests/Feature/FormEngine/PeriodScheduleTest.php` (baru), fixture `PhaseWindowTest.php`, `DeadlineOverrideTest.php`, `PhaseOverrideTest.php`

**Interfaces:**
- Consumes: kolom Task 2.
- Produces:
  - `SubmissionPeriod::deadlineFor(FormPhaseDetail $step, DeadlineType $type): ?SubmissionDate` — memakai relasi `submissionDates` yang dimuat (`loadMissing`), bukan kueri per panggilan.
  - `FormPhaseDetail::isWithinDeadline(SubmissionPeriod $period, DeadlineType $type = DeadlineType::Submit): bool` (menggantikan versi tanpa periode).
  - `FormPhaseDetail::opensSubmission(): bool` — dari kolom (`submit` && siklus null).
  - `FormPhaseDetail::deadlineLabel(): string` — `$this->deadline_type->label($this->deadline_cycle)`.
  - `SubmissionPeriodGate::isForceClosed(SubmissionPeriod $period, FormPhaseDetail $step): bool`.
  - `PhaseAccessGate::isOpenFor/deadlineFor/presentFor` — tanda tangan tetap; periode = `$submission->submissionPeriod`.
  - Relasi tanggal lama dan `deadlineOf()` **tetap ada** (membaca kolom lama) sampai Task 8, untuk pembaca yang belum dipindah. Setiap pemanggil tanda tangan yang berubah di task ini (`isWithinDeadline`, `isForceClosed`) diperbarui **di task ini juga** secara mekanis — kirim periode pengajuan/induk — supaya suite tetap hijau; penulisan ulang perilakunya di Task 4–5.

- [ ] **Step 1: Tulis test** di `PeriodScheduleTest.php` (fixture: satu fase, langkah pengajuan `needs_review`, dua periode A dan B tertaut dengan tanggal berbeda):
  - `it('[FE-WINDOW-14] menggerbangi tiap pengajuan dengan tanggal periodenya')` — Batas Submit A lewat, B belum; pengajuan A tertutup, B terbuka (`PhaseAccessGate::isOpenFor`).
  - `it('[FE-WINDOW-16] menutup langkah yang tanggal kerjanya tidak ada di kalender')` — periode tanpa Batas Submit → `isWithinDeadline` false; tanpa Batas Evaluasi → `isWithinDeadline($period, Evaluation)` true.
  - `it('[FE-WINDOW-04] dua langkah bersiklus sama membaca satu tanggal')`.
  - `it('kembali ke Batas Submit periode saat Batas Revisi tidak ada')` — pengajuan NEEDS_REVISION; `PhaseAccessGate::deadlineFor` = Batas Submit periodenya.
  - `it('[FE-WINDOW-17] force close periode A tidak menutup pengajuan periode B')`.
  - `it('memakai Batas Evaluasi periode pengajuan sebagai tenggat penugasan')` — `EvaluationDueDate::run` (Review) = Batas Evaluasi periode pengajuan; tanpa tanggal → `now()->addWeek()` (perilaku lama).
- [ ] **Step 2: Jalankan** `./vendor/bin/pest tests/Feature/FormEngine/PeriodScheduleTest.php` → FAIL (`deadlineFor` tidak ada).
- [ ] **Step 3: Implementasi** sesuai Interfaces dan Global Constraints. `PhaseAccessGate::isOpenFor` dengan periode null (seharusnya mustahil sesudah Task 2) → tertutup kecuali override. Komentar BR-FE-26/BR-SCH-07 diperbarui ke teks baru.
- [ ] **Step 4: Tulis ulang FE-WINDOW-04 dan tambah 14/16/17** di `phase_window.feature`; perbarui fixture test window/override lama ke skema baru. Jalankan `./vendor/bin/pest tests/Feature/FormEngine tests/Unit/Bdd` → PASS.
- [ ] **Step 5: Commit** — `feat(form-engine): tenggat langkah dibaca dari kalender periode pengajuan`

### Task 4: Siklus monev dan Upload Luaran membaca periode induk

**Files:**
- Modify: `app/Queries/Monev/MonevCycles.php` (`details()`, `whereHasCycles()`, `nameOf()`, `stateOf()`, `cycle()`), `app/Data/View/Monev/MonevCycleData.php` (`deadline: ?string`), `app/Support/MonevAccessGate.php`, `app/Notifications/MonevStageOpenedNotification.php`, `app/Data/View/Monev/MonevReportHeaderData.php`, `app/Support/Reporting/Exports/MonevRecapExport.php`, `app/Queries/ResearchOutput/OutputWriteAccess.php`, `app/Queries/ResearchOutput/OutputUploadDetail.php`, `app/Http/Controllers/ResearchOutput/ResearchOutputController.php`, `app/Queries/Monev/MonevCycleManagement.php`, `app/Queries/Monev/MonevReportForm.php`, `app/Actions/Monev/SaveMonevReport.php`, `app/Actions/Monev/SubmitMonevReport.php`
- Modify: `resources/js/types/` (tipe `MonevCycle.deadline: string | null`) dan pembaca `deadline` siklus (`resources/js/Components/Monev/ManagedCycleCard.vue`, `resources/js/Pages/Monev/ReportFormPage.vue`) — tampilkan "—" bila null
- Modify: `docs/requirement-analysis/bdd/supporting/monev.feature` bila ada skenario yang menyebut tanggal milik detail; `phase_window.feature` FE-WINDOW-11, 15
- Test: `tests/Feature/Monev/MonevCycleScheduleTest.php` (baru); fixture monev/luaran yang ada

**Interfaces:**
- Consumes: `SubmissionPeriod::deadlineFor`, `FormPhaseDetail::isWithinDeadline(SubmissionPeriod, …)` (Task 3).
- Produces: `MonevCycles::details(FormSubmission $parent)` memfilter `deadline_type != 'output'` di kolom langkah (tanpa join `submission_dates`); `nameOf()` membaca `deadline_cycle`; `MonevCycleData::$deadline` nullable; `MonevStageOpenedNotification` menerima periode induk (atau membaca `$parent->submissionPeriod`) untuk tanggalnya.

- [ ] **Step 1: Tulis test:**
  - `it('[FE-WINDOW-15] menyiapkan periode baru tidak mengubah tanggal monev angkatan lama')` — fase F di periode 2025 (Batas Laporan Monev II = 2026-06-01) dengan induk APPROVED; tautkan F ke periode 2026 (Batas Laporan Monev II = 2027-06-01). `MonevCycles::run($parent)` siklus Monev II ber-`deadline` 2026-06-01.
  - `it('[FE-WINDOW-11] siklus memakai tanggal siklusnya sendiri')`.
  - `it('menutup siklus yang jadwalnya belum ada di periode induk')` — state `Closed`, `blockedReason` = `"Jadwal Monev II belum diatur di periode ini. Hubungi Operator LPPM."`, `deadline` null.
  - `it('menggerbangi Upload Luaran dengan Batas Akhir periode induk')` — `OutputWriteAccess`.
  - `it('menghitung siklus ekspor rekap dari langkah, bukan tanggal')` — `MonevRecapExport` menghasilkan jumlah baris sama dengan `count(MonevCycles::details($parent))`.
- [ ] **Step 2: Jalankan** test baru → FAIL.
- [ ] **Step 3: Implementasi.** `whereHasCycles()` dan `MonevRecapExport` mengganti subkueri `submission_dates` dengan `later.deadline_type != 'output'`.
- [ ] **Step 4: Jalankan** `./vendor/bin/pest tests/Feature/Monev tests/Feature/ResearchOutput tests/Feature/Reporting tests/Unit/Bdd` → PASS; `npx vitest run` untuk komponen monev yang diubah → PASS.
- [ ] **Step 5: Commit** — `feat(monev): siklus monev dan luaran membaca kalender periode induk`

### Task 5: Pengajuan baru tanpa cek "tenggat milik periode ini"

**Files:**
- Modify: `app/Actions/FormSubmission/StartSubmission.php`, `app/Queries/Submission/ResearcherStartOptions.php`, `app/Queries/Submission/AccessibleFormPhaseDetail.php`
- Modify: `phase_window.feature` FE-WINDOW-13
- Test: `tests/Feature/Submission/StartSubmissionTest.php`, `tests/Feature/Monev/MonevCycleNotStartableTest.php`

**Interfaces:**
- Consumes: `FormPhaseDetail::opensSubmission()` (Task 3).
- Produces: syarat pengajuan baru = periode terbuka (`SubmissionPeriodGate::isOpen`) **dan** `submission_period_phases` (periode, fase) ada (R1-W2). `AccessibleFormPhaseDetail` memfilter `deadline_type = 'submit' AND deadline_cycle IS NULL` di kolom langkah dan tidak lagi mengurutkan berdasarkan pemilik tanggal.

- [ ] **Step 1: Tulis test:**
  - `it('[FE-WINDOW-13] menolak pengajuan baru di periode yang tidak menautkan fasenya')` — `ValidationException` pada `form_phase_id` dengan pesan `"Tahap ini tidak dibuka di periode ini."`; `ResearcherStartOptions::run` tidak menawarkan Form itu di periode tersebut.
  - `it('menawarkan fase yang sama di dua periode terbuka yang menautkannya')` — kedua periode muncul dengan Form yang sama.
  - `it('tidak menawarkan fase di periode tertaut yang belum bertanggal')` — `ResearcherStartOptions::run` tanpa periode itu; GET halaman mulai → 200.
  - Pertahankan `MonevCycleNotStartableTest` hijau (langkah monev & luaran tetap bukan pintu pengajuan).
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi.** Hapus `$ownedElsewhere` di `ResearcherStartOptions::offer()`; `forms()` sudah hanya melintasi fase tertaut periode, jadi syarat tautan terpenuhi di sana. `StartSubmission` menambah cek tautan di tempat cek pemilik tanggal.
- [ ] **Step 4: Jalankan** `./vendor/bin/pest tests/Feature/Submission tests/Feature/Monev tests/Unit/Bdd` → PASS. Lalu, **akhir R1-a**: `composer run quality` (berurutan, cek RAM) → hijau. Catat hasilnya di ledger.
- [ ] **Step 5: Commit** — `feat(submission): pengajuan baru mensyaratkan fase tertaut periode`

### Task 6: Penulis admin — langkah memilih jenis, periode menjaga tanggal yang dibutuhkan

**Files:**
- Modify: `app/Http/Requests/Form/FormPhaseRequest.php`, `app/Data/Request/FormPhaseDetailInput.php`, `app/Actions/Form/CreateFormPhase.php`, `app/Actions/Form/ReviseFormPhase.php`, `app/Queries/Form/PhaseEdit.php`, `app/Data/View/FormEngine/PhaseDetailEditData.php`, `app/Http/Controllers/FormEngine/FormPhaseController.php`, `app/Queries/Form/FormPhaseCards.php`, `app/Data/View/FormEngine/PhaseCardData.php`
- Rename + rewrite: `app/Actions/Form/AssertPhaseDeadlines.php` → `app/Actions/Form/AssertPhaseSteps.php`
- Delete: `app/Queries/Form/SubmissionDateOptions.php`, `app/Queries/Form/PhaseDeadlineGaps.php`
- Create: `app/Queries/Period/PeriodScheduleGaps.php`
- Modify: `app/Models/SubmissionDate.php` (`scopeWithoutPhaseDetail` → `scopeUnneededByLinkedPhases`; hapus `selectableFor`, `PHASE_DETAIL_RELATIONS`, tiga relasi `*PhaseDetails`, `phaseDetailCount()` diganti hitungan dari `PeriodScheduleGaps`/kebutuhan), `app/Actions/Period/ExtendSubmissionDates.php` (`followEvaluation`, `deleteUnlisted`), `app/Queries/Period/SubmissionPeriodReferences.php`, `app/Queries/Form/LinkablePeriods.php` bila membaca tanggal
- Modify: `phase_window.feature` FE-WINDOW-08, 09
- Test: `tests/Feature/FormEngine/PhaseStepKindTest.php` (baru), `tests/Feature/Period/PeriodScheduleGapsTest.php` (baru), `FormPhaseDeadlineTest.php`, `FormPhaseRevisionTest.php`, `PhaseEditTest.php`, `tests/Feature/Period/PeriodIndexTest.php`, `tests/Feature/Scheme/DeadlineExtensionTest.php`

**Interfaces:**
- Consumes: Task 3.
- Produces:
  - Input form fase per baris: `phase_details.*.deadline_type` (`required|in:submit,output`), `phase_details.*.deadline_cycle` (`nullable|integer|min:1|max:10`); `FormPhaseDetailInput::$deadlineType: DeadlineType`, `$deadlineCycle: ?int`. Tiga field `*_date_id` hilang dari request dan DTO.
  - `AssertPhaseSteps::run(array $details): void` — `ValidationException` dengan kunci `phase_details.{i}.deadline_cycle` ("Upload Luaran tidak bersiklus.") dan `phase_details.{i}.needs_review` ("Detail Upload Luaran tidak direview.").
  - `PeriodScheduleGaps::run(SubmissionPeriod $period): list<array{phase: string, step: int, label: string}>` — untuk setiap langkah fase tertaut: tanggal kerja hilang; Batas Evaluasi hilang bila `needs_review`. `PeriodScheduleGaps::forPhase(FormPhase $phase): list<array{period: string, count: int}>` untuk `PhaseCardData::$deadlineGaps` (bentuk props tetap).
  - `PhaseDetailEditData`: `deadlineType: string`, `deadlineCycle: ?int`, `deadlineLabel: string` menggantikan tiga id + `currentDeadlines`; prop halaman `deadlineOptions` dihapus.
  - `SubmissionDate::scopeUnneededByLinkedPhases()` — tanggal (jenis, siklus) yang tidak dibutuhkan langkah mana pun dari fase yang ditautkan periodenya (submit/output: jenis+siklus sama; evaluation/revision: siklus sama). Dipakai `ExtendSubmissionDates::deleteUnlisted`.
  - `ExtendSubmissionDates::followEvaluation(SubmissionDate $date, Carbon $to)` — penugasan yang `due_date` = tanggal lama dan pengajuannya berperiode `$date->submission_period_id` dengan langkah ber-`deadline_cycle` = `$date->cycle`.
  - `SubmissionPeriodReferences` hanya menghitung `'pengajuan'` (tanggal periode tidak lagi dirujuk dari luar periode).

- [ ] **Step 1: Tulis test:**
  - `it('menyimpan langkah dengan jenis dan siklus')` — POST/PUT fase dengan `deadline_type=submit, deadline_cycle=2` → baris tersimpan; tanpa field tanggal apa pun.
  - `it('[FE-WINDOW-09] menolak Upload Luaran bersiklus atau direview')`.
  - `it('[FE-WINDOW-08] mendaftar celah Batas Evaluasi langkah yang direview')` — `PeriodScheduleGaps::run` memuat `['phase' => …, 'step' => 1, 'label' => 'Batas Evaluasi']`; kartu fase memuat `deadlineGaps` `[['period' => 'Periode A', 'count' => 1]]`.
  - `it('tidak menghapus tanggal yang dibutuhkan fase tertaut')` — revisi periode tanpa Batas Laporan Monev I sementara fase tertaut punya langkah siklus 1 → tanggal tetap ada.
  - `it('menggeser due_date penugasan saat Batas Evaluasi periode diperpanjang')`.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** sesuai Interfaces. Tulis-ganda Task 2 dicabut: DTO tidak lagi tahu `submission_date_id`, dan langkah baru tersimpan dengan tiga kolom lama null (sudah nullable sejak Task 2) sampai Task 8 menghapusnya.
- [ ] **Step 4: Jalankan** `./vendor/bin/pest tests/Feature/FormEngine tests/Feature/Period tests/Unit/Bdd` → PASS.
- [ ] **Step 5: Commit** — `feat(form-engine): langkah fase memilih jenis tenggat; celah jadwal per periode`

### Task 7: Layar — editor langkah dan banner celah jadwal

**Files:**
- Modify: `resources/js/types/form-engine.ts`, `resources/js/lib/phaseDefinition.ts`, `resources/js/Components/FormEngine/PhaseDetailDialog.vue`, `PhaseDetailList.vue`, `PhaseCard.vue`, `resources/js/Pages/FormEngine/PhaseEditPage.vue`
- Delete: `resources/js/Components/FormEngine/PhaseDeadlineSelect.vue`
- Test: `resources/js/lib/phaseDefinition.spec.ts`, `PhaseEditPage.spec.ts` (yang ada), spec dialog baru bila belum ada

**Interfaces:**
- Consumes: props Task 6 (`PhaseDetailEditData` baru, tanpa `deadlineOptions`).
- Produces: payload simpan per baris `{ deadline_type, deadline_cycle, … }`; satu pemilih "Jenis langkah" dengan opsi `Pengajuan` (`submit`, null), `Laporan Monev I…X` (`submit`, n), `Upload Luaran` (`output`, null). Memilih Upload Luaran mematikan "Perlu review".

- [ ] **Step 1: Tulis test Vitest:** `phaseDefinition` membangun payload `{deadline_type: 'submit', deadline_cycle: 2}` dari pilihan "Laporan Monev II"; dialog menyembunyikan/mematikan "Perlu review" saat Upload Luaran; daftar langkah menampilkan `deadlineLabel`; banner kartu fase memakai teks persis dari Global Constraints.
- [ ] **Step 2: Jalankan** `npx vitest run resources/js/lib/phaseDefinition.spec.ts …` → FAIL.
- [ ] **Step 3: Implementasi** mengikuti komponen FE v2 yang sudah dipakai dialog ini (`docs/design/fe-v2-design.md`); tanpa `any`, tanpa `console.log`.
- [ ] **Step 4: Jalankan** `npm run quality` (cek RAM dulu) → hijau.
- [ ] **Step 5: Commit** — `feat(fe): editor langkah fase memilih jenis tenggat`

### Task 8: Skema contract — hapus rujukan tanggal di langkah

**Files:**
- Create: `database/migrations/2026_11_12_200000_drop_date_references_from_form_phase_details.php`
- Modify: `app/Models/FormPhaseDetail.php` (hapus `submissionDate()`, `evaluationDate()`, `revisionDate()`, `deadlineOf()`, `scopeDeadlinedBy()`, properti terkait), `database/factories/FormPhaseDetailFactory.php` (tidak lagi membuat `SubmissionDate`; state `->forPeriod(SubmissionPeriod $p)` hanya menautkan fase), `database/seeders/EndToEndFlowSeeder.php`
- Test: `tests/Feature/Migrations/DropDateReferencesFromFormPhaseDetailsTest.php`, `tests/Feature/ModelSchemaDriftTest.php` (harus hijau)

- [ ] **Step 1: Tulis test migrasi** — sesudah `up()`, ketiga kolom tidak ada; `down()` melempar `RuntimeException` ("Migrasi ini tidak bisa dibalik: rujukan tanggal per langkah sudah dilebur ke kalender periode.").
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi** migrasi dan pembersihan model/factory/seeder.
- [ ] **Step 4: Verifikasi tidak ada sisa:** `grep -rnE "submission_date_id|evaluation_date_id|revision_date_id|deadlineOf\(|->submissionDate\b|evaluationDate|revisionDate|deadlinedBy" app resources/js database/factories database/seeders tests` → hanya berkas migrasi lama dan test migrasi. Jalankan `php artisan migrate:fresh --seed` di DB uji (bukan DB dev pemilik) → sukses, termasuk `EndToEndFlowSeeder`.
- [ ] **Step 5: Jalankan** seluruh Pest → PASS. **Commit** — `refactor(form-engine): hapus rujukan tanggal di langkah fase`

### Task 9: Penutup

- [ ] **Step 1:** `docs/requirement-analysis/bdd/` — pastikan setiap ID di tabel BDD di atas ada di `.feature` dan punya test; `pending.txt`/`uncovered.txt` tidak bertambah (`BddCoverageTest` hijau).
- [ ] **Step 2:** ADR 0019 "Status implementasi": R1 selesai, sebut commit akhir.
- [ ] **Step 3:** Gate penuh, berurutan, cek `free -m` sebelum tiap langkah berat: `composer run quality`, lalu `npm run quality`. Laporkan hasilnya apa adanya (jumlah test Pest & Vitest).
- [ ] **Step 4:** Beri tahu pemilik: DB dev perlu `php artisan migrate:fresh --seed` (tanpa migrasi data, disepakati 2026-11-10).
- [ ] **Step 5: Commit** — `docs: status R1 jadwal milik periode`
