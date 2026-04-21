# 02 — Ubiquitous Language

**Versi:** 2.1  
**Status:** Draft

---

## Actors

| Term                 | Bahasa Indonesia   | Definisi                                                                              |
| -------------------- | ------------------ | ------------------------------------------------------------------------------------- |
| **Researcher**       | Dosen              | Pengguna yang mengajukan proposal. Terhubung ke `UserProfile`.                        |
| **Operator**         | Operator LPPM      | Staf administrasi — mengelola periode, assign reviewer, verifikasi user.              |
| **InternalReviewer** | Reviewer Internal  | Reviewer dari dalam ITK. Spatie role: `reviewer_internal`.                            |
| **ExternalReviewer** | Reviewer Eksternal | Reviewer dari institusi luar. Spatie role: `reviewer_external`. Akses lebih terbatas. |
| **Admin**            | Admin              | Superuser — mengelola konfigurasi sistem dan permission.                              |
| **ExternalUser**     | Pengguna Eksternal | User dari institusi non-ITK, masuk via InvitationToken.                               |

---

## Organization

| Term                    | Definisi                                                                                                                                                                  |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Organization**        | Node dalam hierarki organisasi. Type: `institution`, `faculty`, `department`, `study_program`, `external`, `unit`. Adjacency list — `parent_id` nullable untuk root node. |
| **OrganizationTree**    | Seluruh struktur hierarki sebagai recursive tree. ITK dan institusi eksternal adalah root node yang berbeda.                                                              |
| **OrganizationSubtree** | Semua descendant dari sebuah Organization node, termasuk node itu sendiri. Digunakan untuk scoping access.                                                                |
| **InvitationToken**     | Token yang dibuat Operator untuk mengundang sekelompok user dari satu organisasi. Satu token bisa dipakai banyak user.                                                    |

---

## Form Engine

| Term                     | Definisi                                                                                                                                                                                                     |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Form**                 | Template pengisian — mendefinisikan field apa saja yang dikumpulkan.                                                                                                                                         |
| **FormField**            | Satu field dalam Form. Punya `field_type` dan `config` JSON.                                                                                                                                                 |
| **FormFieldOption**      | Pilihan untuk field bertipe `select` atau `radio`.                                                                                                                                                           |
| **FormPhase**            | Satu fase dalam lifecycle penelitian. Dalam SIMPAS, idealnya satu FormPhase mencakup seluruh lifecycle (pengajuan → revisi → monev → luaran).                                                                |
| **FormPhaseDetail**      | Step spesifik dalam FormPhase — kombinasi FormAccessControl + PhaseType + order + `needs_review`. `needs_review = true` menjadi gate: step selanjutnya tidak bisa diakses sebelum reviewer approve step ini. |
| **FormAccessControl**    | Aturan akses sebuah Form: kombinasi `role_id` + `organization_id`. Role bisa berupa `researcher`, `reviewer_internal`, `reviewer_external`, dll.                                                             |
| **SubmissionPeriod**     | Periode pengajuan yang aktif. Berisi FormPhase via `SubmissionPeriodPhase` dan konfigurasi rules via `SubmissionRule`.                                                                                       |
| **SubmissionRule**       | Key-value rule untuk sebuah period. Contoh: `min_reviewer_count = 2`. Operator bisa ubah tanpa sentuh kode.                                                                                                  |
| **SubmissionDate**       | Tanggal penting dalam period dengan label deskriptif (e.g., "Batas Submit", "Pengumuman Hasil").                                                                                                             |
| **FormSubmission**       | Entitas submission universal. Punya `parent_submission_id` (nullable) untuk child submissions. Satu record = satu user mengisi satu Form dalam satu fase.                                                    |
| **FormFieldResponse**    | Satu jawaban scalar untuk satu FormField. Tidak untuk array atau object.                                                                                                                                     |
| **ReviewEvaluationForm** | Form penilaian yang diisi Reviewer — attached ke FormPhaseDetail yang `needs_review = true`.                                                                                                                 |
| **ReviewSummary**        | Ringkasan review per reviewer per submission. Titik awal threaded discussion.                                                                                                                                |
| **ReviewComment**        | Komentar dalam thread ReviewSummary. Support reply via `parent_comment_id`.                                                                                                                                  |
| **SubmissionReviewer**   | Pivot FormSubmission × Reviewer. Punya `evaluation_status`.                                                                                                                                                  |

