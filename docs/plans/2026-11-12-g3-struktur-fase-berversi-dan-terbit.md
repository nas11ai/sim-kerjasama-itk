# G3 — Struktur Fase Berversi (R5) + Draf → Pratinjau → Terbitkan untuk Fase & Form (R6) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Struktur fase (langkah, urutan, Form + aturan akses, jenis/siklus tenggat, `needs_review`, Form Evaluasi terpasang) menjadi versi beku yang dipaku per angkatan saat pengajuan pertamanya dibuat; Fase dan Form disunting sebagai draf, dipratinjau, lalu diterbitkan dengan ringkasan dampak.

**Architecture:** K1 — versi = salin baris: `form_phase_details` (dan `step_evaluation_forms`-nya) milik `form_phase_versions`; pengajuan lama tetap ber-FK ke langkah yang persis sama. Versi pengajuan yang sudah ada = versi langkahnya sendiri; tabel pin `phase_version_pins` (K3) hanya menjawab "versi mana untuk pengajuan **baru** / layar per angkatan". Draf K2 di `configuration_drafts` (subkelas model per jenis). Form tidak berversi (non-tujuan spec §2): Terbitkan Form = `FormFieldWriter::sync` saat terbit, sehingga `created_at`/`required_since`/soft delete otomatis = waktu terbit dan BR-FE-11/12/18 tidak berubah. Tanpa migrasi data (DB dev `migrate:fresh --seed`).

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.3a, §4.5, §4.6, §7 butir 7 & 9. Peta irisan: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G3 = R5 + R6). ADR 0019. DDD `ddd/generic/01_form_engine.md`, `ddd/supporting/02_monev.md`, `ddd/02_ubiquitous_language.md`.

**Basis:** `feat/init-be-development` @ `e90215ea` (G2 + K-3 selesai).

**Ukuran:** tiga session. **G3-a = Task 1** (versi struktur + pin, BE), **G3-b = Task 2** (draf/terbit Fase, BE + FE), **G3-c = Task 3–4** (draf/terbit Form + penutup). Kedua gate hijau di akhir tiap session.

## Global Constraints

- **Angkatan** = (`submission_period_id`, `form_phase_id`). Pin per angkatan: unik (`submission_period_id`, `form_phase_id`) bernama `phase_version_pins_cohort_unique`, dibuat `createOrFirst` (pola `PinEvaluationVersion`).
- **"Pengajuan pertama dibuat"** = `StartSubmission` membuat `FormSubmission` baru (langkah Pengajuan). Laporan monev dan luaran lahir dari induk, jadi tidak pernah memaku.
- Versi yang terbit **tidak pernah diubah**: `FormPhaseVersion`, `FormPhaseDetail`, `StepEvaluationForm` melempar `LogicException` di event `updating`. Hapus hanya lewat penghapusan tahap (BR-FE-20 tetap menghitung pengajuan di semua versi).
- `title`, `description`, `is_active` Fase = nama pustaka, disunting langsung (tidak berversi, tidak lewat draf) — padanan judul Form Evaluasi. Untuk Form: `title`, `description`, `form_type_id`, `is_active` langsung; **field + opsi** lewat draf.
- Label versi persis `"Versi {n}"` (`FormPhaseVersion::label()`), `n` mulai 1, naik 1 per terbit.
- Teks galat persis (kunci dalam kurung):
    - (`form_phase_detail_id`) `"Struktur tahap ini baru saja diterbitkan ulang. Muat ulang halaman lalu mulai lagi."`
    - (`phase_details`) `"Form pengajuan tahap ini sudah dipakai angkatan berjalan, jadi tidak bisa diganti. Buat tahap baru untuk Form lain."`
    - (`form_phase_id`) `"Tahap ini belum pernah diterbitkan, jadi belum bisa ditautkan ke periode."`
    - (`phase_details`) `"Form \"{judul}\" belum pernah diterbitkan."`
    - (`draft`) `"Tidak ada draf untuk diterbitkan."` (sama dengan G2)
- Teks UI persis: lencana `"Belum terbit"`; banner Fase `"Draf belum diterbitkan. Angkatan baru masih memakai {Versi n}."` (tanpa versi: `"Draf belum diterbitkan. Tahap ini belum bisa dipakai periode."`); banner Form `"Draf belum diterbitkan. Peneliti masih melihat field terbitan terakhir."` (belum pernah terbit: `"Draf belum diterbitkan. Form ini belum bisa dipakai tahap."`); peringatan label (spec §4.6) `"Hanya untuk memperbaiki ketikan. Kalau pertanyaannya berbeda, tambahkan field baru — jawaban lama tetap tercatat di field lama."`.
- Ringkasan dampak terbit Fase: `"Terbitkan {Versi n}? Berlaku untuk angkatan yang belum punya pengajuan."` + bila m > 0 `" {m} angkatan sudah memakai versi lama dan tetap memakainya."`. Terbit Form: kalimat per butir yang > 0, berurutan — `"{a} field baru wajib — berlaku untuk pengajuan yang dibuat sesudah ini."`, `"{d} draf berjalan tidak terpengaruh."`, `"Label \"{label}\" diubah pada field yang sudah dijawab {k} pengajuan."` (satu kalimat per field), `"{r} field dihapus — jawaban lamanya tetap tersimpan."`; tanpa butir apa pun: `"Terbitkan perubahan Form ini?"`.
- Route pustaka/draf/terbit: grup `['auth', 'can:schemes.manage']`, prefix `admin`, nama `admin.` (sama dengan G1/G2).
- BR baru + skenarionya + test-nya mendarat dalam satu commit (BddCoverageTest assertion 3, 4, 7; `pending.txt`/`uncovered.txt` tetap `max: 0`).
- Tag frame: Fase `@ADMIN-08-09`; Form `@ADMIN-08-06`. Katalog frame tidak ditambah.
- Gate via container (AGENTS.md, memori: `simpas-ci:8.4`, `DB_PORT=5433`, `APP_URL` override, `COMPOSER_PROCESS_TIMEOUT=0`); `free -m` ≥ 3 GB atau watchdog, satu langkah berat sekaligus, `npm run quality` tidak pernah tanpa watchdog.

