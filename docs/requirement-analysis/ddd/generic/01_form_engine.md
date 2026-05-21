# BC: Form Engine

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.2  
**Status:** Draft

---

## Responsibility

Platform inti yang diwarisi dari sim-kerjasama-itk. Menyediakan infrastruktur untuk mendefinisikan form, mengatur workflow berbasis fase, mengontrol akses, dan menyimpan respons. Semua bounded context lain **dibangun di atas** Form Engine — bukan menggantikannya.

Tidak ada business logic SIMPAS di sini. Form Engine generik dan bisa dipakai untuk sistem apapun.

---

## Activity Diagram

### Alur Konfigurasi (Admin/Operator)

```mermaid
flowchart TD
    START([Admin buka Form Builder]) --> A

    subgraph BUILD["Build Form"]
        A[Buat Form<br/>title, description, form_type]
        A --> B[Tambah FormFields<br/>label, type, required, order, config]
        B --> C[Set FormAccessControl<br/>permission + organization]
    end

    subgraph PERIOD["Setup Period"]
        J[Buat SubmissionPeriod<br/>name]
        J --> K[Tambah SubmissionDates<br/>label + datetime per deadline]
    end

    subgraph PHASE["Build Workflow Phase"]
        D[Buat FormPhase<br/>title, description]
        D --> E[Tambah FormPhaseDetails<br/>link FormAccessControl + SubmissionDate<br/>set order, needs_review]
        E --> F{Fase ini butuh<br/>evaluasi reviewer?}
        F -->|Ya| G[Tambah ReviewEvaluationForm<br/>ke FormPhaseDetail]
        G --> H[Tambah ReviewFormFields]
        F -->|Tidak| I[Selesai]
        H --> I
    end

    C --> J --> D
    I --> L[Link FormPhase ke Period<br/>via SubmissionPeriodPhase]
```

### Alur Submit FormSubmission

```mermaid
flowchart TD
    START([User buka Form]) --> A[Load FormFields<br/>based on FormAccessControl]
    A --> B[Isi field-field<br/>scalar values]
    B --> C[Save Draft<br/>FormFieldResponse per field]
    C --> D{Semua required<br/>field terisi?}
    D -->|Belum| B
    D -->|Ya| E[Submit]
    E --> F[is_submitted = true]
    F --> G{needs_review?}
    G -->|Tidak| H[status → APPROVED<br/>otomatis]
    G -->|Ya| I[status → PENDING<br/>menunggu reviewer]
```

### Alur Temporal Access Check

Setiap kali user mencoba mengakses atau mengedit sebuah FormPhaseDetail, tiga kondisi ini dicek secara berurutan.

```mermaid
flowchart TD
    START([User request akses<br/>FormPhaseDetail]) --> L1

    subgraph L1["Lapis 1 — Permission + Org"]
        A{Permission user match<br/>FormAccessControl?<br/>DAN org di subtree?}
        A -->|Tidak| DENY1[❌ 403 Forbidden]
        A -->|Ya| L2
    end

    subgraph L2["Lapis 2 — Workflow Gate"]
        B{can_proceed dari<br/>Detail sebelumnya<br/>= true?}
        B -->|Tidak| DENY2[❌ Blocked<br/>tampilkan status menunggu]
        B -->|Ya| L3
    end

    subgraph L3["Lapis 3 — Time Gate"]
        C{now ≤ SubmissionDate<br/>.datetime?}
        C -->|Ya — dalam deadline| ALLOW[✅ Akses diberikan]
        C -->|Tidak — lewat deadline| D{Ada active<br/>FormSubmissionOverride<br/>untuk submission ini?}
        D -->|Ya| ALLOW
        D -->|Tidak| DENY3[❌ 403 Deadline Passed<br/>tampilkan pesan + kontak operator]
    end
```

### Alur Override oleh Operator

