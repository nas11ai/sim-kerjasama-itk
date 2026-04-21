# BC: Form Engine

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.1  
**Status:** Draft

---

## Responsibility

Platform inti yang diwarisi dari sim-kerjasama-itk. Menyediakan infrastruktur untuk mendefinisikan form, workflow berbasis fase, access control, dan menyimpan respons. Context lain dibangun **di atas** Form Engine, tidak menggantikannya.

Tidak ada business logic SIMPAS di sini.

---

## Special Field Types

Selain field type standar (`text`, `textarea`, `select`, `radio`, `checkbox`, `file`, `date`, `number`, `url`), Form Engine support field type khusus berikut:

| Field Type        | Deskripsi                                                                                                 | Config                                              |
| ----------------- | --------------------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| `repeatable`      | Field yang bisa diisi banyak entri. **Data dikirim ke extension tables, bukan form_field_responses.**     | `{ fields[], min_entries, max_entries, add_label }` |
| `scheme_selector` | Dropdown scheme yang di-load berdasarkan SubmissionPeriod aktif. Saves `scheme_id` ke `form_submissions`. | `{ filter_by_period, filter_by_submission_type }`   |
| `trl_selector`    | Dropdown TRL yang di-filter berdasarkan scheme yang dipilih.                                              | `{ depends_on: 'scheme' }`                          |

Kalau `scheme_selector` tidak ada di Form → submission tidak butuh scheme.  
Kalau `trl_selector` tidak ada di Form → submission tidak butuh TRL.  
Ini membuat Form Engine tetap general — fitur domain-specific di-inject via field config.

---

## Activity Diagram

### Konfigurasi Form & Phase (Admin/Operator)

```mermaid
flowchart TD
    START([Operator buka Form Builder]) --> A

    subgraph BUILD["Build Form"]
        A[Buat Form — title, form_type]
        A --> B[Tambah FormFields\nlabel, type, required, order, config]
        B --> C[Set FormAccessControl\nrole + organization_id]
    end

    subgraph PHASE["Build FormPhase"]
        D[Buat FormPhase\ntitle — satu per lifecycle penelitian]
        D --> E[Tambah FormPhaseDetails\nFAC, PhaseType, order, needs_review]
        E --> F{Detail ini butuh\nreviewer evaluation?}
        F -->|Ya| G[Tambah ReviewEvaluationForm\nke FormPhaseDetail]
        G --> H[Tambah ReviewFormFields]
        F -->|Tidak| I[Selesai]
        H --> I
    end

    subgraph PERIOD["Setup Period"]
        J[Buat SubmissionPeriod]
        J --> K[Tambah SubmissionDates\nlabeled dates]
        K --> L[Link FormPhase ke Period]
        L --> M[Set SubmissionRules\ne.g. min_reviewer_count=2]
    end

    C --> D --> J
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
        note "config digunakan untuk:\n- repeatable: schema sub-fields\n- scheme_selector: filter options\n- trl_selector: depends_on\n- select/radio: ada di FormFieldOption"
    }

    class FormAccessControl {
        +FormAccessControlId id
        +FormId form_id
        +RoleId role_id
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

    class SubmissionRule {
        +SubmissionRuleId id
        +string label
        +int value
        note "contoh: min_reviewer_count = 2"
    }

    class FormSubmission {
        +FormSubmissionId id
        +FormId form_id
        +UserId submitted_by
        +FormSubmissionId parent_submission_id
        +SchemeId scheme_id
        +bool is_submitted
        +SubmissionStatus status
        +canProceed() bool
    }

    class FormFieldResponse {
        +FormFieldResponseId id
        +FormSubmissionId form_submission_id
        +FormFieldId form_field_id
        +string value
        note "scalar values only\narray/object → extension tables"
    }

    class ReviewEvaluationForm {
        +ReviewEvaluationFormId id
        +FormPhaseDetailId form_phase_detail_id
        +string title
        +bool is_required
        +bool is_active
        +int order
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

    Form "1" --> "0..*" FormField
    Form "1" --> "0..*" FormAccessControl
    FormPhase "1" --> "1..*" FormPhaseDetail
    FormPhaseDetail "1" --> "0..*" ReviewEvaluationForm
    FormSubmission "0..1" --> "0..*" FormSubmission : parent_of
    FormSubmission "1" --> "0..*" FormFieldResponse
    FormSubmission "1" --> "0..*" ReviewSummary
    ReviewSummary "1" --> "0..*" ReviewComment
    ReviewComment "0..1" --> "0..*" ReviewComment : parent_of
```

---

## Business Rules

| Kode     | Rule                                                                                                  |
| -------- | ----------------------------------------------------------------------------------------------------- |
| BR-FE-01 | FormSubmission hanya bisa dibuat selama SubmissionPeriod masih aktif                                  |
| BR-FE-02 | User hanya bisa submit Form yang ia punya akses via FormAccessControl (role + org subtree match)      |
| BR-FE-03 | `FormFieldResponse` hanya untuk scalar values — repeatable & structured data ke extension tables      |
| BR-FE-04 | Child FormSubmission hanya bisa dibuat jika parent sudah `APPROVED` atau `canProceed() = true`        |
| BR-FE-05 | ReviewFormResponse tidak bisa diedit setelah `status = submitted`                                     |
| BR-FE-06 | Reviewer hanya bisa membuat ReviewSummary setelah `evaluation_status = completed` atau `not_required` |
| BR-FE-07 | `scheme_id` di FormSubmission wajib diisi hanya jika Form punya field bertipe `scheme_selector`       |
