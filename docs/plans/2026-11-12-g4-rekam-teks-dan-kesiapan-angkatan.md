# G4 — Rekam Teks saat Submit (R7) + Cek Kesiapan Angkatan (R8) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Pengajuan dan laporan monev yang sudah disubmit terbaca dengan teks pertanyaan dan label opsi yang dilihat pengisinya saat submit; angkatan (Periode × Tahap) yang setup-nya cacat tidak menerima pengajuan baru, dan Operator melihat daftar ceknya per angkatan di halaman periode.

**Architecture:** R7 — satu kolom `form_submissions.submitted_field_labels` (jsonb, nullable) diisi di Action submit/kirim ulang; satu pintu baca `ReadableSubmissionAnswers` memakai rekaman bila ada dan pengajuan tidak sedang bisa disunting pengisinya, selain itu teks terkini. Nilai jawaban tetap dibaca dari `form_field_responses`. R8 — satu kueri `CohortReadiness` menilai tiga cek per angkatan dari versi struktur angkatan (BR-FE-32); setiap isu **memblokir** (dibutuhkan sekarang: langkah Pengajuan, urutan tanggal) atau **peringatan** (dibutuhkan nanti: langkah monev/luaran — saat dipakai, BR-FE-26 tetap menutupnya). Kueri itu memuat datanya sendiri (tidak memakai relasi tersaring milik pemanggil), dan dipakai `StartSubmission`, `ResearcherStartOptions`, daftar periode, dan halaman Kesiapan Angkatan. Tanpa migrasi data.

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.7, §4.9 (tanpa isi-tanggal-dari-periode-lalu, dibuang pemilik). Peta irisan: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G4 = R7 + R8). ADR 0019. DDD `ddd/generic/01_form_engine.md`, `ddd/02_ubiquitous_language.md`.

**Basis:** `feat/init-be-development` @ `16eb02a7` (G3 selesai).

**Ukuran:** dua session. **G4-a = Task 1** (R7, BE saja), **G4-b = Task 2–3** (R8 BE + FE, lalu penutup gelombang). Kedua gate hijau di akhir tiap session.

## Global Constraints

- **Angkatan** = (`submission_period_id`, `form_phase_id`). Versi struktur angkatan = `PhaseVersionFor` (pin, atau terbaru). **Angkatan berjalan** = angkatan yang punya baris `phase_version_pins` (pin lahir bersama pengajuan pertama, G3-W2) — tidak pernah diblokir cek kesiapan.
- Rekaman teks hanya ditulis oleh `SubmitFormSubmission`, `ResubmitFormSubmission`, `SubmitMonevReport`, di `update` yang sama dengan perubahan statusnya; tidak ada entri audit baru (BR-RPT-09: satu Action, satu entri).
- Bentuk rekaman (`FieldLabel`, phpstan-type milik `FieldLabelSnapshot`): `list<array{form_field_id: int, type: string, label: string, options: list<array{id: int, label: string}>}>` — urut `order`, tanpa tipe `scheme_selector`/`trl_selector` (BR-SCH-09), opsi **termasuk yang sudah soft delete** (BR-FE-24).
- Teks galat persis (kunci dalam kurung):
    - (`form_phase_id`) `"Tahap ini belum siap menerima pengajuan di periode ini. Hubungi Operator LPPM."`
