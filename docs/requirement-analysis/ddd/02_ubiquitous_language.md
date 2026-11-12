# 02 — Ubiquitous Language

**Versi:** 2.5  
**Status:** Draft

> **Perubahan v2.4 → v2.5** ([ADR 0019](../../adr/0019-fase-template-jadwal-periode-form-evaluasi-berversi.md)).
> Istilah baru **Angkatan**, **Kalender periode**, **Langkah**. FormPhaseDetail tidak lagi
> menunjuk SubmissionDate; ia menyebut jenis tenggat + siklus.

> **Perubahan v2.3 → v2.4.** `scheme_selector` dan `trl_selector` kini menulis ke
> kolom sungguhan, bukan ke `form_field_responses`
> ([ADR 0005](../../adr/0005-scheme-selector-writes-to-column.md)). Pembuatan
> pengajuan mengikuti wizard tujuh langkah
> ([ADR 0012](../../adr/0012-wizard-driven-submission-lifecycle.md)). Status
> `ReviewSummary` hanya `open` dan `resolved` — `closed` tidak pernah ada dan
> tidak boleh dipakai.

---

## Actors

| Term              | Bahasa Indonesia   | Definisi                                                                                                  |
| ----------------- | ------------------ | --------------------------------------------------------------------------------------------------------- |
| **Researcher**    | Dosen              | Pengguna yang mengajukan proposal. Terhubung ke `UserProfile`.                                            |
| **Operator**      | Operator LPPM      | Staf administrasi — mengelola periode, assign reviewer, verifikasi user, transfer ownership.              |
| **Reviewer**      | Reviewer           | Dosen/pakar yang ditunjuk menilai proposal. Bukan jalur registrasi sendiri.                               |
| **Admin**         | Admin              | Superuser — mengelola konfigurasi sistem, permission, dan aksi yang membutuhkan override level tertinggi. |
| **External User** | Pengguna Eksternal | User dari institusi non-ITK, masuk via invitation link. Akunnya `active` sejak menit pertama — tidak ada NIDN untuk diverifikasi (BR-IAM-12). |

---

## Account Status

Tiga gerbang berbeda yang sering dianggap satu. Satu user bisa lolos yang satu sambil
tertahan yang lain, dan menyatukannya jadi satu kolom "user siap?" membuat salah satu
kasus berperilaku salah tanpa ada yang gagal mencolok.

| Term                   | Definisi                                                                                                                                                                              |
| ---------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **VerificationStatus** | Status verifikasi NIDN pada `UserProfile`: `pending` → `active`, atau `pending` → `rejected` → `active` bila NIDN dikoreksi. Menggerbang **submit**, bukan login (BR-IAM-04, BR-IAM-13). |
| **Akun aktif**         | `User.is_active = true` — orangnya masih berdinas. Menggerbang **login** (BR-IAM-05). Akun bisa aktif tapi belum terverifikasi.                                                        |
| **Profil lengkap**     | `UserProfile::isComplete()` — telepon, bidang keahlian, dan foto sudah terisi. Menggerbang **pembuatan Submission** (BR-IAM-03).                                                       |

---

## Organization

| Term                     | Definisi                                                                                                                                          |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Organization**         | Node dalam hierarki organisasi. Tipe: `institution`, `faculty`, `department`, `study_program`, `external`, `unit`. Disimpan dalam adjacency list. |
| **Organization Tree**    | Seluruh struktur hierarki sebagai recursive tree.                                                                                                 |
| **Organization Subtree** | Semua descendant dari sebuah Organization node, termasuk node itu sendiri. Digunakan untuk scoping access.                                        |
| **InvitationToken**      | Token yang dibuat Operator untuk mengundang sekelompok user dari organisasi tertentu. Satu token bisa dipakai banyak user, dan membawa `organization_id` **beserta daftar permission** yang diwariskan ke pendaftar (BR-IAM-12). |
| **Token dicabut**        | Undangan yang dinonaktifkan Operator sebelum kuotanya habis. Salah satu dari empat sebab token tidak berlaku, bersama kuota habis, kedaluwarsa, dan organisasinya nonaktif/terhapus (BR-IAM-06). |
| **Organisasi nonaktif**  | `Organization.is_active = false` — unit yang tidak dipakai lagi tapi tidak dihapus. Ia memutus rantai leluhur access control dan membatalkan token undangannya (BR-IAM-14). |

---

## Form Engine

