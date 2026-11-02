# FE v2 — Irisan E1b: Jenis Tenggat Tetap — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Label tenggat teks bebas (W72) diganti **jenis tenggat tetap** yang terikat ke aturan domain. Ada tiga jenis (Batas Submit, Batas Evaluasi, Batas Revisi) pada sumbu siklus (pengajuan utama, Monev I, Monev II, …). Setiap jenis punya satu pemakai di kode, sehingga BR-FE-17 benar-benar ditegakkan, bukan hanya dinamai. Tenggat tetap bisa dilampaui oleh pemegang wewenangnya: Operator periode menggeser tanggal untuk semua (`periods.manage`, BR-SCH-08), dan Operator pengajuan memberi override per pengajuan per jenis (`submissions.view-all`, BR-FE-08..10). Penutupan paksa tetap tak bisa ditembus (BR-SCH-07).

**Architecture:** `App\Enums\DeadlineKind` (`submit`, `evaluation`, `revision`) + kolom `submission_dates.kind`/`cycle` menggantikan tabel `submission_date_labels`. `FormPhaseDetail` memegang tiga tenggat: `submission_date_id` (jenis submit, untuk pengisi), `evaluation_date_id` (jenis evaluasi, wajib bila `needs_review`), dan `revision_date_id` (jenis revisi, opsional). Ketiganya harus satu siklus dan satu periode. Penegakan ada di tiga gerbang yang sudah ada, `SubmissionPeriodGate`, `PhaseAccessGate`, dan `EvaluationDueDate`, ditambah validasi di `AssertPhaseDeadlines`. Label tampilan diturunkan dari jenis + siklus (`SubmissionDate::label()`), bukan disimpan.

**Tech Stack:** Laravel 13 / PHP 8.4, PostgreSQL 18 (`NULLS NOT DISTINCT`), Inertia v3 + Vue 3 + TS, Pest, Vitest. Tanpa dependency baru.

**Spec:** keputusan pemilik 2026-11-03 (sesi E1):
- "jenis tenggat tetap" dipilih alih-alih label terkonfigurasi atau label berkapabilitas;
- irisan baru sebelum E2;
- "tanggalnya tetap bisa di-override oleh user yang punya izin".

Sumber aturan:
- DDD `ddd/generic/01_form_engine.md`: BR-FE-01, 07, 08–10, 15–17, 21; kelas `SubmissionDate`/`FormPhaseDetail`.
- DDD lain: `ddd/supporting/04_scheme.md` (BR-SCH-07, 08), `ddd/supporting/02_monev.md` (BR-MON-05), `ddd/core/01_submission.md` (BR-SM-10, 13), `ddd/02_ubiquitous_language.md` ("Period Lifecycle").
- BDD: `bdd/generic/form_engine/phase_window.feature` (FE-WINDOW-01..07), `deadline_override.feature` (FE-OVERRIDE-01..11), `bdd/supporting/scheme.feature` (SCH-RULE-11..13).
- `docs/design/fe-v2-design.md` §9 W20, W25, W67–W75; irisan E1 (`docs/plans/2026-11-02-fe-e1-period.md`).

**Ukuran:** irisan besar → sesi ini menulis rencana, sesi berikutnya menjalankannya (§6). Enam task.

## Temuan yang membuat irisan ini perlu (diperiksa 2026-11-03)