- **Sifat isu (keputusan pemilik 2026-11-12, G4-W8):** *memblokir* = isu langkah Pengajuan (`FormPhaseDetail::opensSubmission()`) dan setiap pelanggaran urutan tanggal; *peringatan* = isu langkah lain (Laporan Monev n, Upload Luaran). Angkatan ditolak hanya bila **belum berjalan** dan punya isu memblokir.
- Pesan cek kesiapan persis (`label` = `DeadlineType::label($cycle)`, `stepLabel` = `DeadlineType::stepLabel($cycle)`), satu per (jenis, siklus):
    - Tanggal, memblokir: `"{label} belum diatur."`
    - Tanggal kerja (`submit`/`output`), peringatan: `"{label} belum diatur — langkah {stepLabel} tertutup sampai tanggalnya diatur."`
    - Batas Evaluasi, peringatan: `"{label} belum diatur — evaluasi {stepLabel} tak bertenggat sampai tanggalnya diatur."`
    - Urutan (selalu memblokir): `"{label belakangan} tidak boleh lebih awal dari {label sebelumnya}."` — mis. `"Batas Evaluasi tidak boleh lebih awal dari Batas Submit."`
    - Form Evaluasi (memblokir di langkah Pengajuan, peringatan di langkah lain): `"Langkah {order} ({stepLabel}) direview, tapi belum punya Form Evaluasi wajib yang aktif."`
- Judul baris cek persis (urutan tampil): `"Tanggal setiap langkah sudah diatur"`, `"Urutan tanggal masuk akal"`, `"Langkah yang direview punya Form Evaluasi wajib"`. Baris punya tiga keadaan: lolos, peringatan (hanya isu peringatan), gagal (ada isu memblokir).
- Status angkatan dan teks UI persis:
    | Status | Kapan | Lencana | Keterangan |
    | --- | --- | --- | --- |
    | `ready` | tanpa isu | `"Siap"` | — |
    | `pending` | hanya isu peringatan (berjalan atau belum) | `"Siap — ada yang menyusul"` | `"Pengajuan baru diterima. Lengkapi sebelum langkahnya dimulai."` |
    | `blocked` | isu memblokir, belum berjalan | `"Belum siap"` | `"Pengajuan baru di tahap ini ditolak sampai semua cek lolos."` |
    | `needs_fix` | isu memblokir, sudah berjalan | `"Perlu perbaikan"` | `"Angkatan ini sudah punya pengajuan, jadi tidak diblokir."` |
- Teks UI lain persis: judul halaman `"Kesiapan Angkatan"`, subjudul `"Periode {nama} — daftar cek tiap tahap sebelum menerima pengajuan baru."`; tombol tabel periode `"Kesiapan"`; lencana baris `"{n} angkatan belum siap"` — n = angkatan `blocked` + `needs_fix`, tidak tampil bila n = 0; kosong `"Belum ada tahap di periode ini"`.
- Route: `admin.submission-periods.readiness` GET `/admin/submission-periods/{submissionPeriod}/readiness`, di grup `['auth', 'can:periods.manage']` prefix `admin` (sama dengan `evaluation-versions`).
- BR baru + skenarionya + test-nya mendarat dalam satu commit (BddCoverageTest assertion 3, 4, 7; `pending.txt`/`uncovered.txt` tetap `max: 0`). Setiap ID skenario butuh test **Pest** (BddCoverage tidak memindai Vitest).
- Tag frame: R7 `@RESEARCHER-02-03` (pengajuan) / `@RESEARCHER-05-02` (laporan monev); R8 `@OPERATOR-09-01` (halaman periode) / `@RESEARCHER-02-02` (mulai pengajuan). Katalog frame tidak ditambah.
- QualityBudget: blok komentar maks 3 baris prosa; `mixed` tetap ≤ 17 (bentuk jsonb lewat phpstan-type, bukan `mixed`).
- Gate via container (AGENTS.md, memori: `simpas-ci:8.4`, `DB_PORT=5433`, `APP_URL` override, `COMPOSER_PROCESS_TIMEOUT=0`); `free -m` ≥ 3 GB atau watchdog, satu langkah berat sekaligus, `npm run quality` tidak pernah tanpa watchdog.

## Review Focus

