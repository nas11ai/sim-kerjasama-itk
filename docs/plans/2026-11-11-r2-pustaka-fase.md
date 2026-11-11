# R2 — Pustaka Fase & Satu Form Pengajuan per Fase — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fase menjadi objek pustaka mandiri di `/admin/phases` (menu **Fase**), tidak lagi bersarang di bawah satu Form, dan sistem menolak fase yang memuat lebih dari satu Form pengajuan atau yang langkah pertamanya bukan Pengajuan.

**Architecture:** Aturan baru BR-FE-28 ditegakkan di `AssertPhaseSteps` (dipanggil `CreateFormPhase` dan `ReviseFormPhase`), bukan CHECK basis data, karena Form langkah dibaca lewat aturan aksesnya (lintas tabel). Layar dipindah per layar, BE+FE dalam satu task, supaya aplikasi tetap jalan di antara session (`inertia.testing.ensure_pages_exist = true` membuat task BE-saja tidak bisa merender halaman yang belum ada): Task 2 memindah editor, Task 3 memindah daftar dan menghapus seluruh route `admin.forms.phases.*`. Tanpa migrasi (pemilik 2026-11-10: belum produksi); tidak ada fase lama yang ditandai (§5 spec gugur).

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL, lorisleiva/laravel-actions, spatie/laravel-data, Inertia + Vue 3 `<script setup>` + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** `docs/design/2026-11-10-fase-reuse-dan-versi-form-design.md` §4.1 (menu Fase saja — menu Form Evaluasi milik R3), §4.2, §7 butir 1–2, §9 P2. Peta irisan: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G1 = R1 + R2). DDD `ddd/generic/01_form_engine.md`, `ddd/02_ubiquitous_language.md`. ADR 0019. BDD `bdd/generic/form_engine/`.

**Basis:** `feat/init-be-development` @ `d93396f7` + perbaikan B1/B3/B4 `c993c46e`.

**Ukuran:** dua session. **R2-a = Task 1–2** (aturan + editor), **R2-b = Task 3–4** (daftar + penutup). Kedua gate hijau di akhir tiap session (Task 2 dan Task 3 menyentuh FE).

## Global Constraints

- **BR-FE-28** (baru): langkah Pengajuan = `FormPhaseDetail::opensSubmission()` (`submit` tanpa siklus). Satu fase punya **tepat satu Form pengajuan**; yang dihitung Form (`form_access_controls.form_id`), bukan baris — satu Form lewat beberapa aturan akses (mis. per fakultas) tetap satu.
- Langkah pertama (baris pertama kiriman layar) adalah langkah Pengajuan. Urutan diturunkan dari kemunculan pertama Form-nya (`NormalisePhaseDetailOrder`), jadi Form pengajuan selalu `order = 1`.
- Langkah Laporan Monev / Upload Luaran tidak memakai Form pengajuan fase itu (ia akan mendapat `order` yang sama dan hilang dari `MonevCycles::details()`).
- Teks galat persis (kunci galat dalam kurung):
    - (`phase_details`) `"Tahap butuh satu langkah Pengajuan."`
    - (`phase_details`) `"Satu tahap hanya untuk satu Form pengajuan: {judul A}, {judul B}. Buat tahap terpisah untuk tiap Form."` — judul urut kemunculan.
    - (`phase_details.0.deadline_type`) `"Langkah pertama tahap harus langkah Pengajuan."`
    - (`phase_details.{i}.form_access_control_id`) `"Laporan Monev dan Upload Luaran memakai Form selain Form pengajuan."`
- Teks UI persis: menu `"Fase"`; judul daftar `"Fase"`, subjudul `"Pustaka tahap yang bisa dipakai banyak periode."`; filter `"Tahap yang memakai {judul Form}"` + tautan `"Tampilkan semua"`; kartu `"Form pengajuan: {judul}"`; editor tanpa pilihan akses: `"Belum ada Form aktif yang punya aturan akses. Buat aturan akses di Form Builder lebih dulu."` + tautan `"Buka Form Builder"`.
- Route baru dalam grup `['auth', 'can:schemes.manage']`, prefix `admin`, nama `admin.`; tautan periode tetap `can:periods.manage`. Route lama `admin.forms.phases.*` **dihapus, tidak dialihkan** (R2-W4).
- Aturan berlaku untuk penyimpanan baru maupun revisi; tidak ada pengecualian fase lama.
- Gate via container (AGENTS.md); `free -m` ≥ 3 GB atau watchdog, satu langkah berat sekaligus.