## Review Focus

1. **Versi 2 terbit di antara layar "Mulai" dan klik peneliti** pada angkatan yang belum punya pengajuan → pin lahir dengan Versi 2 dan detail Versi 1 yang dikirim ditolak dengan pesan "baru saja diterbitkan ulang"; tidak ada pengajuan di Versi 1, tidak ada pin ganda. Test di Task 1 (`it('rejects a step from a version older than the new pin')`).
2. **Fase sama di dua periode**: angkatan 2025 terpaku Versi 1 (siklus Monev I–II), Versi 2 menambah Monev III → induk 2025 tetap dua siklus, `MonevCycles::whereHasCycles` dan Rekap Monev ikut versi induk, angkatan 2026 tiga siklus. Test di Task 1 (`[FE-PHVER-03]`).
3. **Dua peneliti memulai pengajuan pertama bersamaan** → satu baris pin; `PinPhaseVersion` dua kali mengembalikan versi yang sama. Test di Task 1.
4. **Publish Fase dari draf yang dibuat sebelum Form Evaluasi terpasangnya dihapus dari pustaka / Form langkahnya dinonaktifkan** → Terbitkan memvalidasi ulang payload seperti simpan baru (`AssertPhaseSteps`, BR-FE-30 terbit-saja, Form terbit) dan menolak dengan galat kunci; draf tidak hilang. Test di Task 2.
5. **Terbit Form menghapus field yang dijawab draf berjalan dan menambah field wajib** → draf berjalan tetap bisa disubmit (BR-FE-11: `created_at` field baru = saat terbit > `submission.created_at`), jawaban field terhapus tetap terbaca (BR-FE-12), notifikasi BR-FE-13 hanya bila dicentang saat terbit. Test di Task 3 (`[FE-FORMPUB-03]`).

## Kesesuaian dengan BDD

| ID | Berkas | Kelas | Isi | Task |
| --- | --- | --- | --- | --- |
| `[FE-PHVER-01]` | `bdd/generic/form_engine/phase_version.feature` | `@happy` | Pengajuan pertama angkatan memaku versi struktur terbit terbaru | 1 |
| `[FE-PHVER-02]` | 〃 | `@boundary` | Sebelum pengajuan pertama, angkatan memakai versi terbaru — perbaikan fase terbawa | 1 |
| `[FE-PHVER-03]` | 〃 | `@boundary` | Versi baru tidak mengubah siklus monev angkatan yang sudah terpaku | 1 |
| `[FE-PHVER-04]` | 〃 | `@boundary` | Angkatan lain pada fase yang sama memakai versi terbaru | 1 |
| `[FE-PHVER-05]` | 〃 | `@denial` | Form pengajuan tahap yang sudah dipakai angkatan tidak bisa diganti | 1 |
| `[FE-PHPUB-01]` | `phase_version.feature` | `@denial` | Tahap yang belum pernah terbit tidak bisa ditautkan ke periode | 2 |
| `[FE-PHPUB-02]` | 〃 | `@boundary` | Draf tahap tidak berlaku sebelum terbit: pengajuan baru memakai versi terbit | 2 |
| `[FE-PHPUB-03]` | 〃 | `@happy` | Terbitkan membentuk versi berikutnya, menyebut angkatan yang tetap di versi lama, dan menghapus draf | 2 |
| `[FE-PHPUB-04]` | 〃 | `@denial` | Langkah yang memakai Form belum terbit menolak penerbitan | 2 |
| `[FE-FORMPUB-01]` | `bdd/generic/form_engine/form_publish.feature` | `@boundary` | Draf field tidak terlihat peneliti sebelum terbit | 3 |
| `[FE-FORMPUB-02]` | 〃 | `@happy` | Ringkasan dampak menyebut field wajib baru, draf berjalan, dan label yang diubah | 3 |
| `[FE-FORMPUB-03]` | 〃 | `@boundary` | Field wajib yang terbit berlaku sejak terbit, bukan sejak diketik | 3 |
| `[FE-FORMPUB-04]` | 〃 | `@happy` | Mengubah label field yang sudah dijawab memunculkan peringatan, bukan blokir | 3 |
| `[FE-CHANGE-02..04]` | `form_changes.feature` | — | BR-FE-13: kalimat "mengubah Form" → "menerbitkan Form"; test-nya pindah ke route terbit | 3 |

## Keputusan penulis rencana