---

## Special FormField Types

| Field Type            | Definisi                                                                                                                                                                                            |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **`scheme_selector`** | Field khusus untuk memilih Scheme. Config menentukan apakah filter by period. Kalau field ini tidak ada di Form, submission tidak butuh scheme.                                                     |
| **`trl_selector`**    | Field khusus untuk memilih TRL. Config berisi `depends_on` (key field scheme_selector). TRL options di-load berdasarkan scheme yang dipilih. Kalau field ini tidak ada, submission tidak butuh TRL. |
| **`repeatable`**      | Field yang bisa diisi banyak entri. Config berisi schema sub-fields. Data dikirim ke extension tables, **bukan** ke `form_field_responses`.                                                         |

---

## Scheme

| Term           | Definisi                                                                                                                                        |
| -------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **Scheme**     | Entitas mandiri berisi aturan skema penelitian: max_budget, max_members, duration. Decoupled dari Form — `form_submissions.scheme_id` nullable. |
| **SchemeType** | Tipe skema (DIPA, PNBP, BIMA, dll).                                                                                                             |
| **TRL**        | Technology Readiness Level. Setiap Scheme punya allowed TRLs.                                                                                   |

---

## Reviewer

| Term                  | Definisi                                                                                                                                |
| --------------------- | --------------------------------------------------------------------------------------------------------------------------------------- |
| **Reviewer**          | Record di tabel `reviewers` — user yang ditunjuk Operator untuk menilai. Punya `reviewer_type` (`internal` / `external`) untuk display. |
| **reviewer_internal** | Spatie role untuk reviewer dari dalam ITK. Akses lebih luas.                                                                            |
| **reviewer_external** | Spatie role untuk reviewer dari luar ITK. Akses lebih terbatas (tidak lihat data internal tertentu).                                    |
| **MinReviewerCount**  | Rule yang dikonfigurasi per SubmissionPeriod via `SubmissionRule`. Default 2, bisa diubah admin tanpa kode.                             |

---

## Monev

| Term               | Definisi                                                                                                                                       |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| **Monev**          | Monitoring dan Evaluasi — domain term, tetap disebut Monev.                                                                                    |
| **MonevStage**     | Satu siklus monev (Monev I, Monev II, dll) — diimplementasikan sebagai serangkaian FormPhaseDetail dalam FormPhase yang sama dengan pengajuan. |
| **ProgressReport** | Laporan kemajuan researcher — child FormSubmission dengan `parent_submission_id` = FormSubmission pengajuan utama.                             |

---

## Research Output

| Term                   | Definisi                                                                                                                                        |
| ---------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------- |
| **ResearchOutput**     | Satu record luaran dalam tabel `research_outputs`. Punya `output_type` dan `metadata` JSONB.                                                    |
| **output_type**        | Tipe luaran: `article`, `book`, `intellectual_property`, `prototype`, `cooperation_agreement`, `meeting_record`. Bisa ditambah tanpa migration. |
| **ResearchOutputFile** | File lampiran untuk sebuah ResearchOutput. Tabel terpisah karena satu output bisa punya banyak file.                                            |

---

## Status Values

### FormSubmission.status

| Value            | Deskripsi                   |
| ---------------- | --------------------------- |
| `PENDING`        | Menunggu proses selanjutnya |
| `UNDER_REVIEW`   | Sedang dinilai reviewer     |
| `NEEDS_REVISION` | Diminta revisi              |
| `APPROVED`       | Disetujui                   |
| `REJECTED`       | Ditolak                     |

### SubmissionReviewer.evaluation_status

| Value          | Deskripsi                                  |
| -------------- | ------------------------------------------ |
| `pending`      | Belum selesai evaluation form              |
| `completed`    | Semua required evaluation form disubmit    |
| `not_required` | Tidak ada evaluation form yang perlu diisi |

