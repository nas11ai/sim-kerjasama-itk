# Desain Implementasi Backend — SIMPAS v2

**Versi:** 1.0
**Tanggal:** 2026-08-16
**Status:** Menunggu review

Dokumen ini menjabarkan rencana membangun backend SIMPAS v2 hingga fungsional
end-to-end. Ia turunan dari [`docs/requirement-analysis/ddd/`](../requirement-analysis/ddd/)
dan wireframe `simpas-wireframe` (57 frame).

Keputusan arsitektur yang mendasari dokumen ini tercatat terpisah di
[`docs/adr/`](../adr/). Kalau sebuah bagian di sini terasa mengejutkan,
alasannya — berikut alternatif yang ditolak — ada di ADR yang dirujuk.

---

## 1. Titik Berangkat

Fork ini mewarisi Form Engine dan Review yang sudah berjalan. Yang sudah bisa
dipakai: Form/Field/Phase/PhaseDetail/Period/Date/Override, rantai Review
(Reviewer → SubmissionReviewer → FormAssignment → FormResponse → Summary →
Comment), Organization tree dengan `subtreeIds()`, `FormAccessControl` berbasis
`permission + organization_id`, Scheme dengan rules JSONB, delapan state class,
dan `PermissionSeeder` yang sudah sesuai matriks DDD.

Yang belum ada sama sekali:

| Area                                                            | Kondisi           |
| --------------------------------------------------------------- | ----------------- |
| `research_members`, `student_members`, `submission_partners`, `submission_additional_files` | tabel & model nol |
| `research_outputs` + `research_output_files`                     | nol total         |
| `app/Policies/`, `isAccessibleBy()`                              | folder tidak ada  |
| `app/Events/`, `app/Listeners/`                                  | folder tidak ada  |
| `app/Notifications/`, tabel `notifications`                      | masih `EmailNotificationService` buatan sendiri |
| `StorageService` (MinIO/R2)                                      | belum ada         |
| Budget behavior (lock, `max_budget`, audit)                      | model ada, logic nol |
| Field type `scheme_selector`, `trl_selector`                     | tidak ada di seeder |
| Export background job, cetak PDF, halaman Audit Log              | belum ada         |
| Optimistic locking                                               | tidak ada di controller manapun |

### Kontradiksi di dokumen DDD yang harus dibereskan

Empat hal memblokir implementasi, bukan sekadar preferensi gaya. Semuanya sudah
diputuskan; lihat ADR untuk uraian lengkapnya.

1. **Tidak ada `form_phase_detail_id`.** Relasi submission → phase detail ditebak
   lewat `->first()` dan pecah di skenario Monev. → [ADR 0004](../adr/0004-form-phase-detail-id-on-submissions.md)
2. **Skema disimpan sebagai string di EAV.** Menggagalkan integritas, BR-SM-01,
   performa query, dan migrasi legacy. → [ADR 0005](../adr/0005-scheme-selector-writes-to-column.md)
3. **Auto-reject melanggar BR-REV-07**, dan memakai status `closed` yang tidak
   ada di ubiquitous language. → [ADR 0006](../adr/0006-rejection-is-manual-only.md)
4. **`SubmitFormSubmission` tidak memvalidasi apa pun** — tanpa Temporal Field
   Binding, tanpa cek proposal PDF (BR-SM-02), tanpa cek deadline.

---

## 2. Arsitektur

### Aliran

```
Route → Controller (tipis) → FormRequest → Data (DTO) → Action → Model
                                                          ↓
                                                    Event → Listener → Notification
             Policy dipanggil Controller, bukan Action
```

Action **tidak** melakukan otorisasi — itu tugas Policy yang dipanggil
`$this->authorize()` di Controller. Alasannya Action juga dipanggil dari Command
dan Job yang tidak punya user. Action mengasumsikan pemanggilnya sudah berhak,
dan asumsi itu ditegaskan di test.