1. **Relasi tersaring pemanggil bocor ke cek kesiapan.** `ResearcherStartOptions` memuat `formPhaseDetails` yang disaring aturan akses peneliti; bila `CohortReadiness` memakai relasi itu, langkah monev yang tidak bisa diakses peneliti hilang dari cek urutan, jadi Batas Laporan Monev I yang lebih awal dari Batas Submit (memblokir) lolos dan angkatan cacat ditawarkan. Harapan: tetap tidak ditawarkan. Test di Task 2 (`it('judges readiness on every step even when the researcher can access only the submission step')`).
2. **Opsi yang sudah soft delete sebelum submit** (draf memilihnya, Admin menghapus opsinya, peneliti submit) → layar sesudah submit menampilkan label opsi itu, bukan id mentah. Test di Task 1 (`it('records the label of an option deleted before submit')`).
3. **Laporan monev** lewat `SubmitMonevReport` merekam teks dan `MonevReportDetail` membacanya — jalur kedua yang mudah terlewat karena tidak lewat `SubmitFormSubmission`. Test di Task 1 (`it('records labels when a monev report is submitted')`).
4. **Angkatan berjalan dinilai dengan versi pin-nya**, bukan versi terbaru: Versi 2 membuat langkah Pengajuan direview, dan Batas Evaluasi belum ada di kedua periode → angkatan terpaku Versi 1 tetap "Siap", angkatan baru di periode lain "Belum siap". Test di Task 2 (`it('checks the pinned version of a running cohort, not the latest')`).
5. **Daftar periode tanpa N+1**: cek kesiapan untuk satu halaman (15 baris) dihitung sekali. Jumlah kueri `SubmissionPeriodList` untuk 1 dan 3 periode bertahap sama. Test di Task 2 (`it('computes readiness for a page of periods in a constant number of queries')`).

## Kesesuaian dengan BDD

| ID | Berkas | Kelas | Isi | Task |
| --- | --- | --- | --- | --- |
| `[FE-SNAP-01]` | `bdd/generic/form_engine/submitted_labels.feature` | `@happy` | Label field dan opsi yang diubah sesudah submit tidak mengubah yang terbaca | 1 |
| `[FE-SNAP-02]` | 〃 | `@boundary` | Draf memakai teks terkini | 1 |
| `[FE-SNAP-03]` | 〃 | `@boundary` | Field yang dihapus sesudah submit tetap terbaca; field yang ditambah sesudahnya tidak muncul | 1 |
| `[FE-SNAP-04]` | 〃 | `@boundary` | Selama revisi teks terkini; kirim ulang merekam teks saat itu | 1 |
| `[FE-SNAP-05]` | 〃 | `@boundary` | Pengajuan yang disubmit sebelum fitur ini memakai teks terkini | 1 |
| `[FE-READY-01]` | `bdd/generic/form_engine/cohort_readiness.feature` | `@denial` | Batas Evaluasi langkah Pengajuan yang belum diatur menolak pengajuan baru dan tahapnya tidak ditawarkan | 2 |
| `[FE-READY-02]` | 〃 | `@boundary` | Angkatan lain di periode yang sama tetap menerima pengajuan | 2 |
| `[FE-READY-03]` | 〃 | `@boundary` | Angkatan yang sudah punya pengajuan tidak diblokir; cacatnya tampil sebagai "Perlu perbaikan" | 2 |
| `[FE-READY-04]` | 〃 | `@denial` | Batas Evaluasi sebelum Batas Submit menggagalkan cek urutan | 2 |
| `[FE-READY-05]` | 〃 | `@denial` | Langkah Pengajuan direview tanpa Form Evaluasi wajib aktif menggagalkan cek | 2 |
| `[FE-READY-06]` | 〃 | `@happy` | Operator melihat daftar cek per angkatan; tabel periode menghitung angkatan yang belum siap | 2 |
| `[FE-READY-07]` | 〃 | `@boundary` | Tanggal dan Form Evaluasi langkah monev yang belum diatur tidak menahan pengajuan baru; angkatan "Siap — ada yang menyusul" | 2 |
| `[FE-WINDOW-08]` | `phase_window.feature` | — | Tetap; test-nya pindah dari `PeriodScheduleGaps::run` ke `CohortReadiness` (B2) | 2 |

## Keputusan penulis rencana