---

## Naming Conventions

| Konteks       | Konvensi                  | Contoh                                  |
| ------------- | ------------------------- | --------------------------------------- |
| Model / Class | `PascalCase`, singular    | `FormSubmission`, `ResearchOutput`      |
| DB column     | `snake_case`              | `form_submission_id`, `output_type`     |
| Domain Event  | `PascalCase` + past tense | `ProposalSubmitted`, `ReviewerAssigned` |
| Enum          | `UPPER_SNAKE_CASE`        | `UNDER_REVIEW`, `NEEDS_REVISION`        |
| Vue component | `PascalCase`              | `SchemeSelector.vue`, `TrlSelector.vue` |
| Composable    | `use` prefix              | `useSubmissionPhase`, `useOrgTree`      |

---

## Istilah yang Tidak Dipakai di Kode

| Jangan pakai                         | Pakai                                             | Alasan                                                   |
| ------------------------------------ | ------------------------------------------------- | -------------------------------------------------------- |
| `ReviewerRole` (tabel)               | Spatie role                                       | Dihapus, cukup `reviewer_internal` / `reviewer_external` |
| `reviewer_type_id` (FK)              | `reviewer_type` varchar                           | Tidak perlu FK ke tabel yang sudah tidak ada             |
| `submissions` (tabel)                | `form_submissions`                                | Tidak ada tabel terpisah                                 |
| `faculty_id` di user                 | `organization_id`                                 | Org tree menggantikan                                    |
| `study_program_id` di access control | `organization_id`                                 | Org tree menggantikan                                    |
| `QuestionnaireForm`, `MonevAnswer`   | `ReviewEvaluationForm`, `ReviewFormFieldResponse` | Sudah digantikan                                         |

---

## Addendum v2.1 — Terms yang Berubah

| Term Lama                                   | Term Baru                                                            | Alasan                                                    |
| ------------------------------------------- | -------------------------------------------------------------------- | --------------------------------------------------------- |
| `ReviewerRole` (tabel)                      | `reviewer_type` (field varchar) + Spatie roles                       | Dihapus — Spatie sudah cukup                              |
| `research_output` per tipe (tabel terpisah) | `research_outputs` + `metadata JSONB`                                | Satu tabel generik, tidak perlu migration untuk tipe baru |
| Monev sebagai FormPhase terpisah            | Monev sebagai FormPhaseDetail dalam satu FormPhase lifecycle         | Linking lebih natural via parent_submission_id            |
| `Scheme.form_id` (one-to-one)               | `form_submissions.scheme_id nullable` + field type `scheme_selector` | Scheme decoupled dari Form                                |

### Term Baru

| Term                        | Definisi                                                                                                                                                             |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **scheme_selector**         | FormField type khusus — render dropdown Scheme yang tersedia di period. Opsional per Form.                                                                           |
| **trl_selector**            | FormField type khusus — render dropdown TRL yang difilter berdasarkan Scheme terpilih. Hanya valid jika ada `scheme_selector` di Form yang sama.                     |
| **output_type**             | String identifier tipe research output: `article`, `book`, `ip`, `prototype`, `pks`, `meeting`. Extendable via config.                                               |
| **output_type_definitions** | Config (bisa di System Configuration atau app config) yang mendefinisikan fields per `output_type`. Menambah tipe baru = tambah config entry, tidak butuh migration. |
| **reviewer_internal**       | Spatie role — reviewer dari internal ITK. Bisa lihat skor reviewer lain setelah semua selesai.                                                                       |
| **reviewer_external**       | Spatie role — reviewer dari luar ITK. Tidak bisa lihat skor reviewer lain.                                                                                           |
| **min_reviewer_count**      | Rule di `submission_rules` yang menentukan berapa minimum reviewer per submission. Dikonfigurasi per SubmissionPeriod.                                               |
| **Lifecycle FormPhase**     | Satu FormPhase yang mencakup seluruh tahapan: pengajuan → review → revisi → monev I → monev II → luaran. FormPhaseDetail yang menentukan urutan dan akses.           |