| Term                       | Definisi                                                                                                                                                                                                                                                                                               |
| -------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Form**                   | Template pengisian — mendefinisikan field apa saja yang dikumpulkan.                                                                                                                                                                                                                                   |
| **FormField**              | Satu field dalam Form. Punya `field_type`, `config` (JSON untuk field kompleks), `required_since` (kapan field ini mulai jadi required).                                                                                                                                                               |
| **required_since**         | Timestamp kapan sebuah FormField mulai diberlakukan sebagai required. Digunakan untuk Temporal Field Binding — field hanya di-enforce sebagai required jika `required_since ≤ submission.created_at`.                                                                                                  |
| **Temporal Field Binding** | Kebijakan validasi field saat submit: field hanya di-enforce `is_required` jika field tersebut sudah ada (`created_at ≤ submission.created_at`) DAN sudah required (`required_since ≤ submission.created_at`) sebelum submission dibuat. Memastikan perubahan form tidak memblok draft yang sudah ada. |
| **FormFieldOption**        | Pilihan untuk field bertipe `select` atau `radio`.                                                                                                                                                                                                                                                     |
| **FormPhase** (**Fase**)   | Satu lifecycle workflow (pengajuan, review, monev I, monev II, luaran). Template tanpa tanggal untuk tepat satu Form pengajuan (BR-FE-28); dipakai banyak periode. |
| **FormPhaseDetail** (**Langkah**) | Step dalam FormPhase — menghubungkan FormAccessControl, PhaseType, urutan, `needs_review`, dan **jenis tenggat + siklus** (`deadline_type`, `deadline_cycle`). Tidak menunjuk tanggal: tanggalnya dibaca dari kalender periode pengajuan (BR-FE-26). `submit` tanpa siklus = langkah pengajuan, `submit` siklus n = laporan Monev n, `output` = Upload Luaran. |
| **FormAccessControl**      | Aturan akses sebuah Form: kombinasi `permission` (string via Spatie) + `organization_id`.                                                                                                                                                                                                              |
| **SubmissionPeriod**       | Periode pengajuan yang aktif. Memegang satu **kalender periode** untuk semua fase yang ditautkannya.                                                                                                                                                                                                    |
| **Kalender periode** (**Jadwal**) | Himpunan SubmissionDate milik satu periode, satu per pasangan (jenis tenggat, siklus) (BR-FE-25). Setiap gerbang waktu membaca tanggalnya di sini lewat `SubmissionPeriod::deadlineFor()`. Jadwal berbeda = periode berbeda. |
| **Angkatan**               | Pasangan Periode × Fase: pengajuan-pengajuan pada satu fase di satu periode. Tidak punya tabel sendiri. Angkatan lama tetap memakai kalender periodenya walaupun fase yang sama ditautkan ke periode baru. |
| **SubmissionDate**         | Satu tanggal di kalender periode. Dibaca oleh setiap langkah yang menyebut jenis + siklusnya; langkah dengan aktor berbeda membaca jenis tenggat berbeda (BR-FE-17). Setiap tanggal punya **Jenis Tenggat** dan **Siklus** (BR-FE-25); labelnya diturunkan dari keduanya. |
| **Jenis Tenggat** (`DeadlineType`) | Tahap yang dibatasi sebuah SubmissionDate: `submit` "Batas Submit" (pengisi mengirim), `evaluation` "Batas Evaluasi" (reviewer menilai), `revision` "Batas Revisi" (pengisi merevisi), `output` "Batas Akhir" (Upload Luaran). Langkah fase hanya menyebut `submit` atau `output`. Tetap, bukan dikonfigurasi Operator. |
| **Siklus**                 | Putaran yang dibatasi sebuah SubmissionDate: kosong untuk pengajuan utama, `n` untuk Monev n. Batas Submit siklus n dibaca "Batas Laporan Monev n". |
| **FormSubmission**         | Entitas submission universal. `parent_submission_id` nullable — diisi untuk child submissions (laporan monev, kelengkapan).                                                                                                                                                                            |
| **FormFieldResponse**      | Jawaban scalar untuk satu FormField.                                                                                                                                                                                                                                                                   |
| **Child Submission**       | FormSubmission dengan `parent_submission_id` terisi — hanya untuk laporan monev dan kelengkapan berkas. Research Output tidak menggunakan mekanisme ini.                                                                                                                                               |
| **RepeatableField**        | Konsep UI: field yang bisa diisi lebih dari satu entri. Data dikirim ke extension tables — bukan `form_field_responses`.                                                                                                                                                                               |
| **FormSubmissionOverride** | Record yang dibuat Operator untuk bypass hard deadline pada satu submission + satu FormPhaseDetail. Researcher bisa lihat status override (active/expired) tapi tidak bisa lihat `reason`.                                                                                                             |
| **Optimistic Locking**     | Mekanisme mencegah concurrent edit — saat save, sistem cek `updated_at` di DB vs nilai di request. Kalau berbeda, ada yang sudah edit duluan dan request ditolak dengan pesan yang jelas.                                                                                                              |
| **ReviewEvaluationForm**   | Form Evaluasi: pustaka berversi, dipasang ke langkah `needs_review` lewat StepEvaluationForm. Judul dan deskripsinya nama pustaka (tak berversi); isinya (field, opsi, wajib, aturan validasi) berversi. |
| **Versi Form Evaluasi**    | `ReviewEvaluationFormVersion`: isi Form Evaluasi yang diterbitkan, bernomor "Versi n" mulai 1. Tidak pernah berubah sesudah terbit; jawaban reviewer menunjuk versi yang diisinya. |
| **Pemakuan versi**         | `EvaluationFormPin`: versi yang dipakai satu angkatan × langkah × Form Evaluasi, dipaku saat draf penilaian pertama di sana tersimpan (BR-REV-18). |
| **Versi Struktur Fase**    | `FormPhaseVersion`: langkah sebuah tahap beserta urutan, Form + aturan akses, jenis/siklus tenggat, `needs_review`, dan Form Evaluasi terpasang, beku sesudah terbit dan bernomor "Versi n" (BR-FE-32). |
| **Pemakuan struktur**      | `PhaseVersionPin`: versi struktur yang dipakai satu angkatan (Periode × Tahap), dipaku saat pengajuan pertama angkatan itu dibuat (BR-FE-32). |
| **Pemasangan**            | `StepEvaluationForm`: Form Evaluasi pustaka yang dipakai satu langkah `needs_review`, dengan wajib/aktif/urutannya sendiri (BR-FE-30). Melepas = menghapus pemasangan; menonaktifkan = berhenti menawarkannya untuk pekerjaan baru. |
| **Draf**                   | `ConfigurationDraft`: isi konfigurasi yang sedang disunting dan belum berlaku — isi Form Evaluasi (BR-FE-31), struktur Fase (BR-FE-33), atau field Form (BR-FE-34). Satu per objek; yang berjalan tetap memakai terbitan terakhir. |
| **Terbitkan**              | Mengubah draf menjadi versi bernomor berikutnya yang beku, lalu menghapus drafnya (BR-FE-31, BR-FE-33). Untuk field Form (tidak berversi): menulis draf ke field hidup saat itu juga (BR-FE-34). |
| **Rekaman teks**           | `form_submissions.submitted_field_labels`: teks pertanyaan dan label opsi yang terlihat pengisi saat submit atau kirim ulang (BR-FE-35). Dibaca selama pengajuan tidak bisa disunting pengisinya; draf dan revisi memakai teks terkini. |
| **Kesiapan angkatan**     | Hasil tiga cek satu angkatan terhadap versi strukturnya: tanggal, urutan tanggal, Form Evaluasi wajib (BR-FE-36). Isu langkah Pengajuan dan urutan tanggal memblokir pengajuan baru; isu langkah sesudahnya hanya peringatan. |
| **Angkatan berjalan**      | Angkatan yang sudah punya pemakuan struktur — pengajuan pertamanya sudah dibuat (BR-FE-32). Tidak pernah diblokir cek kesiapan (BR-FE-36). |
| **ReviewFormResponse**     | Respons reviewer. Status: `draft` → `submitted` → `locked`.                                                                                                                                                                                                                                            |
| **ReviewSummary**          | Ringkasan review per reviewer untuk sebuah submission. Titik awal threaded discussion. Status hanya `open` atau `resolved` — `open` berarti ada permintaan revisi yang belum selesai. Tidak ada status `closed`.                                                                                        |
| **ReviewComment**          | Satu komentar dalam thread ReviewSummary. Bisa reply via `parent_comment_id`.                                                                                                                                                                                                                          |
| **SubmissionReviewer**     | Pivot antara FormSubmission dan Reviewer. Satu record per reviewer per siklus (pengajuan atau monev).                                                                                                                                                                                                  |

