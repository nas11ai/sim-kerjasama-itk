# 02 — Ubiquitous Language

**Versi:** 2.3  
**Status:** Draft

---

## Actors

| Term              | Bahasa Indonesia   | Definisi                                                                                                  |
| ----------------- | ------------------ | --------------------------------------------------------------------------------------------------------- |
| **Researcher**    | Dosen              | Pengguna yang mengajukan proposal. Terhubung ke `UserProfile`.                                            |
| **Operator**      | Operator LPPM      | Staf administrasi — mengelola periode, assign reviewer, verifikasi user, transfer ownership.              |
| **Reviewer**      | Reviewer           | Dosen/pakar yang ditunjuk menilai proposal. Bukan jalur registrasi sendiri.                               |
| **Admin**         | Admin              | Superuser — mengelola konfigurasi sistem, permission, dan aksi yang membutuhkan override level tertinggi. |
| **External User** | Pengguna Eksternal | User dari institusi non-ITK, masuk via invitation link.                                                   |

---

## Organization

| Term                     | Definisi                                                                                                                                          |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Organization**         | Node dalam hierarki organisasi. Tipe: `institution`, `faculty`, `department`, `study_program`, `external`, `unit`. Disimpan dalam adjacency list. |
| **Organization Tree**    | Seluruh struktur hierarki sebagai recursive tree.                                                                                                 |
| **Organization Subtree** | Semua descendant dari sebuah Organization node, termasuk node itu sendiri. Digunakan untuk scoping access.                                        |
| **InvitationToken**      | Token yang dibuat Operator untuk mengundang sekelompok user dari organisasi tertentu. Satu token bisa dipakai banyak user.                        |

---

## Form Engine

| Term                       | Definisi                                                                                                                                                                                                                                                                                               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Form**                   | Template pengisian — mendefinisikan field apa saja yang dikumpulkan.                                                                                                                                                                                                                                   |
| **FormField**              | Satu field dalam Form. Punya `field_type`, `config` (JSON untuk field kompleks), `required_since` (kapan field ini mulai jadi required).                                                                                                                                                               |
| **required_since**         | Timestamp kapan sebuah FormField mulai diberlakukan sebagai required. Digunakan untuk Temporal Field Binding — field hanya di-enforce sebagai required jika `required_since ≤ submission.created_at`.                                                                                                  |
| **Temporal Field Binding** | Kebijakan validasi field saat submit: field hanya di-enforce `is_required` jika field tersebut sudah ada (`created_at ≤ submission.created_at`) DAN sudah required (`required_since ≤ submission.created_at`) sebelum submission dibuat. Memastikan perubahan form tidak memblok draft yang sudah ada. |
| **FormFieldOption**        | Pilihan untuk field bertipe `select` atau `radio`.                                                                                                                                                                                                                                                     |
| **FormPhase**              | Satu lifecycle workflow (pengajuan, review, monev I, monev II, luaran).                                                                                                                                                                                                                                |
| **FormPhaseDetail**        | Step dalam FormPhase — menghubungkan FormAccessControl, SubmissionDate (hard deadline), PhaseType, urutan, dan `needs_review`.                                                                                                                                                                         |
| **FormAccessControl**      | Aturan akses sebuah Form: kombinasi `permission` (string via Spatie) + `organization_id`.                                                                                                                                                                                                              |
| **SubmissionPeriod**       | Periode pengajuan yang aktif.                                                                                                                                                                                                                                                                          |
| **SubmissionDate**         | Hard deadline untuk satu atau lebih FormPhaseDetail.                                                                                                                                                                                                                                                   |
| **FormSubmission**         | Entitas submission universal. `parent_submission_id` nullable — diisi untuk child submissions (laporan monev, kelengkapan).                                                                                                                                                                            |
| **FormFieldResponse**      | Jawaban scalar untuk satu FormField.                                                                                                                                                                                                                                                                   |
| **Child Submission**       | FormSubmission dengan `parent_submission_id` terisi — hanya untuk laporan monev dan kelengkapan berkas. Research Output tidak menggunakan mekanisme ini.                                                                                                                                               |
| **RepeatableField**        | Konsep UI: field yang bisa diisi lebih dari satu entri. Data dikirim ke extension tables — bukan `form_field_responses`.                                                                                                                                                                               |
| **FormSubmissionOverride** | Record yang dibuat Operator untuk bypass hard deadline pada satu submission + satu FormPhaseDetail. Researcher bisa lihat status override (active/expired) tapi tidak bisa lihat `reason`.                                                                                                             |
| **Optimistic Locking**     | Mekanisme mencegah concurrent edit — saat save, sistem cek `updated_at` di DB vs nilai di request. Kalau berbeda, ada yang sudah edit duluan dan request ditolak dengan pesan yang jelas.                                                                                                              |
| **ReviewEvaluationForm**   | Form penilaian yang diisi Reviewer — attached ke FormPhaseDetail yang `needs_review = true`.                                                                                                                                                                                                           |
| **ReviewFormResponse**     | Respons reviewer. Status: `draft` → `submitted` → `locked`.                                                                                                                                                                                                                                            |
| **ReviewSummary**          | Ringkasan review per reviewer untuk sebuah submission. Titik awal threaded discussion. Status `open` = ada permintaan revisi yang belum resolved.                                                                                                                                                      |
| **ReviewComment**          | Satu komentar dalam thread ReviewSummary. Bisa reply via `parent_comment_id`.                                                                                                                                                                                                                          |
| **SubmissionReviewer**     | Pivot antara FormSubmission dan Reviewer. Satu record per reviewer per siklus (pengajuan atau monev).                                                                                                                                                                                                  |