- **G3-W1** — `form_phase_details.form_phase_id` **dipertahankan** (denormal) dan ditambah `form_phase_version_id`; FK komposit (`form_phase_version_id`, `form_phase_id`) → `form_phase_versions (id, form_phase_id)` menjaga keduanya selaras. Relasi `formPhase` yang dipakai di mana-mana tetap jalan; "sama tahap" di kueri siklus menjadi "sama versi".
- **G3-W2** — **Versi pengajuan yang ada = versi langkahnya**, bukan hasil baca pin. Pin hanya dibaca untuk pengajuan baru dan layar per angkatan, lewat satu pintu `PhaseVersionFor`. Fixture `scheduledSubmission` tidak perlu memaku.
- **G3-W3** — Form pengajuan sebuah tahap tidak bisa diganti antar versi begitu ada pin untuk tahap itu (`[FE-PHVER-05]`): BR-FE-29 (satu tahap per Form pengajuan per periode) dan pustaka bertapis Form tetap membaca versi terbaru tanpa kasus campuran.
- **G3-W4** — Task 1 = "setiap penulisan struktur menerbitkan versi": Simpan editor fase dan pasang/ubah/lepas Form Evaluasi masing-masing memanggil `PublishPhaseVersion` (komposit sementara, pola G2-W4). Task 2 menggantinya dengan draf dan menghapus route pemasangan langsung.
- **G3-W5** — Versi beku membuat kunci K-1 (jenis langkah terpakai), `AssertUsedPhaseDetailsKeepTheirForm`, dan `AssertEvaluatedDetailsStayReviewed` tak perlu: angkatan lama tidak pernah membaca baris baru. Dihapus beserta test-nya, diganti `[FE-PHVER-03]`; ADR 0019 mencatat penggantiannya. Lepas Form Evaluasi yang sudah ditugaskan (BR-FE-30) kini cukup tidak membawanya ke versi baru; penugasan lama tetap di versi lama.
- **G3-W6** — Hanya tahap yang sudah terbit bisa ditautkan ke periode (`[FE-PHPUB-01]`), dan langkah hanya memakai Form yang sudah terbit (`forms.published_at` tak null, `[FE-PHPUB-04]`). Padanan G2-W3: butir cek kesiapan §4.9 "draf yang belum pernah terbit" menjadi tak perlu di G4.
- **G3-W7** — `ConfigurationDraft` mendapat subkelas per jenis — `EvaluationFormDraft`, `PhaseDraft`, `FormDraft` — masing-masing memaku `draftable_type` (global scope + `creating`) dan bentuk `@property payload`-nya, supaya PHPStan tidak memerlukan `mixed` (QualityBudget membatasi `mixed` di 17).
- **G3-W8** — Pratinjau Fase = daftar langkah baca-saja (urutan, Form, nama tenggat `deadlineLabel()`, perlu review, Form Evaluasi + label versi) — yang dilihat Operator/peneliti sebagai alur. Pratinjau Form = `WizardFieldList` dengan nilai lokal, persis isian peneliti.
- **G3-W9** — Peringatan label §4.6 dihitung di FE dari `responseCount` per field yang sudah ada (BR-FE-23): muncul bila label field atau label opsi berbeda dari terbitan dan `responseCount > 0`. Tidak ada hitungan per opsi.

---

### Task 1: Versi struktur fase + pemakuan per angkatan (BE)

**Files:**

- Create: `database/migrations/2026_11_14_100000_version_form_phase_structure.php`
- Create: `app/Models/FormPhaseVersion.php`, `app/Models/PhaseVersionPin.php`, `database/factories/FormPhaseVersionFactory.php`
- Modify: `app/Models/FormPhase.php`, `FormPhaseDetail.php`, `StepEvaluationForm.php`, `database/factories/FormPhaseDetailFactory.php`
- Create: `app/Queries/Form/PhaseVersionFor.php`, `app/Actions/Form/PinPhaseVersion.php`, `app/Actions/Form/PublishPhaseVersion.php`, `app/Data/Request/PhaseStructureInput.php`, `app/Data/Request/StepEvaluationFormInput.php`
- Modify (komposit sementara, G3-W4): `app/Actions/Form/CreateFormPhase.php`, `ReviseFormPhase.php`, `app/Actions/Review/AttachEvaluationForm.php`, `UpdateStepEvaluationForm.php`, `DetachEvaluationForm.php`
- Modify (pembaca): `app/Actions/FormSubmission/StartSubmission.php`, `app/Http/Controllers/UserFormController.php`, `app/Queries/Submission/{AccessibleFormPhaseDetail,ResearcherStartOptions,AvailableChildPhases}.php`, `app/Queries/Monev/MonevCycles.php`, `app/Queries/ResearchOutput/OutputUploadDetail.php`, `app/Support/Reporting/Exports/MonevRecapExport.php`, `app/Queries/Period/{PeriodScheduleGaps,CohortEvaluationVersions}.php`, `app/Queries/Form/{PhaseEdit,PhaseLibrary,PhaseSubmissionForms,PhaseEvaluationForms,PhaseAccessOptions,LinkablePeriods}.php`, `app/Actions/Form/DeleteFormPhase.php`, `app/Models/SubmissionDate.php` (`stepsNeedingIt`, penjaga BR-FE-21), `database/seeders/EndToEndFlowSeeder.php`
- Delete: `app/Actions/Form/AssertUsedPhaseDetailsKeepTheirForm.php`, `AssertEvaluatedDetailsStayReviewed.php` (G3-W5) + test yang khusus membuktikan keduanya
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-32; BR-FE-20 menyebut "di versi mana pun"), `ddd/supporting/02_monev.md` (siklus = langkah sesudah langkah induk **di versi induk**), `ddd/02_ubiquitous_language.md` (**Versi Struktur Fase**, **Pemakuan struktur**)
- Create: `docs/requirement-analysis/bdd/generic/form_engine/phase_version.feature` (FE-PHVER-01..05)
- Modify: `tests/Support/fixtures.php` + berkas test yang membuat detail lewat `$phase->formPhaseDetails()->create(` (±49 berkas, ganti mekanis ke fixture `phaseStep`)
- Test: `tests/Feature/FormEngine/PhaseVersionPinTest.php` (baru)