1. **Label tidak berarti apa-apa bagi kode.** Tidak ada baris yang membaca nama label (hanya untuk tampilan). Yang menggerbangi adalah `FormPhaseDetail` yang menunjuk tanggal.
2. **BR-FE-17 tidak ditegakkan di jalur produksi.** `AssertReviewedPhaseDetail` memasang form evaluasi ke detail ber-`needs_review` (detail milik peneliti), lalu `EvaluationDueDate` membaca tanggal detail itu. Akibatnya tenggat reviewer = Batas Submit peneliti, persis yang dilarang BR-FE-17 ("jendela kerjanya nol atau negatif"). Test FE-WINDOW-01/02/05..07 lolos karena fixture-nya memasang form ke detail evaluasi terpisah, padahal jalur produksi menolak bentuk itu. Inilah risiko W20 yang tercatat.
3. **Batas Revisi tidak punya pemakai.** `[FE-WINDOW-03]` hanya memeriksa `isWithinDeadline()` detailnya sendiri. Penyuntingan saat NEEDS_REVISION digerbangi detail pengajuan (`SaveSubmissionDraft`, `SubmitFormSubmission`), jadi janji BR-BUD-03 bergantung pada Batas Submit.
4. **"Dibuka" salah.** `SubmissionPeriodGate::isClosed` menunggu **semua** tanggal lewat. Dengan Batas Evaluasi sebulan setelah Batas Submit, daftar menulis "Dibuka" dan `StartSubmission` masih menerima pengajuan baru setelah batas submit (BR-FE-01).
5. **Monev butuh siklus bernomor** ("Batas Monev I/II", BR-MON-05). Jenis tetap tanpa sumbu siklus tidak cukup.

## Keputusan model (perlu disetujui pemilik sebelum dijalankan)

- **K8a — tiga jenis × siklus.** `DeadlineKind`: `submit` "Batas Submit", `evaluation` "Batas Evaluasi", `revision` "Batas Revisi". `cycle` bernilai null untuk pengajuan utama dan `n ≥ 1` untuk Monev n. Label tampilan:
  - siklus null: "Batas Submit", "Batas Evaluasi", "Batas Revisi";
  - siklus n: "Batas Laporan Monev I", "Batas Evaluasi Monev I", "Batas Revisi Monev I".

  Satu periode punya paling banyak satu tanggal per (jenis, siklus): unik `(submission_period_id, kind, cycle) NULLS NOT DISTINCT`.
- **K8b — detail memegang tenggat per aktor.** `form_phase_details` mendapat `evaluation_date_id` (nullable) dan `revision_date_id` (nullable). `submission_date_id` tetap NOT NULL (BR-FE-07) dan harus berjenis `submit`. `evaluation_date_id` wajib bila `needs_review` dan harus berjenis `evaluation`. `revision_date_id` harus berjenis `revision`. Ketiganya satu periode dan satu siklus.
  - Form evaluasi tetap dipasang ke detail ber-`needs_review` (`AssertReviewedPhaseDetail` tidak berubah).
  - `EvaluationDueDate` membaca `evaluation_date_id`.
  - Alternatif yang ditolak: detail evaluasi terpisah, seperti fixture lama, ditolak karena jalur produksi dan ubiquitous language memasang form evaluasi ke detail yang direview.
- **K8c — siapa yang digerbangi tenggat mana** (`PhaseAccessGate`):
  - pengisi DRAFT/SUBMITTED → `submit`;
  - pengisi NEEDS_REVISION → `revision` bila ada, selain itu `submit` (perilaku hari ini);
  - reviewer → `evaluation`.
- **K8d — "Dibuka" dan pengajuan baru (BR-FE-01)** membaca Batas Submit siklus pengajuan utama periode itu. Periode tanpa Batas Submit = "Belum Bertanggal". `displayStatus` dan filter E1 ikut berubah. `isArchived()` (BR-SM-13) tetap "ditutup paksa atau seluruh tanggal lewat", supaya draft revisi tidak terarsip sebelum Batas Revisi.
- **K8e — override tetap jalan, oleh pemegang izin saja.**
  - Untuk semua pengajuan: pemegang `periods.manage` menggeser tanggal jenis apa pun (BR-SCH-08 hanya untuk `submit`/`revision` yang mundur, karena keduanya punya pemilik draft; `evaluation` tidak mengirim notifikasi ke peneliti).
  - Untuk satu pengajuan: pemegang `submissions.view-all` (`FormSubmissionPolicy::grantOverride`) membuat `FormSubmissionOverride` per (pengajuan, detail). Override membuka **jenis yang sedang menggerbangi** aktor itu, termasuk evaluasi (W25).
  - Tidak ada yang menembus penutupan paksa (BR-SCH-07, FE-OVERRIDE-11).
  - Pihak tanpa izin → 403.