- **G4-W1** — Rekaman = **satu kolom jsonb di `form_submissions`**, bukan kolom di tiap `form_field_responses`: field opsional yang tidak dijawab tidak punya baris jawaban, jadi kolom per jawaban tidak bisa menjawab "field apa yang terlihat saat submit" (field yang ditambah/dihapus sesudahnya). Nilai tetap dari `form_field_responses` — jawaban tidak berubah sesudah submit kecuali lewat revisi, yang merekam ulang.
- **G4-W2** — Rekaman dipakai bila tidak null **dan** status bukan `Draft`/`NeedsRevision`. Revisi = pengajuan yang dibuka lagi untuk diisi, jadi ia memakai teks terkini seperti draf (spec: "Draf memakai teks terkini"); `ResubmitFormSubmission` merekam ulang.
- **G4-W3** — **Luaran di luar R7.** Spec §4.7 menyebut luaran, tetapi luaran di kode ini bukan Form: metadatanya dari `output_type_definitions.fields`, tanpa editor label dan tanpa momen submit (`SaveResearchOutput` langsung menyimpan). Tidak ada teks yang bisa berubah di bawahnya.
- **G4-W4** — Cek kesiapan hanya menilai yang **masih bisa salah sesudah terbit**: tanggal (BR-FE-26), urutan tanggal (BR-FE-17), Form Evaluasi wajib aktif di langkah `needs_review`. "Tepat satu Form pengajuan sebagai langkah pertama" dijamin BR-FE-28 saat terbit; "tidak ada draf yang belum pernah terbit" dijamin BR-FE-33/34 (G3-W6). Keduanya tidak diulang.
- **G4-W5** — Urutan tanggal dibandingkan hanya antar tanggal yang **ada** dan yang dibaca langkah angkatan: per siklus c langkah `needs_review` — Batas Submit/Laporan c ≤ Batas Evaluasi c ≤ Batas Revisi c (Batas Revisi dibanding Batas Submit bila Batas Evaluasi tidak ada); antar langkah `submit` berurutan siklus (utama dulu) — Batas c ≤ Batas c+1. Sama boleh. Batas Akhir tidak dibandingkan (spec tidak memintanya).
- **G4-W6 (B2)** — `PeriodScheduleGaps::handle()` dihapus (tak berpemanggil); kelasnya tetap untuk `forPhases`/`missingCount` (kartu fase, tautan periode), `missingTypes` menjadi `public static` dan dipakai `CohortReadiness`. `AsAction` + `@method run` dibuang; `ChangesNoState` tetap. Dua test `run()` pindah ke `CohortReadinessTest` dengan ID yang sama.
- **G4-W7** — `CohortReadiness::run(list<int> $periodIds)` memuat sendiri periode, tanggal, tautan, pin, versi, langkah, dan pemasangan Form Evaluasi dalam jumlah kueri tetap — pemanggil hanya memberi id (Review Focus 1, 5). Lencana tabel menghitung angkatan `blocked` + `needs_fix`; angkatan `pending` sudah menerima pengajuan, jadi tidak dihitung "belum siap".
- **G4-W8 (keputusan pemilik 2026-11-12, mengubah spec §4.9 "setiap fase punya tanggal untuk semua langkahnya")** — yang dibutuhkan sekarang memblokir, yang dibutuhkan nanti diperingatkan, saat dipakai tetap gagal-tutup. Saat periode dibuka, tanggal monev tahun berikutnya biasanya belum diketahui; mewajibkannya menahan pengajuan tanpa alasan atau mendorong tanggal karangan. Aman karena BR-FE-26 menutup langkah bertanggal kosong ("Jadwal … belum diatur"). Pengingat Operator saat tanggal Monev n masih kosong sesudah langkah sebelumnya lewat **tidak** termasuk G4.

---

### Task 1: Rekam teks pertanyaan dan opsi saat submit (R7, BE)

**Files:**