Event di-dispatch **dari Action, bukan model observer**. Model tidak tahu use
case; `ProposalSubmitted` bukan konsekuensi "kolom berubah" melainkan "researcher
menekan Submit".

Pemetaan pola ini ke teknik refactoring bernama ada di
[`.github/CODING_STANDARDS.md`](../../.github/CODING_STANDARDS.md) →
[ADR 0011](../adr/0011-refactoring-vocabulary.md).

### Kontrak frontend

DTO `spatie/laravel-data` yang sama dipakai sebagai input Action **dan** output
props, sehingga bentuk data punya satu definisi. Setiap props halaman menyertakan
objek `can` berisi hasil Policy, supaya frontend bisa menyembunyikan UI tanpa
menebak aturan otorisasi — backend tetap yang menjaga.
→ [ADR 0007](../adr/0007-inertia-props-as-frontend-contract.md)

Field yang dituntut wireframe tapi tidak ada di DDD:

- `my_role` (`lead` | `co_investigator` | `member`) per baris daftar pengajuan —
  wireframe menampilkannya sebagai "Ketua"/"Anggota".
- `is_archived` — computed, bukan kolom (BR-SM-13).

**Enum `SubmissionStatus` frontend saat ini salah.** Isinya lima nilai
(`PENDING`, `UNDER_REVIEW`, `NEEDS_REVISION`, `APPROVED`, `REJECTED`) sementara
backend punya delapan state. Wireframe memakai kosakata yang benar. Enum frontend
diselaraskan saat kontrak disusun.

---

## 3. Perubahan Skema

```sql
-- form_submissions
ALTER TABLE form_submissions
  ADD form_phase_detail_id BIGINT REFERENCES form_phase_details(id),  -- nullable → backfill → NOT NULL
  ADD submission_period_id BIGINT REFERENCES submission_periods(id),  -- nullable → backfill → NOT NULL
  ADD scheme_id            BIGINT REFERENCES schemes(id),             -- NULL: skema opsional (BR-SCH-04)
  ADD trl_id               BIGINT REFERENCES technology_readiness_levels(id),
  ADD last_completed_step  SMALLINT NOT NULL DEFAULT 0;

-- BR-MON-03: satu child submission per phase detail
CREATE UNIQUE INDEX one_child_submission_per_phase_detail
  ON form_submissions (parent_submission_id, form_phase_detail_id)
  WHERE parent_submission_id IS NOT NULL;

-- BR-SM-01: satu submission aktif per period per scheme
CREATE UNIQUE INDEX one_active_submission_per_period_scheme
  ON form_submissions (submitted_by, submission_period_id, scheme_id)
  WHERE parent_submission_id IS NULL
    AND is_submitted = true
    AND status NOT IN ('rejected', 'withdrawn');
```

Index BR-SM-01 hanya berlaku untuk submission yang **sudah disubmit**, dan
sengaja tidak memakai `COALESCE(scheme_id, 0)`. Kedua keputusan itu berasal dari
alur wizard; alasan lengkapnya di [ADR 0005](../adr/0005-scheme-selector-writes-to-column.md)
dan [ADR 0012](../adr/0012-wizard-driven-submission-lifecycle.md).

### Backfill — asumsi yang wajib dibuktikan lebih dulu

Backfill `form_phase_detail_id` memakai inferensi lama (`form_access_control.form_id`).
Inferensi itu hanya benar kalau tidak ada Form yang dipakai lebih dari satu phase
detail. **Asumsi ini belum diverifikasi terhadap data sungguhan.** Migration wajib
menjalankan pemeriksaan ini lebih dulu dan **gagal keras** kalau ada yang ambigu:

```sql
SELECT fs.id, COUNT(DISTINCT fpd.id) AS kandidat
FROM form_submissions fs
JOIN form_access_controls fac ON fac.form_id = fs.form_id
JOIN form_phase_details fpd  ON fpd.form_access_control_id = fac.id
GROUP BY fs.id
HAVING COUNT(DISTINCT fpd.id) > 1;
```