---

## Submission (SIMPAS-specific)

| Term                        | Definisi                                                                                                                                                                                                                         |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Submission**              | Sebuah `FormSubmission` (form pengajuan utama) beserta seluruh extension tables-nya.                                                                                                                                             |
| **Submission Wizard**       | Alur pembuatan pengajuan tujuh langkah: Pilih Form & Periode → Informasi Dasar → Skema & TRL → Anggota Penelitian → Rencana Anggaran → Upload Berkas → Review & Submit. Baris `form_submissions` dibuat di **langkah 1** agar Temporal Field Binding punya jangkar yang benar. |
| **last_completed_step**     | Kolom di `form_submissions` yang menyimpan kemajuan wizard. Diperlukan karena langkah boleh kosong secara sah (mis. pengajuan tanpa anggota), sehingga "kosong" tidak berarti "belum dikunjungi". |
| **Lead Researcher**         | Researcher yang membuat submission (`submitted_by`). Satu-satunya yang bisa submit dan resubmit.                                                                                                                                 |
| **ResearchMember**          | Dosen lain sebagai co-researcher. Role: `co_investigator` atau `member`.                                                                                                                                                         |
| **Co-Investigator**         | ResearchMember dengan role `co_investigator`. Bisa tambah/edit Research Output. Bisa menerima transfer ownership jika lead researcher dinonaktifkan.                                                                             |
| **Member**                  | ResearchMember dengan role `member`. Read-only untuk semua data submission.                                                                                                                                                      |
| **Submission-Level Access** | Lapis akses kedua di luar FormAccessControl — hanya `submitted_by` dan `research_members` yang bisa lihat data submission spesifik. Operator bypass via `submissions.view-all`. Reviewer bypass via `SubmissionReviewer` record. |
| **Ownership Transfer**      | Aksi Operator memindahkan `submitted_by` dari lead researcher yang dinonaktifkan ke Co-Investigator yang aktif.                                                                                                                  |
| **Withdrawal**              | Pembatalan submission yang sudah APPROVED. Hanya bisa dilakukan Operator via override — bukan self-service oleh Researcher. Semua data historis tetap ada.                                                                       |
| **Target Luaran**           | Luaran yang **dijanjikan** peneliti saat mengajukan — tabel `submission_target_outputs`. Taksonominya sama dengan Research Output, bedanya ini janji dan itu realisasi. Jangan disebut "SubmissionOuter" (nama warisan fork yang menyesatkan). |