```mermaid
flowchart TD
    START([Researcher hubungi Operator<br/>minta perpanjangan akses]) --> A
    A[Operator buka<br/>Submission Management] --> B[Pilih submission<br/>yang dimaksud]
    B --> C[Pilih FormPhaseDetail<br/>yang akan dibuka]
    C --> D[Isi alasan override<br/>dan expires_at opsional]
    D --> E[Simpan FormSubmissionOverride]
    E --> F[Researcher sekarang<br/>bisa akses kembali]
    F --> G{expires_at<br/>diisi?}
    G -->|Ya| H[Akses otomatis<br/>dicabut saat expires_at]
    G -->|Tidak| I[Akses terbuka<br/>sampai operator cabut manual]
```

### Alur Access Check (Permission)

```mermaid
flowchart TD
    START([User request akses Form]) --> A[Ambil semua permission<br/>user via Spatie<br/>incl. dari role maupun direct]
    A --> B[Query FormAccessControl<br/>WHERE permission IN user_permissions<br/>AND organization_id IN org_subtree]
    B --> C{Ada baris<br/>yang match?}
    C -->|Tidak| D[❌ Akses ditolak]
    C -->|Ya| E[✅ Lanjut ke lapis berikutnya]

    subgraph SPATIE["Spatie Permission — dua jalur"]
        F[User → Role → Permission<br/>inherited dari role]
        G[User → Permission<br/>custom / direct assign]
        F --> H[getAllPermissions]
        G --> H
    end
```

---

## Aggregates

```mermaid
classDiagram
    class Form {
        +FormId id
        +string title
        +string description
        +FormTypeId form_type_id
        +bool is_active
    }

    class FormField {
        +FormFieldId id
        +FormId form_id
        +string label
        +bool is_required
        +FieldTypeId field_type_id
        +int order
        +Json config
    }

    class FormFieldOption {
        +FormFieldOptionId id
        +FormFieldId form_field_id
        +string label
    }

    class FormAccessControl {
        +FormAccessControlId id
        +FormId form_id
        +string permission
        +OrganizationId organization_id
    }

    class FormPhase {
        +FormPhaseId id
        +string title
        +string description
        +bool is_active
    }

    class FormPhaseDetail {
        +FormPhaseDetailId id
        +FormPhaseId form_phase_id
        +FormAccessControlId form_access_control_id
        +SubmissionDateId submission_date_id
        +PhaseTypeId phase_type_id
        +int order
        +bool needs_review
        +isWithinDeadline() bool
    }

    class SubmissionPeriod {
        +SubmissionPeriodId id
        +string name
    }

    class SubmissionDate {
        +SubmissionDateId id
        +SubmissionPeriodId period_id
        +string label
        +DateTime datetime
    }

    class FormSubmission {
        +FormSubmissionId id
        +FormId form_id
        +UserId submitted_by
        +FormSubmissionId parent_submission_id
        +bool is_submitted
        +SubmissionStatus status
        +submit()
        +canProceed() bool
    }

    class FormFieldResponse {
        +FormFieldResponseId id
        +FormSubmissionId form_submission_id
        +FormFieldId form_field_id
        +string value
    }

    class FormSubmissionOverride {
        +FormSubmissionOverrideId id
        +FormSubmissionId form_submission_id
        +FormPhaseDetailId form_phase_detail_id
        +UserId granted_by
        +string reason
        +DateTime expires_at
        +bool is_active
        +isValid() bool
        +revoke()
    }

    class ReviewEvaluationForm {
        +ReviewEvaluationFormId id
        +FormPhaseDetailId form_phase_detail_id
        +string title
        +string description
        +bool is_required
        +bool is_active
        +int order
    }

    class ReviewFormField {
        +ReviewFormFieldId id
        +ReviewEvaluationFormId review_evaluation_form_id
        +FieldTypeId field_type_id
        +string label
        +bool is_required
        +int order
        +Json validation_rules
    }

    class ReviewerFormAssignment {
        +ReviewerFormAssignmentId id
        +SubmissionReviewerId submission_reviewer_id
        +ReviewEvaluationFormId review_evaluation_form_id
        +bool is_required
        +DateTime assigned_at
        +DateTime due_date
    }

    class ReviewFormResponse {
        +ReviewFormResponseId id
        +ReviewerFormAssignmentId reviewer_form_assignment_id
        +string status
        +DateTime submitted_at
        +string final_notes
    }

    class ReviewFormFieldResponse {
        +ReviewFormFieldResponseId id
        +ReviewFormResponseId review_form_response_id
        +ReviewFormFieldId review_form_field_id
        +string value
    }

    class ReviewSummary {
        +ReviewSummaryId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string status
        +string summary_notes
    }

    class ReviewComment {
        +ReviewCommentId id
        +ReviewSummaryId review_summary_id
        +ReviewCommentId parent_comment_id
        +UserId user_id
        +ReviewerId reviewer_id
        +string comment_text
    }

    class SubmissionReviewer {
        +SubmissionReviewerId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string evaluation_status
    }

    Form "1" --> "0..*" FormField : has
    Form "1" --> "0..*" FormAccessControl : controlled_by
    FormField "1" --> "0..*" FormFieldOption : has
    FormPhase "1" --> "1..*" FormPhaseDetail : has
    FormPhaseDetail "*" --> "1" SubmissionDate : deadline
    FormPhaseDetail "1" --> "0..*" ReviewEvaluationForm : has
    FormPhaseDetail "1" --> "0..*" FormSubmissionOverride : overridden_by
    ReviewEvaluationForm "1" --> "1..*" ReviewFormField : has
    FormSubmission "1" --> "0..*" FormFieldResponse : has
    FormSubmission "1" --> "0..*" SubmissionReviewer : has
    FormSubmission "0..1" --> "0..*" FormSubmission : parent_of
    SubmissionReviewer "1" --> "0..*" ReviewerFormAssignment : has
    ReviewerFormAssignment "1" --> "0..1" ReviewFormResponse : has
    ReviewFormResponse "1" --> "0..*" ReviewFormFieldResponse : has
    FormSubmission "1" --> "0..*" ReviewSummary : has
    ReviewSummary "1" --> "0..*" ReviewComment : has
```