Kalau ada hasil, baris-baris itu diselesaikan manual sebelum `NOT NULL` dipasang.
Menebak diam-diam tidak diperbolehkan.

`submission_period_id` diturunkan lewat
`form_phase_detail → submission_date → submission_period`. Jalur lewat
`submission_period_phases` **tidak bisa dipakai** karena pivot itu tidak punya
unique constraint — satu FormPhase boleh dimiliki banyak period.

### Tabel baru

| Tabel                          | Milestone | Catatan                                    |
| ------------------------------ | --------- | ------------------------------------------ |
| `notifications`                | M0        | bawaan Laravel, belum pernah dibuat        |
| `research_members`             | M0        | `role`: co_investigator \| member — dipindah dari M1 karena `FormSubmissionPolicy` (Task 5) butuh tabel ini untuk cabang anggota BR-SM-07, jadi Policy tidak bisa ditulis lengkap tanpanya |
| `student_members`              | M1        |                                            |
| `submission_partners`          | M1        |                                            |
| `submission_additional_files`  | M1        |                                            |
| `submission_outers`, `outer_types` | M1    | disebut aggregate BC Budget, ada di legacy |
| `research_outputs`, `research_output_files` | M4 | JSONB + GIN index                     |

---

## 4. Milestone

Urutan dan alasannya di [ADR 0009](../adr/0009-milestone-sequencing-critical-path-first.md).
Setelah **M2** sistem sudah fungsional end-to-end.

### M0 — Fondasi

Tidak menghasilkan fitur yang terlihat. Membereskan yang memblokir M1 dan M2.

| Area                            | Isi                                                                     |
| ------------------------------- | ----------------------------------------------------------------------- |
| Migration                       | seluruh perubahan skema di §3 + backfill terverifikasi                  |
| `app/Policies/`                 | `FormSubmissionPolicy` + `isAccessibleBy()` (BR-SM-07), `BudgetPolicy`  |
| `app/Events/`, `app/Listeners/` | kerangka + `ProposalSubmitted` sebagai contoh berjalan                  |
| `app/Notifications/`            | Laravel Notification dua channel; `EmailNotificationService` ditandai `@deprecated`, bukan dipensiunkan penuh — `UserFormController` (543 baris) dan `ReviewController` (780 baris) masih memanggilnya karena M0 tidak menyentuh alur keduanya (ADR 0008); migrasi sisanya jadi tugas M1/M2 |
| `app/Services/Storage/`         | interface `StorageService` + implementasi S3 (MinIO dev, R2 produksi)   |
| `app/Data/`                     | struktur DTO dasar + `SubmissionAbilitiesData`                          |
| Seeder                          | field type `scheme_selector` & `trl_selector`; `BudgetComponent` 7 kategori |
| Guard                           | `App\Support\OptimisticLock::assertNotStale()` untuk semua endpoint mutasi — dirancang sebagai trait tapi diubah jadi `final class` statis karena metodenya tidak memakai `$this`, dan PHPStan tidak menganalisis isi trait yang tidak dipakai |

**Definisi selesai M0:** `composer run quality` dan `npm run quality` hijau;
`getFormPhaseDetail()` tidak lagi memakai `->first()` di seluruh codebase; dan
`EvaluateSubmissionOutcome` **belum** ada — itu M2, jangan dicicil.

### M1 — Submission

Empat tabel extension, `SubmitFormSubmission` yang sesungguhnya (Temporal Field
Binding, proposal PDF per BR-SM-02, gerbang deadline), Budget dengan lock per
state dan validasi `max_budget` serta audit nilai lama/baru saat NEEDS_REVISION
(BR-BUD-08), transfer ownership, dan withdrawal.

Tujuh endpoint wizard beserta validasi bertingkat — lihat
[ADR 0012](../adr/0012-wizard-driven-submission-lifecycle.md).