- **K8f — label legacy dihapus.** Tabel `submission_date_labels`, `SubmissionDateLabel`, `SubmissionDateLabelSeeder`, dan `SubmissionDateLabel::named` (E1, W72) dihapus. Aplikasi belum live: DB dev di-`migrate:fresh`, tanpa migrasi data.
- **K8g — form builder legacy.** `FormBuilder/Steps/Step5SubmissionPeriod.vue` + `BuildCompleteForm` membuat periode dengan `label_id`. Usul: langkah periode di builder legacy hanya **memilih** periode yang sudah ada (dibuat di layar E1), lalu mengikat Batas Submit/Evaluasi siklus utamanya. Penyuntingan Vue legacy seminimal yang dibutuhkan gate; layarnya diganti E2. Alternatif: hapus langkah periode dari builder sekarang.

Semua keputusan di atas mengubah DDD, jadi Task 1 menyunting dokumen DDD **dengan persetujuan pemilik ini**. Tanpa persetujuan K8a–K8g, jangan mulai Task 1.

## Global Constraints

- DDD/BDD disunting hanya di Task 1, sesuai K8a–K8g. BR baru wajib punya skenario di commit yang sama (`BddCoverageTest` assertion 7). `pending.txt` dan `uncovered.txt` hanya boleh menyusut.
- Migration hanya ke DB dev/test (`simpas_dev_pg`, `simpas_browser`); tidak ada data sungguhan.
- Status hanya lewat `transitionTo()`. Gerbang tidak boleh punya cabang testing. PHPStan tanpa ignore/baseline/cast. TS tanpa `any`.
- Vue baru ≤ 250 baris; legacy Vue hanya disentuh seminimal yang dibutuhkan gate (memori: v2 menggantikannya).
- Gate: `composer run quality` (Pest **serial** di `simpas-ci:8.4`) + `npm run quality`; cek browser E1 diulang.
- Commit satu baris, Indonesia, tanpa trailer `Co-Authored-By`.

## Review Focus

1. **Reviewer terkunci saat Batas Submit lewat** padahal Batas Evaluasi masih di depan. Evaluasi tetap terbuka. → Task 3 `[FE-WINDOW-01]` diikat ulang ke jalur produksi (form di detail `needs_review`).
2. **Pengajuan baru lolos setelah Batas Submit** karena tanggal lain masih di depan. → Task 3 skenario baru BR-FE-01.
3. **Override dibuat oleh pihak tanpa izin** (peneliti, reviewer, `periods.manage` tanpa `view-all`) → 403. Override untuk evaluasi membuka evaluasi, bukan pengisian. → Task 4.
4. **Detail memakai tanggal jenis/siklus/periode yang salah** (evaluasi memakai Batas Submit; Monev I memakai Batas Evaluasi utama) → 422. → Task 3 `AssertPhaseDeadlines`.
5. **Draft revisi terarsip sebelum Batas Revisi** karena "Dibuka" kini membaca Batas Submit. → Task 3 BR-SM-13 tetap seluruh tanggal.

---

### Task 1: DDD + BDD (dokumen kesepakatan)

**Files:** Modify `docs/requirement-analysis/ddd/generic/01_form_engine.md` (kelas `SubmissionDate`: `+DeadlineKind kind`, `+int cycle`; `FormPhaseDetail`: `+evaluation_date_id`, `+revision_date_id`; BR baru, BR-FE-01 diperjelas), `ddd/02_ubiquitous_language.md` ("Jenis Tenggat", "Siklus"), `bdd/generic/form_engine/phase_window.feature`, `deadline_override.feature`, `bdd/supporting/scheme.feature`, `pending.txt` (skenario baru masuk dulu, lalu dikeluarkan di task yang mengikatnya; plafon dinaikkan sementara **tidak** boleh, jadi skenario + test-nya ditulis di task yang sama bila plafon 0).