## Review Focus

1. **Satu Form pengajuan lewat dua aturan akses** (Fakultas A, Fakultas B) di dua baris pertama → diterima, keduanya `order = 1`, dan siklus monev induk Fakultas A tidak memuat baris Fakultas B. Test di Task 1 (`[FE-PHASE-01]`).
2. **Revisi fase yang sudah dipakai pengajuan** menambah Form pengajuan kedua → ditolak, tahap dan audit tidak berubah. Test di Task 1 (`[FE-PHASE-05]`).
3. **Admin tanpa `periods.manage`** membuka pustaka → kartu tanpa aksi hubungkan periode, dan POST tautan periode 403. Test di Task 3.
4. **Filter `?form=` untuk Form yang hanya dipakai langkah monev** → fase yang memakainya tetap tampil (filter = langkah mana pun memakai Form itu, bukan hanya Pengajuan); `?form=` id tak dikenal → 404. Test di Task 3.
5. **Editor mode buat tanpa satu pun Form aktif beraturan akses** → banner + Simpan nonaktif, bukan 500 atau detail berakses 0. Test di Task 2 (Pest render + Vitest).

## Kesesuaian dengan BDD

Berkas baru `docs/requirement-analysis/bdd/generic/form_engine/phase_library.feature`, tag `@BR-FE-28 @M2 @ADMIN-08-09`. `pending.txt` dan `uncovered.txt` tetap `max: 0` — BR-FE-28, skenarionya, dan test-nya mendarat di commit yang sama (BddCoverageTest assertion 3, 4, 7).

| ID              | Kelas       | Isi                                                                                   | Task |
| --------------- | ----------- | ------------------------------------------------------------------------------------- | ---- |
| `[FE-PHASE-01]` | `@boundary` | Satu Form pengajuan lewat dua aturan akses dihitung satu dan berbagi posisi Pengajuan | 1    |
| `[FE-PHASE-02]` | `@denial`   | Tahap dengan dua Form pengajuan ditolak dan menyebut kedua judulnya                   | 1    |
| `[FE-PHASE-03]` | `@denial`   | Tahap yang langkah pertamanya Laporan Monev ditolak                                   | 1    |
| `[FE-PHASE-04]` | `@denial`   | Laporan Monev yang memakai Form pengajuan tahap itu ditolak                           | 1    |
| `[FE-PHASE-05]` | `@denial`   | Revisi tahap yang sudah dipakai pengajuan tidak bisa menambah Form pengajuan kedua    | 1    |
| `[FE-PHASE-06]` | `@denial`   | Tahap tanpa langkah Pengajuan ditolak                                                 | 1    |

Pustaka/route adalah layar (BR-FE-07 non-behavioral, `bdd/README.md` baris ADMIN-08-08) — tanpa skenario baru.

## Keputusan penulis rencana

- **R2-W1** — Aturan di Action, bukan CHECK: Form langkah dibaca lewat `form_access_controls`, dan pesan galat harus menyebut judul Form.
- **R2-W2** — Langkah pertama yang bukan Pengajuan **ditolak**, tidak diurut ulang diam-diam: mengurut ulang mengubah langkah mana yang menjadi siklus monev tanpa ada yang memutuskannya (anti-tujuan spec §2).
- **R2-W3** — P2 sudah ditutup (`FormPhaseDetail::opensSubmission()`, `tests/Feature/Monev/MonevCycleNotStartableTest.php`); R2 tidak menambah apa pun untuknya.
- **R2-W4** — Route lama dihapus tanpa pengalihan: aplikasi belum produksi, jadi tidak ada tautan luar/bookmark yang perlu dijaga; tautan internal diganti di task yang sama. Tab "Phases" di Form Builder menjadi tautan ke pustaka bertapis `?form={id}`.
- **R2-W5** — Pustaka diurut `title`, lalu `id` (daftar banyak fase dicari menurut nama; dulu per Form, urut `id`).
- **R2-W6** — `MonevCycles` tidak diubah. Kasus W92 (Form lain berbagi tahap) tidak bisa lagi lahir lewat Action; test lamanya di `MonevCyclesTest.php` tetap karena memakai insert langsung dan masih benar.

---