---

## Scheme

| Term                | Definisi                                                                                                                                                               |
| ------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Scheme**          | Metadata aturan yang mengatur sebuah kategori pengajuan: `max_budget`, `max_members`, `duration_months`, dan `rules` JSONB untuk rule opsional.                        |
| **scheme.rules**    | JSONB column di tabel `schemes` untuk rule yang opsional dan extensible tanpa migration. Contoh: `min_reviewer_count`, `max_reviewer_workload`, `max_student_members`. |
| **scheme_selector** | FormField type khusus — dropdown scheme yang tersedia di period. Opsional per Form. **Menulis ke `form_submissions.scheme_id`, bukan ke `form_field_responses`** — sekelas dengan RepeatableField. |
| **trl_selector**    | FormField type khusus — dropdown TRL filtered by scheme terpilih. **Menulis ke `form_submissions.trl_id`.** Hanya valid jika ada `scheme_selector` di Form yang sama.   |

---

## Review

| Term                      | Definisi                                                                                                                                                                   |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Reviewer Workload**     | Jumlah submission aktif yang sedang di-review oleh seorang reviewer. Dibatasi oleh `scheme.rules.max_reviewer_workload` — operator mendapat warning jika batas terlampaui. |
| **Reviewer Reassignment** | Aksi Operator mengganti reviewer yang tidak bisa melanjutkan. Record lama di-mark `replaced`, reviewer baru mendapat assignment fresh. History review lama tetap ada.      |
| **Putaran Review**        | Satu babak review milik pengajuan utama, dengan daftar peserta (reviewer) dan tanda Selesai per peserta. Hasil review hanya dihitung saat putaran selesai — yaitu saat peserta terakhir menekan Selesai (BR-REV-16). Riwayat putaran lama tetap terbaca. Laporan monev tidak punya putaran. |
| **Putaran Penilaian**     | Putaran review yang dibuka saat reviewer ditugaskan. Pesertanya semua `SubmissionReviewer` aktif; `not_required` dihitung selesai. |
| **Putaran Pemeriksaan**   | Putaran review yang dibuka saat peneliti mengirim ulang revisi. Pesertanya hanya reviewer aktif yang punya `ReviewSummary` `open`. Lembar penilaian tidak diisi ulang (BR-REV-08). |
| **Selesai menilai**       | Tanda peserta Putaran Penilaian bahwa penilaiannya — lembar dan catatan revisinya — sudah lengkap. Baru bisa ditekan setelah evaluasinya `completed`; tidak bisa dibatalkan. |
| **Selesai memeriksa**     | Tanda peserta Putaran Pemeriksaan bahwa ia sudah memeriksa revisi dan menutup catatan yang dipenuhi. Tidak bisa dibatalkan. |
| **Siap diputuskan**       | Keadaan turunan, bukan kolom: pengajuan utama yang menunggu keputusan, putaran review terakhirnya selesai tanpa NEEDS_REVISION (atau fasenya tanpa review). Hanya pengajuan yang siap diputuskan boleh disetujui atau ditolak Operator (BR-REV-05, BR-REV-07). |

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
                                 → REJECTED (manual via operator)