- [ ] **Step 1:** Tambah aturan.
  - **BR-FE-25** (jenis & siklus): setiap `SubmissionDate` berjenis `submit|evaluation|revision` bersiklus null|n, unik per periode.
  - **BR-FE-26** (pengikatan): `submission_date_id` berjenis submit; `evaluation_date_id` wajib bila `needs_review` dan berjenis evaluation; `revision_date_id` berjenis revision. Ketiganya satu periode dan satu siklus.
  - Perjelas **BR-FE-01**: periode menerima pengajuan baru selama Batas Submit siklus utamanya belum lewat dan tidak `is_force_closed`.
- [ ] **Step 2:** Tulis skenario (ID baru, tag BR + frame).
  - `[FE-WINDOW-08]` detail ber-review tanpa Batas Evaluasi ditolak.
  - `[FE-WINDOW-09]` evaluasi memakai tanggal berjenis submit ditolak.
  - `[FE-WINDOW-10]` NEEDS_REVISION digerbangi Batas Revisi.
  - `[FE-WINDOW-11]` siklus Monev memakai tenggat siklusnya sendiri.
  - `[FE-ACCESS-09]` pengajuan baru ditolak setelah Batas Submit walau Batas Evaluasi di depan.
  - `[FE-OVERRIDE-12]` override membuka evaluasi reviewer yang lewat Batas Evaluasi.
  - `[FE-OVERRIDE-13]` override membuka revisi yang lewat Batas Revisi.
  - `[FE-OVERRIDE-14]` tanpa `submissions.view-all` override ditolak 403.
  - `[SCH-RULE-16]` menggeser Batas Evaluasi tidak memberi tahu pemilik draft.

  Cek ID bebas dengan `grep -rn` sebelum menulis.
- [ ] **Step 3:** `./vendor/bin/pest tests/Unit/Bdd` → assertion 4 **gagal** (skenario tanpa test). Bawa ke Task 2–4 dan **jangan commit dulu**; Task 1 di-commit bersama Task 2 kalau plafon `pending.txt` 0. Ruling dicatat di ledger.

### Task 2: Model data jenis tenggat (BE)

**Files:**
- Create: `app/Enums/DeadlineKind.php`, migration `…_replace_submission_date_labels_with_deadline_kinds.php`.
- Modify: `app/Models/SubmissionDate.php` (`kind` cast enum, `cycle`, `label(): string`, hapus relasi label), `app/Models/FormPhaseDetail.php` (`evaluationDate()`, `revisionDate()`), factories, `DatabaseSeeder`, `EndToEndFlowSeeder`, `tests/Support/fixtures.php` (semua pembuat tanggal), dan setiap test yang membuat `SubmissionDateLabel`.
- Delete: `app/Models/SubmissionDateLabel.php`, `database/seeders/SubmissionDateLabelSeeder.php`.
- Test: `tests/Feature/FormEngine/DeadlineKindTest.php`.

**Interfaces:**
- Produces: `enum DeadlineKind: string { Submit='submit'; Evaluation='evaluation'; Revision='revision'; public function label(?int $cycle): string }` (K8a; angka Romawi I–X cukup, siklus > 10 → angka biasa).
- Produces: `SubmissionDate::label(): string` = `$this->kind->label($this->cycle)`. `SubmissionDate::scopeOfKind(Builder, DeadlineKind, ?int $cycle)`.
- Migration: `kind` varchar NOT NULL, `cycle` smallint NULL CHECK (`cycle` IS NULL OR `cycle` ≥ 1); unik `NULLS NOT DISTINCT` (Postgres ≥ 15; `DB::statement` karena Blueprint belum mendukung); drop `submission_date_label_id` + tabel `submission_date_labels`. `form_phase_details` dapat `evaluation_date_id`, `revision_date_id` (FK `submission_dates`, `restrictOnDelete` seperti `submission_date_id`). `down()` memulihkan struktur tanpa data.

- [ ] **Step 1: Test gagal.** `DeadlineKindTest`:
  - label per jenis/siklus ("Batas Evaluasi Monev II");
  - unik per (periode, jenis, siklus) termasuk siklus null → `QueryException`;
  - `cycle` 0 ditolak.