### Task 1: BR-FE-28 — satu Form pengajuan per fase (DDD + BDD + aturan)

**Files:**

- Modify: `docs/requirement-analysis/ddd/generic/01_form_engine.md` — tambah BR-FE-28 sesudah BR-FE-27 (isi = tiga baris pertama Global Constraints); `docs/requirement-analysis/ddd/02_ubiquitous_language.md` — baris **FormPhase** menjadi `**FormPhase** (**Fase**)`, tambah "Template tanpa tanggal untuk tepat satu Form pengajuan (BR-FE-28); dipakai banyak periode."
- Create: `docs/requirement-analysis/bdd/generic/form_engine/phase_library.feature` (FE-PHASE-01..06)
- Modify: `app/Actions/Form/AssertPhaseSteps.php`
- Test: `tests/Feature/FormEngine/PhaseSubmissionFormTest.php` (baru)

**Interfaces:**

- Consumes: `CreateFormPhase::run(FormPhaseInput, User)`, `ReviseFormPhase::run(FormPhase, FormPhaseInput, User)`, `FormPhaseInput(title, description, isActive, details)`, `FormPhaseDetailInput(id, formAccessControlId, phaseTypeId, needsReview, deadlineType, deadlineCycle)`.
- Produces: `AssertPhaseSteps::run(list<FormPhaseDetailInput> $details): void` — tanda tangan tetap; kini juga menegakkan BR-FE-28 dengan satu kueri `FormAccessControl` (id → form_id) + judul Form. `FormPhaseDetailInput::opensSubmission(): bool` (padanan `FormPhaseDetail::opensSubmission()` untuk masukan).

- [ ] **Step 1: Tulis skenario** `phase_library.feature` (kalimat Indonesia, pola `phase_window.feature`). Contoh FE-PHASE-02:
    ```gherkin
    @BR-FE-28 @M2 @ADMIN-08-09 @denial
    Scenario: [FE-PHASE-02] Tahap dengan dua Form pengajuan ditolak
      Given Form "Pengajuan Penelitian" dan Form "Pengajuan Pengmas"
      When Admin menyimpan tahap yang dua langkah Pengajuan-nya memakai kedua Form itu
      Then penyimpanan ditolak dengan pesan yang menyebut "Pengajuan Penelitian" dan "Pengajuan Pengmas"
      And tidak ada tahap yang dibuat
    ```
- [ ] **Step 2: Tulis BR-FE-28 dan istilah** di dua berkas DDD di atas (disetujui sekaligus, roadmap).
- [ ] **Step 3: Tulis test** `PhaseSubmissionFormTest.php`. Fixture lokal: `phaseStep(FormAccessControl $access, DeadlineType $type = DeadlineType::Submit, ?int $cycle = null, ?int $id = null): FormPhaseDetailInput` dan akses per Form lewat `FormAccessControl::create` (pola `scheduleStep` di `tests/Support/fixtures.php`). Test:
    - `[FE-PHASE-01] counts one submission form reached through two access rules` — Form P lewat akses Fakultas A dan B + Form M siklus 1 → `CreateFormPhase` sukses; `order` = `[1, 1, 2]`; `MonevCycles::details()` induk di langkah Fakultas A = hanya langkah Form M (baris Fakultas B tidak ikut).
    - `[FE-PHASE-02] rejects a phase with two submission forms` — `ValidationException`, `errors()['phase_details'][0]` === `'Satu tahap hanya untuk satu Form pengajuan: Pengajuan Penelitian, Pengajuan Pengmas. Buat tahap terpisah untuk tiap Form.'`; `FormPhase::count()` 0.
    - `[FE-PHASE-03] rejects a phase whose first step is a monev report` — baris 0 Form M siklus 1, baris 1 Form P → `errors()['phase_details.0.deadline_type'][0]` === `'Langkah pertama tahap harus langkah Pengajuan.'`.
    - `[FE-PHASE-04] rejects a monev report that reuses the submission form` — Form P Pengajuan + Form P siklus 1 → kunci `phase_details.1.form_access_control_id`, pesan persis Global Constraints. Dataset: siklus 1 dan `output`.
    - `[FE-PHASE-05] keeps a used phase from gaining a second submission form` — fase sah + satu `scheduledSubmission` di langkah Pengajuan; `ReviseFormPhase` menambah baris Pengajuan Form Q → ditolak; jumlah langkah dan `AuditEntry` beraksi "Tahap formulir direvisi" tidak bertambah.
    - `[FE-PHASE-06] rejects a phase with no submission step` — satu baris Form M siklus 1 → `errors()['phase_details'][0]` === `'Tahap butuh satu langkah Pengajuan.'` (bersama galat FE-PHASE-03; keduanya dilaporkan).
