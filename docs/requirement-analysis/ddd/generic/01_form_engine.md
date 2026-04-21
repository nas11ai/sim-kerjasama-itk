# BC: Form Engine

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.1  
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

    subgraph PHASE["Build Workflow Phase"]
        D[Buat FormPhase<br/>title, description]
        D --> E[Tambah FormPhaseDetails<br/>link FormAccessControl, set order, needs_review]
        E --> F{Fase ini<br/>butuh evaluasi<br/>reviewer?}
        F -->|Ya| G[Tambah ReviewEvaluationForm<br/>ke FormPhaseDetail]
        G --> H[Tambah ReviewFormFields]
        F -->|Tidak| I[Selesai]
        H --> I
    end

    subgraph PERIOD["Setup Period"]
        J[Buat SubmissionPeriod<br/>name]
        J --> K[Tambah SubmissionDates<br/>labeled dates]
        K --> L[Link FormPhases<br/>ke Period via SubmissionPeriodPhase]
    end

    C --> D --> J
    I --> J
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

### Alur Access Check

```mermaid
flowchart TD
    START([User request akses Form]) --> A[Ambil semua permission<br/>user via Spatie<br/>incl. dari role maupun direct]
    A --> B[Query FormAccessControl<br/>WHERE permission IN user_permissions<br/>AND organization_id IN org_subtree]
    B --> C{Ada baris<br/>yang match?}
    C -->|Tidak| D[❌ Akses ditolak]
    C -->|Ya| E[✅ Akses diberikan]

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
        +PhaseTypeId phase_type_id
        +int order
        +bool needs_review
    }

    class SubmissionPeriod {
        +SubmissionPeriodId id
        +string name
    }

    class SubmissionDate {
        +SubmissionDateId id
        +SubmissionPeriodId period_id
        +SubmissionDateLabelId label_id
        +DateTime date
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
    FormPhaseDetail "1" --> "0..*" ReviewEvaluationForm : has
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

## Konsep `FormAccessControl` — Permission + Org

`form_access_controls` tidak lagi menyimpan `role_id`. Yang disimpan adalah **nama permission** (string) dan `organization_id`. Ini memungkinkan dua jalur akses via Spatie yang transparan:

```
User → Role → Permission   (inherited — majority of users)
User → Permission           (direct / custom — edge cases)
```

Keduanya ter-cover oleh satu query yang sama:

```php
$userPermissions = $user->getAllPermissions()->pluck('name');
$userOrgSubtree  = Organization::subtreeIds($user->profile->organization_id);

$canAccess = FormAccessControl::where('form_id', $form->id)
    ->whereIn('permission', $userPermissions)
    ->whereIn('organization_id', $userOrgSubtree)
    ->exists();
```

Operator tidak perlu tahu apakah permission user berasal dari role atau direct assign — access check-nya identik.

---

## Konsep `parent_submission_id`

`FormSubmission` bisa punya hierarki. Parent selalu submission pengajuan utama. Child submissions digunakan untuk:

| Child Type      | Digunakan untuk          | Siapa yang isi |
| --------------- | ------------------------ | -------------- |
| Progress Report | Laporan kemajuan monev   | Researcher     |
| Kelengkapan     | Upload dokumen pendukung | Researcher     |
| Research Output | Pelaporan luaran         | Researcher     |

Satu parent bisa punya banyak child dari tipe yang sama (e.g., banyak laporan per siklus monev).

---

## Konsep `RepeatableField`

`FormField` dengan `field_type = 'repeatable'` dan kolom `config` berisi JSON schema sub-fields:

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

**Penting:** `config` adalah **UI schema saja**. Data tidak masuk ke `form_field_responses`. Saat submit, frontend kirim data repeatable ke endpoint extension table yang sesuai (research_members, budget_line_items, dll).

---

## Business Rules

| Kode     | Rule                                                                                                             |
| -------- | ---------------------------------------------------------------------------------------------------------------- |
| BR-FE-01 | `FormSubmission` hanya bisa dibuat selama `SubmissionPeriod` masih aktif                                         |
| BR-FE-02 | User hanya bisa akses Form jika ada `FormAccessControl` yang match permission user DAN organization subtree user |
| BR-FE-03 | `FormFieldResponse` hanya menyimpan scalar values — tidak ada array atau object                                  |
| BR-FE-04 | Child `FormSubmission` hanya bisa dibuat jika parent sudah berstatus `APPROVED`                                  |
| BR-FE-05 | `ReviewFormResponse` tidak bisa diedit setelah `status = submitted`                                              |
| BR-FE-06 | Reviewer hanya bisa membuat `ReviewSummary` setelah `evaluation_status = completed` atau `not_required`          |

---

## Integration Map

| Context           | Arah                     | Keterangan                                                   |
| ----------------- | ------------------------ | ------------------------------------------------------------ |
| Submission        | Form Engine → Downstream | FormSubmission adalah basis Submission SIMPAS                |
| Review            | Form Engine → Downstream | ReviewEvaluationForm, ReviewSummary, ReviewComment           |
| Monev             | Form Engine → Downstream | FormPhase untuk monev stages, child FormSubmission           |
| Research Output   | Form Engine → Downstream | Child FormSubmission untuk output reporting                  |
| Identity & Access | Upstream → Form Engine   | Permission string dan OrganizationId untuk FormAccessControl |