**Interfaces:**

- Skema (skema saja, `down()` membalik):
    - `form_phase_versions`: `form_phase_id` (cascade), `number` unsignedSmallInteger, `published_by` FK users nullable `nullOnDelete`, `published_at`, timestamps; unik (`form_phase_id`, `number`); unik (`id`, `form_phase_id`) untuk FK komposit.
    - `form_phase_details`: + `form_phase_version_id` not null; FK komposit (`form_phase_version_id`, `form_phase_id`) → `form_phase_versions (id, form_phase_id)` cascade; indeks (`form_phase_version_id`, `order`).
    - `phase_version_pins`: `submission_period_id`, `form_phase_id`, `form_phase_version_id` (restrict), `pinned_at`, timestamps; unik `phase_version_pins_cohort_unique`.
- Model:
    - `FormPhase`: `versions(): HasMany` (urut `number`), `latestVersion(): HasOne` (`latestOfMany('number')`), `currentDetails(): HasMany` (detail versi terbaru, urut `order`); `formPhaseDetails()` tetap = semua versi (penjaga hapus BR-FE-20).
    - `FormPhaseVersion`: `formPhase()`, `formPhaseDetails()` (urut `order`), `label(): string`; `updating` → `LogicException`.
    - `FormPhaseDetail`: `formPhaseVersion(): BelongsTo`; `updating` → `LogicException`. `StepEvaluationForm`: `updating` → `LogicException`.
    - `PhaseVersionPin`: `submissionPeriod()`, `formPhase()`, `formPhaseVersion()`.
- Kueri & Action:
    - `PhaseVersionFor::run(SubmissionPeriod $period, FormPhase $phase): ?FormPhaseVersion` — versi pin, atau `latestVersion`; null hanya untuk tahap tanpa versi (baru mungkin sesudah Task 2).
    - `PinPhaseVersion::run(SubmissionPeriod $period, FormPhaseDetail $detail): FormPhaseVersion` — `createOrFirst` pin dengan versi `$detail`; bila pin yang ada menunjuk versi lain → `ValidationException` (`form_phase_detail_id`, pesan Global Constraints). Audit `"Struktur tahap dipaku"` hanya saat baris baru lahir. `// BR-FE-32`.
    - `StartSubmission`: sesudah semua penjaga, dalam satu transaksi `PinPhaseVersion` lalu `FormSubmission::create`. Tanda tangan `run(int $formId, int $submissionPeriodId, FormPhaseDetail $detail, User $user)` tetap.
    - `AccessibleFormPhaseDetail::run(int $formPhaseId, int $formId, int $submissionPeriodId, User $user): ?FormPhaseDetail` — mencari di `PhaseVersionFor` angkatan itu; `UserFormController` meneruskan `submission_period_id`. `ResearcherStartOptions` eager-load `submissionPeriodPhases.formPhase.latestVersion.formPhaseDetails` dan relasi baru `SubmissionPeriod::phaseVersionPins.formPhaseVersion.formPhaseDetails` (tapis aturan akses yang sama), lalu memilih detail versi pin bila ada — jumlah kueri tidak bertambah per tahap.
    - `MonevCycles::details` / `whereHasCycles`, `OutputUploadDetail`, `MonevRecapExport`: `form_phase_id` → `form_phase_version_id` versi langkah induk. `AvailableChildPhases`: hanya detail di versi langkah induk.
    - `PeriodScheduleGaps`, `CohortEvaluationVersions`: langkah dari `PhaseVersionFor` per angkatan. `SubmissionDate::stepsNeedingIt`: cabang "tahap ditautkan" = langkah versi pin periode itu, atau versi terbaru bila belum terpaku; cabang "dipakai pengajuan" = `used_steps.form_phase_version_id`. `PhaseEdit`, `PhaseLibrary`, `PhaseSubmissionForms`, `PhaseEvaluationForms`, `PhaseAccessOptions`, `LinkablePeriods`: `currentDetails`. `PhaseEdit` + `versionLabel: ?string`.
    - `PhaseStructureInput(list<FormPhaseDetailInput> $details)`; `FormPhaseDetailInput` + `list<StepEvaluationFormInput> $evaluationForms`; `StepEvaluationFormInput(int $reviewEvaluationFormId, bool $isRequired, bool $isActive)` (urutan = indeks). `PhaseStructureInput::fromVersion(FormPhaseVersion): self` (dipakai komposit & Task 2).
    - `PublishPhaseVersion::run(FormPhase $phase, PhaseStructureInput $structure, User $actor): FormPhaseVersion` — `AssertPhaseSteps`, G3-W3 (pesan `phase_details`), BR-FE-30 per pemasangan (terbit, sekali per langkah, langkah `needs_review` — pakai `AssertReviewedPhaseDetail` versi masukan); salin baris detail + pemasangan; `number` = maks + 1; audit `"Struktur tahap diterbitkan"` berisi nomor + jumlah langkah. `// BR-FE-32`.
    - Komposit: `CreateFormPhase` = buat tahap + `PublishPhaseVersion` (Versi 1). `ReviseFormPhase` = ubah judul/deskripsi/aktif + `PublishPhaseVersion` dengan Form Evaluasi tiap detail ber-`id` disalin dari detail itu (id milik tahap lain tetap ditolak). Attach/Update/Detach = `fromVersion(latest)` diubah satu pemasangan lalu `PublishPhaseVersion`; pesan dan kunci galatnya tetap. Ketiganya mengembalikan/menerima model seperti sekarang (controller tak berubah selain id langkah baru sesudah terbit).
    - Fixture: `phaseStep(FormPhase $phase, array $attributes): FormPhaseDetail` — menambah ke versi terbaru tahap, atau Versi 1 baru bila belum ada; tanpa audit. `FormPhaseDetailFactory` membuat Versi 1 untuk tahapnya. `phaseVersion(FormPhase $phase, list<array<string, mixed>> $steps): FormPhaseVersion` — versi baru berisi langkah itu (untuk test dua versi).