- [ ] **Step 4: Jalankan** `./vendor/bin/pest tests/Feature/FormEngine/PhaseSubmissionFormTest.php` → FAIL (FE-PHASE-01 lulus sejak awal; sisanya tidak dilempar).
- [ ] **Step 5: Implementasi** di `AssertPhaseSteps::handle` — kumpulkan semua galat lalu lempar sekali (pola yang ada). Komentar `// BR-FE-28 — …`. Pecah per aturan menjadi method privat (Extract Method) supaya `handle` tetap di bawah ambang `quality-budget.txt`.
- [ ] **Step 6: Jalankan seluruh Pest.** Test HTTP/Action yang membuat tahap berisi dua Form pengajuan atau Form pengajuan di belakang (W92, terutama `PhaseEditTest`, `FormPhaseTabTest`, `FormPhaseLifecycleTest`, `PhaseStepKindTest`) gagal: perbaiki fixture-nya menjadi tahap sah, atau hapus test yang khusus membuktikan perilaku W92 lewat route. Insert langsung (`monevDetail`, `scheduleStep`) tidak terkena.
- [ ] **Step 7: Commit** — `feat(form-engine): satu tahap hanya untuk satu Form pengajuan`

### Task 2: Editor fase mandiri — `/admin/phases/create`, `/admin/phases/{formPhase}/edit`

**Files:**

- Modify: `routes/web.php` — tambah 4 route, hapus `admin.forms.phases.create|store|edit|update`
- Modify: `app/Http/Controllers/FormEngine/FormPhaseController.php` (`create`, `store`, `edit`, `update`, `editor` tanpa `Form`)
- Modify: `app/Queries/Form/PhaseEdit.php`, `app/Queries/Form/PhaseAccessOptions.php`
- Modify: `resources/js/Pages/FormEngine/PhaseEditPage.vue`, `resources/js/Components/FormEngine/PhaseEditor.vue`, `PhaseDetailList.vue`, `PhaseCard.vue` (tautan Sunting), `resources/js/Pages/FormEngine/FormEditPage.vue` (tombol "Buat Tahap Baru"), `resources/js/types/form-engine.ts` (`PhaseEditPageProps` tanpa `form`)
- Test: `tests/Feature/FormEngine/PhaseEditTest.php`, `FormPhaseRevisionTest.php`, `PhaseStepKindTest.php`, `PhaseEvaluationTest.php`, `FormPhaseLifecycleTest.php` (bagian buat/sunting); `tests/frontend/pages/PhaseEditPage.spec.ts`, `tests/frontend/components/FormEngine/PhaseCard.spec.ts`

**Interfaces:**

- Consumes: Task 1.
- Produces:
    - Route `admin.phases.create` GET `/admin/phases/create`, `admin.phases.store` POST `/admin/phases`, `admin.phases.edit` GET `/admin/phases/{formPhase}/edit`, `admin.phases.update` PUT `/admin/phases/{formPhase}`.
    - `store` → `admin.phases.edit` + `"Tahap dibuat. Tambahkan form evaluasi untuk detail yang direview."`; `update` → `admin.phases.edit` + `"Tahap diperbarui."` (Task 3 mengganti tujuan `update` ke `admin.phases.index`).
    - `PhaseEdit::run(?FormPhase $phase): PhaseEditData`; `PhaseAccessOptions::run(?FormPhase $phase): list<Option>` — aturan akses semua Form aktif + Form yang sudah dipakai fase ini, urut judul Form (tanpa "Form yang sedang dibuka").
    - Props `FormEngine/PhaseEditPage`: `{ phase, accessOptions, phaseTypeOptions, evaluation, fieldTypeOptions }`.
    - `PhaseEditor` dan `PhaseDetailList` tanpa `form`/`formId`; akses bawaan detail baru = `accessOptions[0]`. Mode buat: satu detail Pengajuan kosong pada `accessOptions[0]`; tanpa pilihan → daftar kosong + banner Global Constraints, Simpan nonaktif.
    - `PhaseCard` tautan Sunting → `route('admin.phases.edit', phase.id)`, prop `formId` dihapus (`FormPhaseTab` berhenti mengirimnya); `FormEditPage` "Buat Tahap Baru" → `route('admin.phases.create')`. Aksi aktif/hapus/periode di tab Phases tetap memakai route lama sampai Task 3.
    - Kembali di `PhaseEditPage` → `admin.forms.index` "Form Builder" (Task 3 mengganti ke pustaka).