- [ ] **Step 2:** Jalankan → FAIL.
- [ ] **Step 3:** Implementasi + perbarui semua pembuat tanggal di factories, seeders, fixtures, dan test (`grep -rl "SubmissionDateLabel\|submission_date_label" app tests database` harus kosong di akhir).
- [ ] **Step 4:** `pest tests/Feature/FormEngine tests/Feature/Period tests/Feature/Scheme tests/Unit` → PASS. Test lain yang merah karena fixture dicatat dan diperbaiki di task ini.
- [ ] **Step 5: Commit** `feat(be): jenis tenggat tetap menggantikan label tanggal` (bersama Task 1 bila perlu, lihat Task 1 Step 3).

### Task 3: Gerbang membaca jenis (BE)

**Files:**
- Modify: `app/Support/SubmissionPeriodGate.php` (`isOpen` K8d; `isClosed` lama → `allDeadlinesPassed` untuk BR-SM-13), `app/Models/FormSubmission.php` (`resolveIsArchived` memakai `allDeadlinesPassed`), `app/Support/PhaseAccessGate.php` (`deadlineFor(FormSubmission, FormPhaseDetail): ?SubmissionDate` K8c), `app/Models/FormPhaseDetail.php` (`isWithinDeadline` menerima jenis), `app/Queries/Submission/EvaluationDueDate.php` dan `app/Queries/Review/EvaluationDueDate.php` (baca `evaluation_date_id`; yang kedua: tenggat penugasan mandiri = Batas Evaluasi detail pengajuan, fallback seminggu), `app/Actions/Form/AssertPhaseDeadlines.php` + `app/Data/Request/FormPhaseDetailInput.php` + `FormPhaseRequest` (tiga tenggat, K8b), `app/Queries/Period/SubmissionPeriodList.php` (filter status K8d).
- Test: `tests/Feature/FormEngine/PhaseWindowTest.php` (FE-WINDOW-01/02/05..07 diikat ulang ke form di detail `needs_review` + `evaluation_date_id`; 08–11 baru), `tests/Feature/FormEngine/AccessControlTest` atau file yang memegang FE-ACCESS-07 (FE-ACCESS-09 baru), `tests/Feature/Period/PeriodIndexTest.php` (filter status masih sama dengan `displayStatus`), `tests/Feature/Submission/ArchivalTest.php` (draft NEEDS_REVISION tidak terarsip sebelum Batas Revisi).

- [ ] **Step 1:** Tulis/ikat ulang test di atas. Assertion utama:
  - `[FE-WINDOW-01]` form evaluasi di detail `needs_review`, Batas Submit lewat, Batas Evaluasi di depan → `ReviewerFormAssignmentPolicy::evaluate` true.
  - `[FE-ACCESS-09]` `StartSubmission` 403 setelah Batas Submit.
  - `[FE-WINDOW-10]` `SaveSubmissionDraft` pada NEEDS_REVISION lolos setelah Batas Submit bila Batas Revisi di depan, dan 403 setelah Batas Revisi.
  - `AssertPhaseDeadlines` menolak 422 untuk jenis, siklus, atau periode yang salah.
- [ ] **Step 2:** FAIL. **Step 3:** implementasi. **Step 4:** `pest tests/Feature/FormEngine tests/Feature/Review tests/Feature/Submission tests/Feature/Period tests/Feature/Monev tests/Unit` → PASS.
- [ ] **Step 5: Commit** `feat(be): gerbang fase membaca jenis tenggat`

### Task 4: Override per jenis oleh pemegang izin (BE)

**Files:** Modify `app/Actions/Override/GrantPhaseOverride.php` (tidak berubah bentuk; validasi bahwa detail milik pengajuan atau induk monevnya tetap), `app/Support/PhaseAccessGate.php` (override membuka jenis dari `deadlineFor`), `app/Policies/ReviewerFormAssignmentPolicy.php` (W25 tetap, kini lewat `evaluation_date_id`), `app/Actions/Period/ExtendSubmissionDates.php` (BR-SCH-08 hanya untuk jenis `submit`/`revision` yang mundur, K8e).
Test: `tests/Feature/FormEngine/DeadlineOverrideTest.php` (FE-OVERRIDE-12..14), `tests/Feature/Scheme/DeadlineExtensionTest.php` (SCH-RULE-16).