---

## Konsep `FormPhaseDetail.submission_date_id` — Hard Deadline

Setiap `FormPhaseDetail` wajib punya satu `SubmissionDate` sebagai deadline (NOT NULL). Operator set deadline ini saat konfigurasi phase — tidak ada detail tanpa batas waktu.

Satu `SubmissionDate` bisa di-reference oleh banyak `FormPhaseDetail`. Misalnya "Batas Submit Pengajuan" bisa jadi deadline untuk form pengajuan utama sekaligus form upload berkas pendukung.

Setelah `SubmissionDate.datetime` terlewat, akses ke detail tersebut ditutup secara hard — tidak ada pesan warning yang bisa di-bypass. Satu-satunya jalan adalah `FormSubmissionOverride` dari operator.

---

## Konsep `FormSubmissionOverride` — Bypass Deadline

Override dibuat oleh operator untuk satu submission + satu FormPhaseDetail secara spesifik. Tidak mempengaruhi submission lain atau period secara keseluruhan.

```sql
form_submission_overrides
  id
  form_submission_id    FK → form_submissions
  form_phase_detail_id  FK → form_phase_details
  granted_by            FK → users
  reason                text        -- wajib diisi operator
  expires_at            timestamp nullable  -- null = tidak ada batas baru
  is_active             boolean default true
  created_at, updated_at
```

`is_active` bisa diset `false` oleh operator untuk mencabut override sebelum `expires_at`. Semua override tercatat permanen di tabel untuk keperluan audit trail.

---

## Konsep `FormAccessControl` — Permission + Org