- [ ] **Step 1: Pindahkan test BE** ke route baru (nama + URL tanpa `{form}`). Hapus test 404 "tahap tidak memakai Form di URL" (W92) — maknanya hilang. Tambah: `it('renders the create editor with no access options')` — tanpa Form aktif beraturan akses → 200, `accessOptions` `[]`; `it('offers access rules of every active form, ordered by form title')`.
- [ ] **Step 2: Jalankan** test berkas di atas → FAIL (route tidak ada).
- [ ] **Step 3: Implementasi BE** (route, controller, dua Query). Docblock W92 di `PhaseEdit`/controller diganti rujukan BR-FE-28.
- [ ] **Step 4: Pest berkas di atas** → PASS. `grep -rn "forms.phases.\(create\|store\|edit\|update\)" app routes resources tests` → kosong.
- [ ] **Step 5: Tulis test Vitest** — `PhaseEditPage.spec.ts`: tanpa `form` prop; Simpan memanggil `route('admin.phases.store')` / `route('admin.phases.update', id)`; `accessOptions: []` → banner berteks persis + tombol Simpan `disabled`. `PhaseCard.spec.ts`: tautan Sunting `admin.phases.edit`.
- [ ] **Step 6: Implementasi FE** sesuai Interfaces; jalankan `npx vitest run tests/frontend/pages/PhaseEditPage.spec.ts tests/frontend/components/FormEngine/PhaseCard.spec.ts` → PASS.
- [ ] **Step 7: Gate R2-a** — `composer run quality` lalu `npm run quality` (berurutan, watchdog). Hijau.
- [ ] **Step 8: Commit** — `feat(form-engine): editor tahap mandiri di /admin/phases`

### Task 3: Pustaka fase — `/admin/phases`, menu Fase, contract route lama

**Files:**

- Modify: `routes/web.php` — tambah `admin.phases.index|active|destroy|periods.store`, hapus sisa `admin.forms.phases.*`
- Modify: `app/Http/Controllers/FormEngine/FormPhaseController.php` (`index`, `setActive`, `destroy`, `linkPeriod` tanpa `Form`; `update` → pustaka)
- Rename: `app/Queries/Form/FormPhaseCards.php` → `app/Queries/Form/PhaseLibrary.php`
- Modify: `app/Data/View/FormEngine/PhaseCardData.php` (+ `submissionFormTitle`), `app/Models/FormPhase.php` (hapus `usesForm()` bila tak terpakai; `scopeUsingForm` tetap)
- Create: `resources/js/Pages/FormEngine/PhaseIndexPage.vue` (isi dari `FormPhaseTab.vue`)
- Delete: `resources/js/Components/FormEngine/FormPhaseTab.vue`
- Modify: `PhaseCard.vue`, `PhasePeriodDialog.vue`, `composables/usePhaseActions.ts`, `FormEditTabs.vue`, `Pages/FormEngine/FormEditPage.vue`, `Pages/FormEngine/PhaseEditPage.vue`, `lib/navigation.ts`, `types/form-engine.ts`
- Test: `tests/Feature/FormEngine/FormPhaseTabTest.php` → `PhaseLibraryTest.php`, `PhasePeriodLinkTest.php`, `FormPhaseLifecycleTest.php`; `tests/frontend/pages/FormEditPage.spec.ts`, `tests/frontend/pages/PhaseIndexPage.spec.ts` (baru), `PhaseCard.spec.ts`

**Interfaces:**