---

## Submission (SIMPAS-specific)

| Term                        | Definisi                                                                                                                                                                                                                         |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Submission**              | Sebuah `FormSubmission` (form pengajuan utama) beserta seluruh extension tables-nya.                                                                                                                                             |
| **Lead Researcher**         | Researcher yang membuat submission (`submitted_by`). Satu-satunya yang bisa submit dan resubmit.                                                                                                                                 |
| **ResearchMember**          | Dosen lain sebagai co-researcher. Role: `co_investigator` atau `member`.                                                                                                                                                         |
| **Co-Investigator**         | ResearchMember dengan role `co_investigator`. Bisa tambah/edit Research Output. Bisa menerima transfer ownership jika lead researcher dinonaktifkan.                                                                             |
| **Member**                  | ResearchMember dengan role `member`. Read-only untuk semua data submission.                                                                                                                                                      |
| **Submission-Level Access** | Lapis akses kedua di luar FormAccessControl — hanya `submitted_by` dan `research_members` yang bisa lihat data submission spesifik. Operator bypass via `submissions.view-all`. Reviewer bypass via `SubmissionReviewer` record. |
| **Ownership Transfer**      | Aksi Operator memindahkan `submitted_by` dari lead researcher yang dinonaktifkan ke Co-Investigator yang aktif.                                                                                                                  |
| **Withdrawal**              | Pembatalan submission yang sudah APPROVED. Hanya bisa dilakukan Operator via override — bukan self-service oleh Researcher. Semua data historis tetap ada.                                                                       |

---

## Scheme

| Term                | Definisi                                                                                                                                                               |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Scheme**          | Metadata aturan yang mengatur sebuah kategori pengajuan: `max_budget`, `max_members`, `duration_months`, dan `rules` JSONB untuk rule opsional.                        |
| **scheme.rules**    | JSONB column di tabel `schemes` untuk rule yang opsional dan extensible tanpa migration. Contoh: `min_reviewer_count`, `max_reviewer_workload`, `max_student_members`. |
| **scheme_selector** | FormField type khusus — dropdown scheme yang tersedia di period. Opsional per Form.                                                                                    |
| **trl_selector**    | FormField type khusus — dropdown TRL filtered by scheme terpilih.                                                                                                      |

---

## Review

| Term                      | Definisi                                                                                                                                                                   |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Reviewer Workload**     | Jumlah submission aktif yang sedang di-review oleh seorang reviewer. Dibatasi oleh `scheme.rules.max_reviewer_workload` — operator mendapat warning jika batas terlampaui. |
| **Reviewer Reassignment** | Aksi Operator mengganti reviewer yang tidak bisa melanjutkan. Record lama di-mark `replaced`, reviewer baru mendapat assignment fresh. History review lama tetap ada.      |

---

## Budget

| Term                   | Definisi                                                                                                     |
| ---------------------- | ------------------------------------------------------------------------------------------------------------ |
| **BudgetLineItem**     | Satu baris item anggaran. FK ke `form_submission_id`.                                                        |
| **BudgetComponent**    | Kategori belanja.                                                                                            |
| **Budget Grand Total** | Selalu dikalkulasi on-the-fly via `budgetLineItems()->sum('total')` — tidak disimpan sebagai kolom terpisah. |

---

## Monev

| Term                          | Definisi                                                                                                                                                                |
| ----------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Monev**                     | Monitoring dan Evaluasi — domain term, tetap disebut "Monev".                                                                                                           |
| **Monev Stage**               | Satu siklus monev — diimplementasikan sebagai serangkaian FormPhaseDetail dalam satu FormPhase lifecycle.                                                               |
| **Progress Report**           | Laporan kemajuan researcher — child FormSubmission.                                                                                                                     |
| **Monev Reviewer Suggestion** | Daftar reviewer dari pengajuan awal yang masih aktif, ditampilkan sebagai suggestion saat operator assign reviewer monev. Operator bebas memilih reviewer yang berbeda. |

---

## Research Output