- Create: `database/migrations/2026_11_17_100000_add_submitted_field_labels_to_form_submissions.php`
- Create: `app/Queries/Submission/FieldLabelSnapshot.php`
- Modify: `app/Models/FormSubmission.php` (`@property` + cast `array`), `app/Queries/Submission/ReadableSubmissionAnswers.php`, `app/Actions/FormSubmission/SubmitFormSubmission.php` (`markSubmitted`), `ResubmitFormSubmission.php`, `app/Actions/Monev/SubmitMonevReport.php`
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-35, sesudah BR-FE-34), `ddd/02_ubiquitous_language.md` (**Rekaman teks**)
- Create: `docs/requirement-analysis/bdd/generic/form_engine/submitted_labels.feature` (FE-SNAP-01..05)
- Test: `tests/Feature/FormEngine/SubmittedFieldLabelsTest.php` (baru)

**Interfaces:**

- Skema: `form_submissions.submitted_field_labels` jsonb nullable; `down()` membuang kolomnya.
- `FieldLabelSnapshot::run(FormSubmission $submission): list<FieldLabel>` — field Form hidup (tanpa soft delete) terurut `order`, opsi `withTrashed`, tanpa tipe pemilih kolom; satu kueri field + satu kueri opsi. `implements ChangesNoState`. `// BR-FE-35`.
- `FormSubmission`: `@property list<FieldLabel>|null $submitted_field_labels` (`@phpstan-import-type FieldLabel from FieldLabelSnapshot`), cast `'submitted_field_labels' => 'array'`.
- `ReadableSubmissionAnswers::handle` — bentuk keluaran `list<SubmissionAnswerData>` **tidak berubah** (FE tidak disentuh). Daftar field = rekaman (G4-W2) atau `FieldLabelSnapshot::run` (teks terkini); `readable()` membaca label opsi dari bentuk `FieldLabel`, jadi kedua jalur memakai satu kode (Extract Method). `downloadUrl` tetap dari id jawaban hidup.
- Action submit: `SubmitFormSubmission::markSubmitted` → `update(['is_submitted' => true, 'submitted_field_labels' => FieldLabelSnapshot::run($submission)])`; `ResubmitFormSubmission` dan `SubmitMonevReport` menulis kolom yang sama sebelum `transitionTo`.

- [ ] **Step 1: Tulis skenario** FE-SNAP-01..05 dan **BR-FE-35** di `01_form_engine.md`: "Rekaman teks saat submit. Saat pengajuan atau laporan monev disubmit atau dikirim ulang, teks pertanyaan dan label opsi yang terlihat saat itu disimpan bersama pengajuannya. Selama pengajuan tidak bisa disunting pengisinya, tampilannya memakai rekaman itu: label yang diubah, field yang dihapus, atau field yang ditambah sesudahnya tidak mengubah yang terbaca. Draf dan pengajuan yang sedang direvisi memakai teks terkini; pengajuan yang disubmit sebelum aturan ini ada juga memakai teks terkini. Form Evaluasi tidak memerlukannya karena versinya beku (BR-REV-18). Luaran bukan Form, jadi tidak direkam." Contoh:
    ```gherkin
    @BR-FE-35 @M2 @RESEARCHER-02-03 @happy
    Scenario: [FE-SNAP-01] Label field dan opsi yang diubah sesudah submit tidak mengubah yang terbaca
      Given peneliti "P-001" mensubmit pengajuan dengan field "Anggaran" dijawab opsi "Mandiri"
      And Admin menerbitkan Form dengan label "Total Anggaran" dan opsi "Swadana"
      When Operator membuka pengajuan "P-001"
      Then ia membaca "Anggaran" dengan jawaban "Mandiri"
    ```