- Consumes: Task 2.
- Produces:
    - Route `admin.phases.index` GET `/admin/phases` (kueri opsional `form` = id Form; id tak dikenal → 404), `admin.phases.active` PATCH `/admin/phases/{formPhase}/active`, `admin.phases.destroy` DELETE `/admin/phases/{formPhase}`, `admin.phases.periods.store` POST `/admin/phases/{formPhase}/periods` (`can:periods.manage`). Teks flash yang ada dipertahankan.
    - `PhaseLibrary::run(?Form $usingForm): list<PhaseCardData>` — urut `title`, `id` (R2-W5); `$usingForm` menapis lewat `FormPhase::scopeUsingForm` (langkah mana pun). Tetap tanpa kueri per kartu.
    - `PhaseCardData::$submissionFormTitle: ?string` — judul Form langkah Pengajuan (BR-FE-28: paling banyak satu); TS `submissionFormTitle: string | null`.
    - Props `FormEngine/PhaseIndexPage` (Frame: ADMIN-08-08): `{ phases: PhaseCardData[], filterForm: FormHeaderData | null, can: { linkPeriod: boolean } }`.
    - `usePhaseActions()` tanpa argumen; `PhaseCard` props `{ phase, canLinkPeriod }` (sejak Task 2); `PhasePeriodDialog` props `{ phase }`.
    - `FormEditTabs` `active: 'general' | 'access'`; tab Phases = tautan `route('admin.phases.index', { form: form.id })`. `FormEditPage` tanpa cabang `phases`; tipe `FormEditPhasesProps` dihapus.
    - Menu: item `{ label: 'Fase', icon: Layers, routeName: 'admin.phases.index', activePattern: 'admin.phases.*', anyPermission: ['schemes.manage'] }` sesudah "Form Builder" di grup Konfigurasi.
    - `PhaseEditPage` kembali → `admin.phases.index` "Fase".

- [ ] **Step 1: Tulis/pindahkan test BE** `PhaseLibraryTest.php`: `it('lists every phase ordered by title')`, `it('shows the submission form of each phase')`, `it('filters to phases where any step uses the form')` (Form hanya di langkah monev tetap menampilkan fasenya), `it('returns 404 for an unknown form filter')`, `it('hides the link-period action without periods.manage')` + POST tautan 403. Pindahkan `PhasePeriodLinkTest`/`FormPhaseLifecycleTest` ke route baru.
- [ ] **Step 2: Jalankan** → FAIL.
- [ ] **Step 3: Implementasi BE.** `grep -rn "forms.phases" app routes resources tests` → kosong.
- [ ] **Step 4: Pest berkas di atas** → PASS.
- [ ] **Step 5: Tulis test Vitest** — `PhaseIndexPage.spec.ts`: kartu menampilkan `"Form pengajuan: {judul}"`; `filterForm` → teks `"Tahap yang memakai {judul}"` + tautan `"Tampilkan semua"` ke `admin.phases.index`; kosong → EmptyState. `FormEditPage.spec.ts`: tab Phases menaut ke pustaka bertapis; tidak ada lagi tab phases yang dirender di halaman ini.
- [ ] **Step 6: Implementasi FE**; Vitest berkas di atas → PASS.
- [ ] **Step 7: Commit** — `feat(form-engine): pustaka fase mandiri dan menu Fase`

### Task 4: Penutup G1 — dokumen status, seeder, gate

**Files:**

- Modify: `docs/adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md` (baris R2 "Selesai — {commit Task 3}")
- Modify: `docs/requirement-analysis/bdd/README.md` (baris `ADMIN-08-08` → "Fase — pustaka (`/admin/phases`)"; tambah `phase_library.feature` di daftar berkas bila README mendaftarnya)
- Modify: `docs/plans/2026-11-10-fase-reuse-roadmap.md` (G1 selesai; rencana rinci yang ada += R2), `docs/plans/2026-11-11-fase-reuse-catatan-bug.md` (temuan review akhir G1)

- [ ] **Step 1: Seeder** — di container: `php artisan migrate:fresh --seed --force && php artisan db:seed --class=EndToEndFlowSeeder --force` (DB = `simpas_test`, sama dengan DB dev; Pest menghapusnya pula). Expected: tanpa galat; fase seeder tetap satu Form pengajuan.
- [ ] **Step 2: Review akhir gelombang** (satu review menyeluruh G1, roadmap). Perbaiki Critical/Important + yang murah; sisanya ke catatan bug.
- [ ] **Step 3: Gate penuh** — `free -m`, lalu `composer run quality` (`COMPOSER_PROCESS_TIMEOUT=0`) dan `npm run quality`, berurutan di bawah watchdog. Laporkan angka test apa adanya.
- [ ] **Step 4: Dokumen status** (berkas di atas).
- [ ] **Step 5: Commit** — `docs: status R2 pustaka fase`