```

| Status           | Trigger                                                           | Deskripsi                   |
| ---------------- | ----------------------------------------------------------------- | --------------------------- |
| `DRAFT`          | Submission dibuat                                                 | Sedang diisi Researcher     |
| `SUBMITTED`      | Researcher submit                                                 | Menunggu reviewer di-assign |
| `UNDER_REVIEW`   | Operator assign reviewer terakhir                                 | Sedang dinilai              |
| `NEEDS_REVISION` | **Otomatis** — putaran review selesai + ada ReviewSummary open    | Diminta revisi              |
| `RESUBMITTED`    | Researcher resubmit                                               | Kembali ke reviewer         |
| `APPROVED`       | **Manual** — aksi Setujui Operator atas pengajuan siap diputuskan tanpa ReviewSummary open | Disetujui |
| `REJECTED`       | **Manual** — hanya lewat aksi eksplisit Operator, tidak pernah otomatis | Ditolak                |
| `WITHDRAWN`      | Operator override (bukan self-service)                            | Dibatalkan setelah approved |

> **ARCHIVED** bukan status di DB — ini **computed property** di model. Submission dianggap archived jika `is_submitted = false` (masih DRAFT) DAN period-nya sudah tutup (`SubmissionPeriod.is_force_closed = true` ATAU semua `SubmissionDate` sudah terlewat). Tidak ada kolom atau migration tambahan — logic hidup di `FormSubmission::isArchived(): bool`.

> **Can Proceed** bukan kolom di DB — ini **computed property** di model, sejajar
> `isArchived()`. Sebuah `FormPhaseDetail` boleh dilanjutkan kalau detail itu sudah
> disubmit DAN, bila `needs_review = true`, evaluasinya selesai tanpa `ReviewSummary`
> berstatus `open`. Tidak ada aktor yang menyetelnya — ia turunan keadaan (BR-MON-02).

> **Active Submission** adalah submission yang `status`-nya **bukan** `REJECTED` dan
> **bukan** `WITHDRAWN` — enam status sisanya, `APPROVED` termasuk. Sebuah pengajuan
> yang disetujui masih menjadi kegiatan berjalan: laporan monev lahir darinya dan
> skemanya masih memasok `max_budget` serta `duration_months`. "Terminal" pada BR-SM-05
> berbicara soal *mutabilitas record*, bukan soal kegiatannya sudah selesai — dua
> pertanyaan berbeda yang kebetulan bersinggungan di APPROVED.
>
> Predikat ini **sudah dipakai database**: index unik BR-SM-01 memfilter dengan
> `status NOT IN ('rejected', 'withdrawn')` ([ADR 0005](../../adr/0005-scheme-selector-writes-to-column.md),
> `2026_08_17_000003_add_submission_uniqueness_indexes.php`). BR-SCH-01 memakai definisi
> yang sama persis — satu predikat, bukan dua yang bisa menyimpang.

---

## Period Lifecycle

| Term              | Definisi                                                                                                                              |
| ----------------- | ------------------------------------------------------------------------------------------------------------------------------------- |
| **Close Early**   | Operator menutup period sebelum semua SubmissionDate terlewat via flag `is_force_closed`.                                             |
| **Dibuka**        | Period menerima pengajuan baru: Batas Submit siklus pengajuan utamanya belum lewat dan tidak `is_force_closed` (BR-FE-01). Tanggal lain yang masih di depan tidak membuatnya Dibuka. |
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
| `review_summaries.status = 'closed'` | `open` atau `resolved`                        | `closed` tidak pernah ada di domain |
| `submission_rules` (tabel)   | `scheme.rules` JSONB                                  | Tabel ini di-drop          |
| `ReviewerRole` (tabel/class) | Spatie role `reviewer_internal` / `reviewer_external` | Tabel sudah di-drop        |
| `nilai` (field)              | `score`                                               | English untuk field names  |
| `status` tanpa konteks       | `SubmissionStatus`                                    | Harus ada konteks spesifik |
| `SubmissionOuter` / `submission_outers` | `SubmissionTargetOutput` / `submission_target_outputs` | Nama lama menyesatkan — "outer" dibaca sebagai data anggaran |