- [ ] **Step 2: Tulis test** `SubmittedFieldLabelsTest.php` — satu `it('[FE-SNAP-0n] …')` per skenario (ubah label lewat `FormField`/`FormFieldOption` update langsung, bukan lewat terbit; yang diuji pembacanya), ditambah Review Focus 2 dan 3, `it('stores the same labels a reviewer reads')` (`ReviewerEvaluation` di status UnderReview), `it('excludes column-backed fields from the record')`.
- [ ] **Step 3: Jalankan** `./vendor/bin/pest tests/Feature/FormEngine/SubmittedFieldLabelsTest.php` → FAIL (kolom belum ada).
- [ ] **Step 4: Implementasi** migrasi, model, `FieldLabelSnapshot`, `ReadableSubmissionAnswers`, tiga Action sesuai Interfaces.
- [ ] **Step 5: Jalankan** test baru + `tests/Feature/Submission tests/Feature/Monev tests/Feature/Review` (satu folder per panggilan) → PASS.
- [ ] **Step 6: Gate G4-a** — `composer run quality` lalu `npm run quality` (berurutan, watchdog). Hijau.
- [ ] **Step 7: Commit** — `feat(form-engine): rekam teks pertanyaan dan opsi saat submit`

### Task 2: Cek kesiapan angkatan (R8, BE + FE)

**Files:**

- Create: `app/Enums/ReadinessCheck.php`, `app/Data/View/Period/CohortReadinessData.php`, `app/Data/View/Period/ReadinessCheckData.php`, `app/Data/View/Period/ReadinessIssueData.php`, `app/Queries/Period/CohortReadiness.php`, `app/Http/Controllers/FormEngine/CohortReadinessController.php`
- Modify: `app/Queries/Period/PeriodScheduleGaps.php` (G4-W6), `app/Actions/FormSubmission/StartSubmission.php`, `app/Queries/Submission/ResearcherStartOptions.php`, `app/Queries/Period/SubmissionPeriodList.php`, `app/Data/View/FormEngine/PeriodRowData.php`, `routes/web.php`
- Modify FE: `resources/js/Components/FormEngine/PeriodTable.vue`, `resources/js/types/form-engine.ts`; Create `resources/js/Pages/FormEngine/PeriodReadinessPage.vue`
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-36), `ddd/02_ubiquitous_language.md` (**Kesiapan angkatan**, **Angkatan berjalan**)
- Create: `docs/requirement-analysis/bdd/generic/form_engine/cohort_readiness.feature` (FE-READY-01..07)
- Test: `tests/Feature/Period/CohortReadinessTest.php` (baru), `PeriodScheduleGapsTest.php` (dua test `run()` pindah ke berkas baru; sisanya tetap), `PeriodIndexTest.php`, `tests/Feature/Submission/` test `StartSubmission`/`ResearcherStartOptions` yang membuat angkatan tanpa tanggal lengkap (perbaiki fixture-nya, jangan longgarkan aturannya); `tests/frontend/pages/PeriodReadinessPage.spec.ts` (baru), `PeriodIndexPage.spec.ts`

**Interfaces:**