- [ ] **Step 1:** Test gagal.
  - `[FE-OVERRIDE-12]` override pada detail pengajuan membuka evaluasi reviewer yang lewat Batas Evaluasi.
  - `[FE-OVERRIDE-13]` override membuka penyuntingan NEEDS_REVISION yang lewat Batas Revisi.
  - `[FE-OVERRIDE-14]` dataset peneliti / reviewer / pemegang `periods.manage` saja → `POST admin.submissions.overrides.store` 403; pemegang `submissions.view-all` → 302.
  - `[SCH-RULE-16]` mundurkan Batas Evaluasi dengan draft berjalan → `Notification::assertNothingSent()`.
  - Regresi: `[FE-OVERRIDE-11]` force close tetap mengalahkan override evaluasi.
- [ ] **Step 2:** FAIL. **Step 3:** implementasi. **Step 4:** test di atas + `tests/Feature/FormEngine tests/Feature/Review` → PASS.
- [ ] **Step 5: Commit** `feat(be): override tenggat per jenis hanya oleh pemegang izin`

### Task 5: Layar E1 memakai jenis tenggat (BE + FE)

**Files:**
- Modify: `app/Http/Requests/Period/SubmissionPeriodRequest.php` (`submission_dates.*.kind` in enum, `.cycle` nullable integer ≥ 1, unik (kind, cycle); hapus `label`), `app/Data/Request/SubmissionPeriodInput.php`, `CreateSubmissionPeriod`, `ExtendSubmissionDates` (kunci per (kind, cycle), bukan nama label), `PeriodDateData` (+`kind`, `cycle`, label turunan), `SubmissionPeriodList` (`labelOptions` → `kindOptions: list<Option>`), `resources/js/types/form-engine.ts`, `Components/FormEngine/PeriodDateRows.vue` (select jenis + input siklus, bukan teks bebas), `PeriodFormDialog.vue`, `docs/design/fe-v2-design.md` (W72 ditutup → K8; §8 baris E1b).
- Test: `tests/Feature/Period/SubmissionPeriodSaveTest.php` (test label W72 diganti: jenis tak dikenal 422, (kind, cycle) ganda 422), `tests/frontend/components/FormEngine/PeriodFormDialog.spec.ts`, `tests/frontend/pages/PeriodIndexPage.spec.ts`.

- [ ] **Step 1–4:** TDD seperti biasa. Assertion utama:
  - dialog mengirim `{kind, cycle, date}`;
  - baris terkunci (FE-DELETE-11) tetap terkunci;
  - status "Dibuka" mengikuti Batas Submit (K8d);
  - banner BR-SCH-08 hanya bila ada pemilik draft dan periode bukan force-closed.
- [ ] **Step 5: Commit** `feat(fe): periode memakai jenis tenggat tetap`

### Task 6: Builder legacy, cek browser, tutup irisan

**Files:** `app/Http/Requests/Form/FormBuilderRequest.php`, `app/Actions/Form/BuildCompleteForm.php`, `app/Http/Controllers/CompleteFormBuilderController.php`, `app/Queries/Form/SubmissionDateOptions.php`, `app/Queries/Form/FormPhaseFormOptions.php`, `resources/js/types/form-builder.ts`, `Pages/FormBuilder/Steps/Step5SubmissionPeriod.vue`, `Step6Review.vue` (K8g: pilih periode yang ada), `docs/design/fe-v2-design.md` (§8 E1b ☑, §9 K8 + W76..), skrip browser E1 diperbarui.

- [ ] **Step 1:** Test Pest builder yang ada (`tests/Feature/FormBuilder*`) diubah ke K8g → FAIL → implementasi → PASS.
- [ ] **Step 2:** Gate penuh: `composer run quality`, `npm run quality`.
- [ ] **Step 3:** Ulangi cek browser E1. Tambah langkah: buat periode dengan Batas Submit + Batas Evaluasi + Batas Laporan Monev I, lalu Batas Submit lewat → status "Ditutup", tetapi reviewer masih bisa mengevaluasi.
- [ ] **Step 4: Commit** `docs: irisan E1b jenis tenggat selesai`