`form_access_controls` menyimpan `permission` (string) + `organization_id`. Dua jalur Spatie ter-cover oleh satu query:

```php
$userPermissions = $user->getAllPermissions()->pluck('name');
$userOrgSubtree  = Organization::subtreeIds($user->profile->organization_id);

$canAccess = FormAccessControl::where('form_id', $form->id)
    ->whereIn('permission', $userPermissions)
    ->whereIn('organization_id', $userOrgSubtree)
    ->exists();
```

---

## Konsep `parent_submission_id`

`FormSubmission` bisa punya hierarki. Parent selalu submission pengajuan utama. Child submissions digunakan untuk:

| Child Type      | Digunakan untuk          | Siapa yang isi |
| --------------- | ------------------------ | -------------- |
| Progress Report | Laporan kemajuan monev   | Researcher     |
| Kelengkapan     | Upload dokumen pendukung | Researcher     |
| Research Output | Pelaporan luaran         | Researcher     |

---

## Konsep `RepeatableField`

`FormField` dengan `field_type = 'repeatable'` dan `config` berisi JSON schema sub-fields. **Config adalah UI schema saja** — data dikirim ke extension table, bukan ke `form_field_responses`.

```json
{
    "add_label": "Tambah Anggota",
    "min_entries": 0,
    "max_entries": 5,
    "fields": [
        { "key": "nidn", "label": "NIDN", "type": "text", "required": true },
        {
            "key": "name",
            "label": "Nama Lengkap",
            "type": "text",
            "required": true
        },
        { "key": "role", "label": "Peran", "type": "select", "required": true }
    ]
}
```

---

## Business Rules

| Kode     | Rule                                                                                                                                |
| -------- | ----------------------------------------------------------------------------------------------------------------------------------- |
| BR-FE-01 | `FormSubmission` hanya bisa dibuat selama `SubmissionPeriod` masih aktif                                                            |
| BR-FE-02 | User hanya bisa akses Form jika ada `FormAccessControl` yang match permission user DAN organization subtree user                    |
| BR-FE-03 | `FormFieldResponse` hanya menyimpan scalar values — tidak ada array atau object                                                     |
| BR-FE-04 | Child `FormSubmission` hanya bisa dibuat jika parent sudah berstatus `APPROVED`                                                     |
| BR-FE-05 | `ReviewFormResponse` tidak bisa diedit setelah `status = submitted`                                                                 |
| BR-FE-06 | Reviewer hanya bisa membuat `ReviewSummary` setelah `evaluation_status = completed` atau `not_required`                             |
| BR-FE-07 | `FormPhaseDetail.submission_date_id` NOT NULL — setiap detail wajib punya deadline                                                  |
| BR-FE-08 | Akses ke `FormPhaseDetail` ditolak secara hard jika `now() > SubmissionDate.datetime` dan tidak ada active `FormSubmissionOverride` |
| BR-FE-09 | `FormSubmissionOverride.reason` wajib diisi — tidak boleh kosong                                                                    |
| BR-FE-10 | Override hanya berlaku untuk satu `form_submission_id` + satu `form_phase_detail_id` — tidak bisa bulk override                     |
| BR-FE-11 | `SubmissionDate` yang sama boleh di-reference oleh banyak `FormPhaseDetail` dalam phase yang sama                                   |

---

## Integration Map

| Context           | Arah                     | Keterangan                                                   |
| ----------------- | ------------------------ | ------------------------------------------------------------ |
| Submission        | Form Engine → Downstream | FormSubmission adalah basis Submission SIMPAS                |
| Review            | Form Engine → Downstream | ReviewEvaluationForm, ReviewSummary, ReviewComment           |
| Monev             | Form Engine → Downstream | FormPhase untuk monev stages, child FormSubmission           |
| Research Output   | Form Engine → Downstream | Child FormSubmission untuk output reporting                  |
| Identity & Access | Upstream → Form Engine   | Permission string dan OrganizationId untuk FormAccessControl |