### M2 — Review

`EvaluateSubmissionOutcome` menggantikan `updateStatusBasedOnReviews()`
([ADR 0006](../adr/0006-rejection-is-manual-only.md)), `AssignReviewers` dengan
`min_reviewer_count` (BR-REV-01), peringatan workload (BR-REV-04) dan conflict of
interest dua arah (BR-REV-02), reassignment yang menandai reviewer lama
`replaced` (BR-REV-10), serta aturan visibilitas skor (BR-REV-11, BR-REV-12).

### M3 — Identity & Access

Tiga jalur registrasi, verifikasi NIDN, deaktivasi user dengan guard transfer
ownership (BR-IAM-10). `Faculty` dan `StudyProgram` dihapus di sini.

### M4 — Monev & Research Output

Orkestrasi child submission per phase detail, assignment reviewer per siklus,
`research_outputs` dengan tipe yang digerakkan config.

### M5 — Reporting

Export lewat background job (BR-RPT-02), cetak PDF, halaman audit log, statistik
dashboard.

---

## 5. Strategi Test

Test berjalan di PostgreSQL, bukan SQLite — sudah jadi aturan di
`CONTRIBUTING.md` dan sekarang ditegakkan CI.

Yang wajib ada di M0 karena jadi fondasi milestone berikutnya:

- **Backfill migration** — dengan data ambigu **dan** tidak ambigu; pastikan yang
  ambigu menggagalkan migration alih-alih menebak.
- **Unique index BR-SM-01** — dua submit ke skema sama ditolak; dua *draft* ke
  skema sama **diizinkan**. Test kedua inilah yang membedakan keputusan yang benar
  dari versi salahnya.
- **Policy** — matriks lengkap: lead, co_investigator, member, reviewer assigned,
  operator, orang luar.
- **Optimistic locking** — `updated_at` basi menghasilkan 409.
- **Tidak ada jalur otomatis menuju REJECTED** (BR-REV-07).

Tidak ditulis test-nya di M0: `StorageService` terhadap MinIO sungguhan (cukup
`Storage::fake()`) dan pengiriman email sungguhan (`Notification::fake()`).

---

## 6. Yang Sengaja Tidak Dikerjakan

| Hal                                      | Alasan                                                              |
| ---------------------------------------- | ------------------------------------------------------------------- |
| Migrasi data legacy                      | [ADR 0010](../adr/0010-defer-legacy-data-migration.md) — spec terpisah |
| Upgrade 4 paket dengan 21 advisory keamanan | Di luar scope; `composer audit` sudah jalan di CI sebagai step non-blocking |
| Observability stack (Grafana, Loki, GlitchTip) | Telescope sudah cukup untuk development; produksi ditangani terpisah |
| Mengganti 9 `confirm()` native dengan dialog | Pekerjaan UI, bukan backend                                     |
| `declare(strict_types=1)`                | Menyentuh 546 file dan mengubah perilaku runtime — butuh PR tersendiri |

---

## 7. Dokumen DDD yang Harus Diperbarui

Keputusan di dokumen ini menyimpang dari DDD v2.3 di empat tempat. Dokumen sumber
diperbarui ke **v2.4** agar tidak berpisah jalan dengan kode:

| Berkas                        | Perubahan                                                                     |
| ----------------------------- | ----------------------------------------------------------------------------- |
| `supporting/04_scheme.md`     | Batalkan "Scheme ID tidak disimpan di form_submissions"                       |
| `02_ubiquitous_language.md`   | `scheme_selector`/`trl_selector` sekelas RepeatableField; hapus status `closed` |
| `core/01_submission.md`       | Alur pembuatan menyebut wizard tujuh langkah; tambahkan kolom baru            |
| `core/02_review.md`           | Tegaskan tidak ada jalur otomatis menuju REJECTED                             |
| `01_domain_map.md`            | Ganti *Implementation Priority* dengan urutan M0–M5                           |