| Term                        | Definisi                                                                                                                |
| --------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| **Research Output**         | Luaran penelitian/pengabdian. Disimpan di tabel `research_outputs` dengan JSONB `metadata`. Bukan child FormSubmission. |
| **output_type**             | String identifier tipe output: `article`, `book`, `ip`, `prototype`, `pks`, `meeting`. Extendable via config.           |
| **output_type_definitions** | Config yang mendefinisikan fields per `output_type`. Menambah tipe baru = tambah config, tidak butuh migration.         |

---

## Reporting

| Term                      | Definisi                                                                                                                                                                                    |
| ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Export Job**            | Background job via Laravel Queue untuk generate file export besar. Flow: dispatch → antri → proses → notifikasi in-app → file tersedia 24 jam via signed URL.                               |
| **Audit Trail**           | Rekam jejak semua aksi domain yang signifikan (submit, approve, reject, override, transfer ownership, dll). Diimplementasikan via `spatie/laravel-activitylog`. Domain owner: Reporting BC. |
| **Data Retention Policy** | Submission APPROVED atau REJECTED disimpan permanen. DRAFT yang period-nya tutup disimpan sebagai archived. Tidak ada hard delete untuk data submission apapun.                             |

---

## Submission Status Lifecycle

```
DRAFT → SUBMITTED → UNDER_REVIEW → NEEDS_REVISION → RESUBMITTED → APPROVED → WITHDRAWN
                                 → REJECTED (semi-manual via operator)
```

| Status           | Trigger                                                           | Deskripsi                   |
| ---------------- | ----------------------------------------------------------------- | --------------------------- |
| `DRAFT`          | Submission dibuat                                                 | Sedang diisi Researcher     |
| `SUBMITTED`      | Researcher submit                                                 | Menunggu reviewer di-assign |
| `UNDER_REVIEW`   | Operator assign reviewer terakhir                                 | Sedang dinilai              |
| `NEEDS_REVISION` | **Otomatis** — semua reviewer done + ada ReviewSummary open       | Diminta revisi              |
| `RESUBMITTED`    | Researcher resubmit                                               | Kembali ke reviewer         |
| `APPROVED`       | **Otomatis** — semua reviewer done + tidak ada ReviewSummary open | Disetujui                   |
| `REJECTED`       | **Semi-manual** — operator confirm eksplisit                      | Ditolak                     |
| `WITHDRAWN`      | Operator override (bukan self-service)                            | Dibatalkan setelah approved |

> **ARCHIVED** bukan status di DB — ini **computed property** di model. Submission dianggap archived jika `is_submitted = false` (masih DRAFT) DAN period-nya sudah tutup (`SubmissionPeriod.is_force_closed = true` ATAU semua `SubmissionDate` sudah terlewat). Tidak ada kolom atau migration tambahan — logic hidup di `FormSubmission::isArchived(): bool`.

---

## Period Lifecycle

| Term              | Definisi                                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| **Close Early**   | Operator menutup period sebelum semua SubmissionDate terlewat via flag `is_force_closed`.                                             |
| **Extend Period** | Operator memperbarui `SubmissionDate.datetime` ke tanggal baru. Submission yang sudah DRAFT tetap bisa disubmit sampai deadline baru. |

---

## Naming Conventions

| Konteks       | Konvensi                  | Contoh                                     |
| ------------- | ------------------------- | ------------------------------------------ |
| Model / Class | `PascalCase`, singular    | `FormSubmission`, `ReviewEvaluationForm`   |
| DB column     | `snake_case`              | `form_submission_id`, `required_since`     |
| Domain Event  | `PascalCase` + past tense | `ProposalSubmitted`, `ReviewerAssigned`    |
| Enum value    | `UPPER_SNAKE_CASE`        | `UNDER_REVIEW`, `NEEDS_REVISION`           |
| Vue component | `PascalCase`              | `RepeatableField.vue`, `OrgTreePicker.vue` |
| Composable    | `use` prefix              | `useSubmissionStatus`, `useOrgTree`        |

---

## Istilah yang Tidak Dipakai di Kode

| Jangan pakai                 | Pakai                                                 | Alasan                     |
| ---------------------------- | ----------------------------------------------------- | -------------------------- |
| `submissions` (tabel)        | `form_submissions`                                    | Tidak ada tabel terpisah   |
| `form_submissions.scheme_id` | `form_field_responses` via scheme_selector            | Single source of truth     |
| `submission_rules` (tabel)   | `scheme.rules` JSONB                                  | Tabel ini di-drop          |
| `ReviewerRole` (tabel/class) | Spatie role `reviewer_internal` / `reviewer_external` | Tabel sudah di-drop        |
| `nilai` (field)              | `score`                                               | English untuk field names  |
| `status` tanpa konteks       | `SubmissionStatus`                                    | Harus ada konteks spesifik |