- `ReadinessCheck`: string enum `MissingDate = 'missing_date'`, `DateOrder = 'date_order'`, `RequiredEvaluationForm = 'required_evaluation_form'`; `title(): string` (judul baris, Global Constraints). Urutan `cases()` = urutan tampil.
- `ReadinessIssueData(string $message, bool $blocking)` (G4-W8).
- `ReadinessCheckData(string $check, string $title, list<ReadinessIssueData> $issues)` + `state(): string` (`'passed' | 'warning' | 'failed'`, dikirim ke FE sebagai properti `state`).
- `CohortReadinessData(int $phaseId, string $phaseTitle, ?string $versionLabel, bool $running, list<ReadinessCheckData> $checks)` + `hasBlockingIssues(): bool`, `blocksNewSubmissions(): bool` (= `!running && hasBlockingIssues()`), `status(): string` (`'ready' | 'pending' | 'blocked' | 'needs_fix'`, tabel Global Constraints; dikirim ke FE sebagai properti `status`).
- `CohortReadiness::run(list<int> $periodIds): array<string, CohortReadinessData>` — dikunci `CohortReadiness::key(int $periodId, int $phaseId): string` (`"{p}:{f}"`); hanya angkatan dari `submission_period_phases`; G4-W4/W5/W7; `implements ChangesNoState`; `// BR-FE-36`.
- `StartSubmission::assertAcceptsNewSubmissions` — sesudah cek "ditautkan": `blocksNewSubmissions()` → `ValidationException` (`form_phase_id`, pesan Global Constraints). Draf yang masih bisa disunting sudah dikembalikan lebih awal, jadi tidak tersentuh.
- `ResearcherStartOptions` — satu panggilan `CohortReadiness::run($periods->modelKeys())`; angkatan yang `blocksNewSubmissions()` diperlakukan seperti tahap nonaktif (ditawarkan hanya bila ada draf).
- `PeriodRowData` + `int $unreadyCohortCount` (status `blocked` + `needs_fix`); `fromModel(SubmissionPeriod $period, int $unreadyCohortCount)`. `SubmissionPeriodList` memanggil `CohortReadiness::run` sekali untuk id halaman itu.
- `CohortReadinessController::index(SubmissionPeriod $submissionPeriod): Response` → `FormEngine/PeriodReadinessPage` dengan `period: {id, name}`, `cohorts: list<CohortReadinessData>` urut judul tahap.
- FE: `PeriodReadinessPage` — `PageHeader` (kembali ke `admin.submission-periods.index`), satu kartu per angkatan: judul tahap, label versi, lencana status + keterangannya, tiga baris cek (ikon lolos/peringatan/gagal, judul, pesan di bawahnya; pesan peringatan berbeda nada dari pesan memblokir); `EmptyState` bila tanpa tahap. `PeriodTable` — tombol `"Kesiapan"` (ikon `ListChecks`) di samping "Versi Form Evaluasi", lencana `"{n} angkatan belum siap"` di kolom Status. Tipe `CohortReadiness`, `ReadinessCheck`, `PeriodReadinessPageProps` di `form-engine.ts`.

- [ ] **Step 1: Tulis skenario** FE-READY-01..07 dan **BR-FE-36** di `01_form_engine.md`: "Cek kesiapan angkatan. Setiap angkatan diperiksa terhadap versi strukturnya (BR-FE-32): tanggal yang dibaca langkahnya ada di kalender periode (BR-FE-26), urutan tanggal yang ada masuk akal (BR-FE-17), dan langkah yang direview punya Form Evaluasi wajib yang aktif. Isu langkah Pengajuan dan urutan tanggal memblokir: angkatan itu tidak menerima pengajuan baru, angkatan lain di periode yang sama tetap berjalan. Isu langkah sesudahnya hanya peringatan, karena saat dipakai langkah bertanggal kosong tetap tertutup. Angkatan yang sudah punya pengajuan tidak pernah diblokir. Operator melihat daftar cek per angkatan di halaman periode; tidak ada aksi 'Buka periode'." Contoh:
    ```gherkin
    @BR-FE-36 @M2 @RESEARCHER-02-02 @denial
    Scenario: [FE-READY-01] Batas Evaluasi langkah Pengajuan yang belum diatur menolak pengajuan baru
      Given "Periode 2026" menautkan "Hibah Internal" yang langkah Pengajuannya direview
      And kalender "Periode 2026" belum punya Batas Evaluasi
      When peneliti "P-001" memulai pengajuan "Hibah Internal" di "Periode 2026"
      Then ia ditolak dengan "Tahap ini belum siap menerima pengajuan di periode ini. Hubungi Operator LPPM."
      And "Hibah Internal" tidak ditawarkan di langkah 1 wizard

    @BR-FE-36 @M2 @OPERATOR-09-01 @boundary
    Scenario: [FE-READY-07] Tanggal langkah monev yang belum diatur tidak menahan pengajuan baru
      Given "Periode 2026" menautkan "Hibah Internal" dengan langkah Monev I
      And kalender "Periode 2026" belum punya Batas Laporan Monev I
      When Operator membuka Kesiapan Angkatan "Periode 2026"
      Then "Hibah Internal" berlencana "Siap — ada yang menyusul"
      And peneliti "P-001" bisa memulai pengajuan "Hibah Internal"
    ```