- [ ] **Step 1: Tulis skenario** FE-PHVER-01..05 dan **BR-FE-32** di `01_form_engine.md` (sesudah BR-FE-31): "Struktur tahap berversi. Setiap penerbitan membentuk versi beku berisi langkah, urutan, Form + aturan akses, jenis/siklus tenggat, `needs_review`, dan Form Evaluasi terpasang. Angkatan (Periode × Tahap) dipaku ke versi terbaru saat pengajuan pertamanya dibuat; sebelumnya ia memakai versi terbaru. Pengajuan, siklus monev, dan luaran membaca versi langkah pengajuannya. Tidak ada aksi menerapkan struktur baru ke angkatan berjalan; Form pengajuan tahap tidak bisa diganti begitu ada angkatan terpaku." Contoh:
    ```gherkin
    @BR-FE-32 @M2 @ADMIN-08-09 @boundary
    Scenario: [FE-PHVER-03] Versi baru tidak mengubah siklus monev angkatan yang sudah terpaku
      Given angkatan "Periode 2025" × "Hibah Internal" terpaku Versi 1 dengan Monev I dan Monev II
      And Admin menerbitkan Versi 2 yang menambah Monev III
      When peneliti "P-001" di "Periode 2025" membuka siklus monevnya
      Then ia melihat Monev I dan Monev II saja
    ```
- [ ] **Step 2: Tulis test** `PhaseVersionPinTest.php` — satu `it('[FE-PHVER-0n] …')` per skenario, ditambah Review Focus 1 dan 3, `it('refuses to update a published step')` (`toThrow(LogicException::class)`), `it('keeps a step evaluation form on the version that was assigned')` (lepas di Versi 2 tidak menyentuh penugasan Versi 1), `it('offers the pinned version step to a researcher in a pinned cohort')` (`ResearcherStartOptions` + `AccessibleFormPhaseDetail`).
- [ ] **Step 3: Jalankan** `./vendor/bin/pest tests/Feature/FormEngine/PhaseVersionPinTest.php` → FAIL (tabel/kelas belum ada).
- [ ] **Step 4: Implementasi** migrasi, model, kueri, Action, komposit sesuai Interfaces. Seeder memanggil `PinPhaseVersion` di tempat ia membuat pengajuan induk.
- [ ] **Step 5: Jalankan** test baru → PASS.
- [ ] **Step 6: Pindahkan fixture & test lama.** Ganti mekanis `$X->formPhaseDetails()->create(` → `phaseStep($X, ` di `tests/`; test yang meng-`update` detail (6 tempat, mis. `PhaseStepKindTest:104`, `PhasePeriodLinkTest:39,161`) membuat langkah/versi baru; test yang menegaskan detail revisi mempertahankan id-nya kini menegaskan langkah Versi 2 dan Versi 1 yang utuh. `grep -rn "formPhaseDetails()->create\|AssertUsedPhaseDetailsKeepTheirForm\|AssertEvaluatedDetailsStayReviewed" app database tests` → kosong. `grep -rn "'form_phase_id', \$own\|later.form_phase_id" app` → kosong.
- [ ] **Step 7: Gate G3-a** — `composer run quality` lalu `npm run quality` (berurutan, watchdog; FE hanya menerima `versionLabel`). Hijau.
- [ ] **Step 8: Commit** — `feat(form-engine): struktur tahap berversi, dipaku per angkatan`

### Task 2: Draf → Pratinjau → Terbitkan untuk Fase

**Files:**

- Create: `app/Models/PhaseDraft.php`, `app/Models/EvaluationFormDraft.php`, `app/Data/Request/PhaseDraftInput.php`
- Modify: `app/Models/ConfigurationDraft.php` (basis, G3-W7; konstanta `PHASE = 'form_phase'`), `ReviewEvaluationForm::draft()` → `EvaluationFormDraft`, Action draf G2 yang mencari draf lewat `draftable_type`
- Create: `app/Actions/Form/{SavePhaseDraft,PublishPhaseDraft,DiscardPhaseDraft}.php`, `app/Queries/Form/PhasePublishImpact.php`, `app/Http/Controllers/FormEngine/PhaseDraftController.php`, `app/Http/Requests/Form/PhaseDraftRequest.php`
- Modify: `app/Actions/Form/CreateFormPhase.php` (buat tahap + draf, tanpa versi), `ReviseFormPhase.php` → `UpdateFormPhase.php` (judul/deskripsi/aktif saja), `LinkPhaseToPeriod.php` + `CreateSubmissionPeriod`/`ReviseSubmissionPeriod` (G3-W6), `app/Http/Requests/Form/FormPhaseRequest.php`, `app/Http/Controllers/FormEngine/FormPhaseController.php`, `app/Queries/Form/{PhaseEdit,PhaseLibrary,LinkablePeriods}.php`, `routes/web.php`
- Delete: `app/Http/Controllers/FormEngine/StepEvaluationFormController.php` + FormRequest-nya, `app/Actions/Review/{AttachEvaluationForm,UpdateStepEvaluationForm,DetachEvaluationForm}.php` (aturannya pindah ke `PublishPhaseVersion`, Task 1), route `admin.phase-details.evaluation-forms.store`, `admin.step-evaluation-forms.*`
- Modify FE: `resources/js/Components/FormEngine/{PhaseEditor,PhaseDetailList,PhaseDetailDialog,EvaluationSection,StepEvaluationFormRow,PhaseCard}.vue`, `resources/js/Pages/FormEngine/{PhaseEditPage,PhaseIndexPage}.vue`, `resources/js/lib/phaseDefinition.ts`, `resources/js/types/form-engine.ts`; Create `resources/js/Components/FormEngine/PhaseStepPreview.vue`
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-33; BR-FE-30 "dipasang lewat draf tahap"), `ddd/02_ubiquitous_language.md` (**Draf**/**Terbitkan** berlaku untuk Fase), `docs/requirement-analysis/ux/ia/08_administration/form_builder.md` (#5 Edit Phase: tab Langkah / Pratinjau / Versi), `phase_version.feature` (FE-PHPUB-01..04)
- Test: `tests/Feature/FormEngine/PhaseDraftTest.php` (baru), `PhaseEditTest.php`, `PhaseLibraryTest.php`, `PhaseEvaluationTest.php`, `FormPhaseRevisionTest.php`, `PhasePeriodLinkTest.php`, `EvaluationLibraryTest.php` (FE-EVLIB-02/05 lewat draf tahap); `tests/frontend/pages/PhaseEditPage.spec.ts`, `PhaseIndexPage.spec.ts`, `tests/frontend/components/FormEngine/EvaluationSection.spec.ts`, `PhaseStepPreview.spec.ts` (baru)

**Interfaces:**

- Consumes: Task 1 (`PhaseStructureInput`, `PublishPhaseVersion`, `PhaseVersionFor`, `FormPhase::latestVersion`).
- Produces:
    - `FormPhaseInput` kehilangan `details` (judul, deskripsi, aktif saja); langkah hanya lewat `PhaseDraftInput`.
    - `ConfigurationDraft` = basis; subkelas memaku jenis. `FormPhase::draft(): HasOne<PhaseDraft>`. `PhaseDraftInput` = payload `{steps: list<{form_access_control_id, phase_type_id, needs_review, deadline_type, deadline_cycle, evaluation_forms: list<{review_evaluation_form_id, is_required, is_active}>}>}` dengan `toStructure(): PhaseStructureInput`, `fromStructure()`, `toPayload()`/`fromPayload()` (pola `EvaluationDraftInput`, tanpa id).
    - `SavePhaseDraft::run(FormPhase, PhaseDraftInput, User): PhaseDraft` (upsert; validasi bentuk saja — `AssertPhaseSteps` dijalankan juga supaya galat terlihat saat simpan); `PublishPhaseDraft::run(FormPhase, User): FormPhaseVersion` (kunci draf, `PublishPhaseVersion`, hapus draf, satu transaksi; `// BR-FE-33`); `DiscardPhaseDraft::run(FormPhase, User): void`. `CreateFormPhase::run(FormPhaseInput, PhaseDraftInput, User): FormPhase`.
    - `PhasePublishImpact::run(FormPhase): array{nextLabel: string, pinnedCohorts: int}` — angkatan yang punya pin ke versi < berikutnya.
    - Route: `admin.phases.draft.update` PUT `/admin/phases/{formPhase}/draft` `"Draf disimpan."`; `.draft.destroy` DELETE `"Draf dibuang."`; `admin.phases.publish` POST `/admin/phases/{formPhase}/publish` `"{Versi n} diterbitkan."`; `admin.phases.update` hanya judul/deskripsi/aktif `"Tahap diperbarui."`; `admin.phases.store` → `.edit` `"Tahap dibuat. Susun langkahnya lalu terbitkan."`.
    - `PhaseEditData` + `hasDraft: bool`, `versionLabel: ?string`, `steps` (draf ?? versi terbaru), `versions: list<{label, publishedAt, publishedBy, cohortCount}>`, `impact: {nextLabel, pinnedCohorts}`; tiap langkah membawa `evaluationForms: list<{formId, title, versionLabel, isRequired, isActive}>`. `PhaseLibrary` kartu + `hasDraft`, `versionLabel`.
    - FE: editor bertab **Langkah** (daftar langkah + pemasangan Form Evaluasi sebagai state draf; Simpan draf), **Pratinjau** (`PhaseStepPreview`, G3-W8), **Versi & pemakaian**; tombol Terbitkan dengan konfirmasi dampak (Global Constraints) dan Buang draf; banner draf di editor dan lencana "Belum terbit"/banner di `PhaseCard`.

- [ ] **Step 1: Tulis skenario** FE-PHPUB-01..04 + **BR-FE-33** ("Struktur tahap disunting sebagai draf. Draf tidak berlaku bagi angkatan mana pun; Terbitkan memvalidasi ulang isi draf, membentuk versi bernomor berikutnya (BR-FE-32), lalu menghapus drafnya. Tahap yang belum pernah terbit tidak bisa ditautkan ke periode, dan langkah hanya memakai Form yang sudah pernah terbit. Judul, deskripsi, dan status aktif tahap bukan isi berversi, jadi disunting langsung.").
- [ ] **Step 2: Tulis test BE** `PhaseDraftTest.php` — FE-PHPUB-01..04, Review Focus 4, ditambah `it('creates a phase with a draft and no version')`, `it('discards a draft without leaving a version')`, `it('counts cohorts that keep an older version in the publish impact')`, `it('hides the draft routes from users without schemes.manage')` (403). Pindahkan test pemasangan (`PhaseEvaluationTest`, FE-EVLIB-02/05) ke draf + terbit; hapus test route pemasangan langsung.
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi BE.** `grep -rn "StepEvaluationFormController\|AttachEvaluationForm\|DetachEvaluationForm\|UpdateStepEvaluationForm\|step-evaluation-forms\|ReviseFormPhase" app routes tests resources/js` → kosong.
- [ ] **Step 5: Pest berkas di atas + `tests/Feature/FormEngine`** → PASS.
- [ ] **Step 6: Tulis test Vitest** — editor: Simpan draf → `route('admin.phases.draft.update', id)` dengan payload langkah + pemasangan; konfirmasi terbit berteks persis (m = 0 tanpa kalimat kedua, m = 2 dengan); banner draf dua varian; Pratinjau merender langkah draf read-only dengan `deadlineLabel`; pustaka: lencana "Belum terbit"; `EvaluationSection`: pilihan hanya Form terbit yang belum terpasang di langkah itu, lepas/ubah wajib mengubah state draf tanpa request.
- [ ] **Step 7: Implementasi FE** (sistem desain FE v2); Vitest berkas di atas → PASS.
- [ ] **Step 8: Gate G3-b** — `composer run quality` lalu `npm run quality`. Hijau.
- [ ] **Step 9: Commit** — `feat(form-engine): draf, pratinjau, dan terbit untuk tahap`

### Task 3: Draf → Pratinjau → Terbitkan untuk Form (field)

**Files:**

- Create: `database/migrations/2026_11_14_200000_add_published_at_to_forms.php`, `app/Models/FormDraft.php`, `app/Data/Request/FormDraftInput.php`
- Create: `app/Actions/Form/{SaveFormDraft,PublishFormDraft,DiscardFormDraft}.php`, `app/Queries/Form/FormPublishImpact.php`, `app/Http/Controllers/FormEngine/FormDraftController.php`, `app/Http/Requests/Form/FormDraftRequest.php`
- Modify: `app/Actions/Form/CreateForm.php` (buat Form + draf field, `published_at` null), `ReviseForm.php` (tanpa field), `NotifyFormChange.php` (dipanggil dari terbit), `app/Http/Requests/Form/FormDefinitionRequest.php`, `app/Http/Controllers/FormEngine/FormController.php`, `app/Queries/Form/{FormGeneral,FormDirectory}.php`, `app/Actions/Form/PublishPhaseVersion.php` (G3-W6 Form terbit), `routes/web.php`
- Modify FE: `resources/js/Components/FormEngine/{FormGeneralTab,FormFieldList,FormFieldDialog,AffectedDraftsNotice,FormTable}.vue`, `resources/js/lib/formDefinition.ts`, `resources/js/types/form-engine.ts`; Create `resources/js/Components/FormEngine/FormPreview.vue` (membungkus `WizardFieldList`)
- Modify: `ddd/generic/01_form_engine.md` (BR-FE-34; BR-FE-13 "saat Form **diterbitkan**"), `form_changes.feature` (FE-CHANGE-02..04 kalimatnya), Create `docs/requirement-analysis/bdd/generic/form_engine/form_publish.feature` (FE-FORMPUB-01..04), `ux/ia/08_administration/form_builder.md` (#2 Edit Form: Pratinjau + Terbitkan)
- Test: `tests/Feature/FormEngine/FormPublishTest.php` (baru), test BR-FE-13/11/18/23 yang memakai `admin.forms.update` dengan `fields` (pindah ke draf + terbit); `tests/frontend/components/FormEngine/FormGeneralTab.spec.ts`, `FormPreview.spec.ts` (baru), `tests/frontend/pages/FormIndexPage.spec.ts`

**Interfaces:**

- Consumes: Task 2 (`ConfigurationDraft` basis + subkelas, pola controller draf), `FormFieldWriter::sync`, `AffectedDraftOwners::countForForm`.
- Produces:
    - `forms.published_at` timestamp nullable. `Form::draft(): HasOne<FormDraft>`. `FormDraftInput(list<FormFieldInput> $fields)` dengan `toPayload()`/`fromPayload()`; payload **menyimpan id field/opsi terbit** (beda dengan Form Evaluasi: Form tidak berversi, jadi `sync` memperbarui baris yang sama — BR-FE-23 dan jawaban lama tetap menempel).
    - `SaveFormDraft::run(Form, FormDraftInput, User): FormDraft`; `PublishFormDraft::run(Form, User): void` — kunci draf, `SchemeFieldGuard`, `FormFieldWriter::sync` (penjaga BR-FE-23/24 berjalan saat terbit, id asing ditolak seperti sekarang), `published_at ??= now()`, hapus draf, audit `"Formulir diterbitkan"`; `// BR-FE-34`. `DiscardFormDraft::run(Form, User): void`.
    - `FormPublishImpact::run(Form): array{newRequired: int, runningDrafts: int, relabelled: list<array{label: string, answered: int}>, removed: int}` — draf vs field terbit.
    - Route: `admin.forms.draft.update` PUT `/admin/forms/{form}/draft` `"Draf disimpan."`; `.draft.destroy` DELETE `"Draf dibuang."`; `admin.forms.publish` POST `/admin/forms/{form}/publish` (`notify_affected` bool) → pesan terbit yang sekarang dipakai `update` (dengan/ tanpa jumlah notifikasi). `admin.forms.update` hanya General; `admin.forms.store` → `.edit` `"Form dibuat. Susun field-nya lalu terbitkan."`.
    - `FormGeneralData` + `hasDraft`, `publishedAt: ?string`, `publishedFields` (untuk G3-W9), `impact`. FE: kartu Field menyimpan ke draf; tab/area **Pratinjau** (`FormPreview`); konfirmasi Terbitkan menampilkan kalimat dampak (Global Constraints) + `AffectedDraftsNotice` (centang notifikasi) di dialog terbit, bukan di Simpan; peringatan label G3-W9 di `FormFieldDialog`; banner draf di editor; lencana "Belum terbit" di `FormTable`.

- [ ] **Step 1: Tulis skenario** FE-FORMPUB-01..04, ubah FE-CHANGE-02..04 ke momen terbit, dan **BR-FE-34** ("Field Form disunting sebagai draf. Draf tidak terlihat pengisi; Terbitkan menulis field ke baris hidup saat itu juga, sehingga `created_at`, `required_since`, dan soft delete yang dibaca BR-FE-11/12/18 adalah waktu terbit. Terbitkan menampilkan ringkasan dampak; prompt BR-FE-13 terjadi di sini. Mengubah label field atau opsi yang sudah dijawab diperingatkan, tidak diblokir. Form yang belum pernah terbit tidak bisa dipakai langkah tahap.").
- [ ] **Step 2: Tulis test BE** `FormPublishTest.php` — FE-FORMPUB-01..03, Review Focus 5, `it('summarises new required fields, running drafts, relabelled answered fields and removals')`, `it('still refuses a type change of an answered field at publish')` (BR-FE-23), `it('creates a form with a draft and no published fields')`. Pindahkan test lama yang menyunting field lewat `update` ke draf + terbit.
- [ ] **Step 3: Jalankan** → FAIL.
- [ ] **Step 4: Implementasi BE**; `grep -rn "'fields' =>" tests/Feature | grep "admin.forms.update"` → kosong.
- [ ] **Step 5: Pest berkas di atas + `tests/Feature/FormEngine`** → PASS.
- [ ] **Step 6: Tulis test Vitest** — Simpan draf → `admin.forms.draft.update`; dialog terbit: kalimat dampak persis per butir, tanpa butir → `"Terbitkan perubahan Form ini?"`, centang notifikasi hanya bila `runningDrafts > 0`; `FormPreview` merender field draf lewat `WizardFieldList`; `[FE-FORMPUB-04]` peringatan muncul saat label field `responseCount > 0` diubah, hilang saat dikembalikan, tidak muncul untuk field tanpa jawaban; banner dua varian.
- [ ] **Step 7: Implementasi FE**; Vitest berkas di atas → PASS.
- [ ] **Step 8: Commit** — `feat(form-engine): draf, pratinjau, dan terbit untuk field Form`

### Task 4: Penutup G3 — seeder, review, gate, dokumen

**Files:**

- Modify: `database/seeders/EndToEndFlowSeeder.php` (tahap lewat `CreateFormPhase` + `PublishPhaseDraft`, Form `published_at` terisi, pin angkatan dari pengajuan induk), `docs/adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md` (R5/R6 "Selesai — {commit}"; kunci K-1 diganti versi beku, G3-W1/W2/W5/W7), `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G3 selesai; rencana rinci += G3), `docs/plans/2026-11-11-fase-reuse-catatan-bug.md`

- [ ] **Step 1: Seeder** — `php artisan migrate:fresh --seed --force && php artisan db:seed --class=EndToEndFlowSeeder --force` di container. Bila klasifikasi auto-mode menolak, minta pemilik menjalankannya (`! <perintah>`) — jangan dipaksa.
- [ ] **Step 2: Review akhir gelombang** (satu review menyeluruh G3). Perbaiki Critical/Important + yang murah; sisanya ke catatan bug.
- [ ] **Step 3: Gate penuh** — `free -m`, lalu `composer run quality` dan `npm run quality`, berurutan di bawah watchdog. Laporkan angka test apa adanya.
- [ ] **Step 4: Dokumen status** (berkas di atas).
- [ ] **Step 5: Commit** — `docs: status G3 struktur fase berversi dan terbit`