- [ ] **Step 2: Tulis test BE** `CohortReadinessTest.php` — satu `it('[FE-READY-0n] …')` per skenario (FE-READY-06 lewat `get(route('admin.submission-periods.readiness', …))` + `assertInertia` dan baris `PeriodIndexTest`; FE-READY-07 juga menegaskan langkah monev bertanggal kosong menolak laporan lewat `PhaseAccessGate`), dua test FE-WINDOW-08 pindahan, Review Focus 1, 4, 5, ditambah `it('warns, not blocks, on a monev step without a required evaluation form')`, `it('blocks on an order violation that involves a monev date')`, `it('does not compare dates that are not set')`, `it('accepts equal dates')`, `it('hides the readiness page from users without periods.manage')` (403).
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi BE** sesuai Interfaces. `grep -rn "PeriodScheduleGaps::run" app tests` → kosong.
- [ ] **Step 5: Pest** berkas baru + `tests/Feature/Period` + `tests/Feature/Submission` + `tests/Feature/FormEngine` (satu folder per panggilan) → PASS. Test lama yang gagal karena angkatannya kini "Belum siap": lengkapi tanggal/Form Evaluasi di fixture-nya.
- [ ] **Step 6: Tulis test Vitest** — `PeriodReadinessPage`: empat lencana dengan keterangan persis (`ready` tanpa keterangan), baris cek gagal dan baris peringatan menampilkan pesannya dengan ikon berbeda, baris lolos tanpa pesan, kosong → `"Belum ada tahap di periode ini"`; `PeriodIndexPage`: tombol "Kesiapan" menaut ke `route('admin.submission-periods.readiness', id)`, lencana `"2 angkatan belum siap"` tampil, tidak tampil untuk 0.
- [ ] **Step 7: Implementasi FE** (sistem desain FE v2, pola `PeriodEvaluationVersionsPage`); Vitest berkas di atas → PASS.
- [ ] **Step 8: Gate G4-b** — `composer run quality` lalu `npm run quality`. Hijau.
- [ ] **Step 9: Commit** — `feat(period): cek kesiapan angkatan`

### Task 3: Penutup G4 — seeder, review, gate, dokumen

**Files:**

- Modify: `database/seeders/EndToEndFlowSeeder.php` (bila angkatan seed-nya "Belum siap": lengkapi tanggal/Form Evaluasi wajibnya, jangan longgarkan cek), `docs/adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md` (R7/R8 "Selesai — {commit}", G4-W1/W3/W4/W8), `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.9 (satu catatan "Keputusan pemilik 2026-11-12" untuk G4-W8 di bawah butir tanggal, kalimat aslinya tidak dihapus), `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G4 selesai; rencana rinci += G4), `docs/plans/2026-11-11-fase-reuse-catatan-bug.md` (B2 → Selesai)

- [ ] **Step 1: Seeder** — `php artisan migrate:fresh --seed --force && php artisan db:seed --class=EndToEndFlowSeeder --force` di container; buka `CohortReadiness::run` untuk semua periode seed lewat `php artisan tinker --execute` dan pastikan tidak ada `blocked`. Bila klasifikasi auto-mode menolak, minta pemilik menjalankannya (`! <perintah>`) — jangan dipaksa.
- [ ] **Step 2: Review akhir gelombang** (satu review menyeluruh G4). Perbaiki Critical/Important + yang murah; sisanya ke catatan bug.
- [ ] **Step 3: Gate penuh** — `free -m`, lalu `composer run quality` dan `npm run quality`, berurutan di bawah watchdog. Laporkan angka test apa adanya.
- [ ] **Step 4: Dokumen status** (berkas di atas).
- [ ] **Step 5: Commit** — `docs: status G4 rekam teks dan kesiapan angkatan`
