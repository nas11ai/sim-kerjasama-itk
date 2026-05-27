# BC: Form Engine

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.3  
**Status:** Draft

---

## Responsibility

Platform inti yang diwarisi dari sim-kerjasama-itk. Generik dan bisa dipakai untuk sistem apapun — tidak ada business logic SIMPAS di sini. Semua BC lain dibangun di atas Form Engine.

---

## Activity Diagram

### Alur Konfigurasi Form

```mermaid
flowchart TD
    START([Admin buka Form Builder]) --> A[Buat Form]
    A --> B[Tambah FormFields<br/>label, type, required, order, config<br/>required_since nullable]
    B --> C[Set FormAccessControl<br/>permission + organization]
    C --> D[Buat FormPhase]
    D --> E[Tambah FormPhaseDetails<br/>link FormAccessControl + SubmissionDate<br/>set order, needs_review]
    E --> F{Fase butuh<br/>evaluasi reviewer?}
    F -->|Ya| G[Tambah ReviewEvaluationForm<br/>ke FormPhaseDetail]
    G --> H[Tambah ReviewFormFields]
    F -->|Tidak| I[Link FormPhase ke Period]
    H --> I
```

### Temporal Field Binding — Alur Validasi Submit

```mermaid
flowchart TD
    START([Researcher klik Submit]) --> A[Load semua FormFields<br/>untuk Form ini]
    A --> B{Untuk setiap field<br/>yang is_required = true}
    B --> C{field.created_at<br/>≤ submission.created_at?}
    C -->|Tidak — field baru ditambah<br/>setelah submission dibuat| SKIP[Skip — opsional<br/>untuk submission ini]
    C -->|Ya| D{field.required_since<br/>IS NULL<br/>ATAU<br/>required_since ≤ submission.created_at?}
    D -->|Tidak — field baru jadi required<br/>setelah submission dibuat| SKIP
    D -->|Ya| ENFORCE[Field ini wajib diisi<br/>validasi sebagai required]
    ENFORCE --> E{Field terisi?}
    E -->|Ya| NEXT[Lanjut ke field berikutnya]
    E -->|Tidak| ERR[❌ Validation error]
```

### Alur Override Deadline

```mermaid
flowchart TD
    START([User request akses FormPhaseDetail<br/>setelah deadline]) --> A{now ≤<br/>SubmissionDate.datetime?}
    A -->|Ya| ALLOW[✅ Akses diberikan]
    A -->|Tidak| B{Ada active<br/>FormSubmissionOverride?}
    B -->|Ya| C{Override masih valid?<br/>is_active = true<br/>DAN expires_at belum lewat}
    C -->|Ya| ALLOW
    C -->|Tidak| DENY
    B -->|Tidak| DENY[❌ 403<br/>Tampilkan pesan + info kontak operator]

    subgraph RESEARCHER_VIEW["Yang Researcher Lihat"]
        V1[Status override: Active / Expired]
        V2[❌ Reason — tidak ditampilkan ke researcher]
    end
```

### Alur Perubahan Form dengan Active Drafts

```mermaid
flowchart TD
    START([Admin ubah FormField<br/>is_required atau tambah field baru]) --> A[Simpan perubahan]
    A --> B{Ada FormSubmission<br/>status DRAFT untuk form ini?}
    B -->|Tidak| END([Selesai])
    B -->|Ya| C[Sistem tampilkan<br/>warning + jumlah draft terdampak]
    C --> D[Admin pilih:<br/>Kirim notifikasi atau skip]
    D -->|Kirim| E[Dispatch notifikasi<br/>ke semua researcher terdampak]
    D -->|Skip| END
    E --> END
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
        +DateTime created_at
        +DateTime required_since
        +DateTime deleted_at
        +isRequiredFor(FormSubmission) bool
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
        +isAccessibleFor(FormSubmission) bool
    }

    class SubmissionPeriod {
        +SubmissionPeriodId id
        +string name
        +bool is_force_closed
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
        +DateTime created_at
        +DateTime updated_at
        +submit()
        +canProceed() bool
        +isAccessibleBy(User) bool
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
        +string comment_text
    }

    class SubmissionReviewer {
        +SubmissionReviewerId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string evaluation_status
        +string status
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
    FormSubmission "1" --> "0..*" ReviewSummary : has
    ReviewSummary "1" --> "0..*" ReviewComment : has
```

---

## Temporal Field Binding

Logika validasi field saat submit:

```php
public function isRequiredFor(FormSubmission $submission): bool
{
    // Field harus sudah ada sebelum submission dibuat
    if ($this->created_at > $submission->created_at) {
        return false;
    }

    // Field harus sudah required sebelum submission dibuat
    if ($this->required_since !== null && $this->required_since > $submission->created_at) {
        return false;
    }

    return $this->is_required;
}
```

Ketika operator mengubah `is_required` dari false ke true, sistem otomatis set `required_since = now()`. Ketika field dibuat dengan `is_required = true`, `required_since` diset sama dengan `created_at`.

---

## FormSubmissionOverride — Visibility ke Researcher

Researcher bisa melihat **status** override (aktif atau sudah expired) di UI — tampil sebagai badge "Akses Diperpanjang oleh Operator". Researcher **tidak bisa melihat** `reason` — itu hanya untuk Operator dan Admin.

---

## Optimistic Locking

Semua save operation menyertakan `updated_at` check:

```php
// Di controller sebelum save
$submission = FormSubmission::lockForUpdate()->find($id);
if ($submission->updated_at->ne($request->last_updated_at)) {
    return response()->json([
        'message' => 'Data sudah diubah oleh pengguna lain. Muat ulang halaman.'
    ], 409);
}
```

---

## Business Rules

| Kode     | Rule                                                                                                                      |
| -------- | ------------------------------------------------------------------------------------------------------------------------- |
| BR-FE-01 | FormSubmission hanya bisa dibuat selama SubmissionPeriod aktif dan tidak `is_force_closed`                                |
| BR-FE-02 | User hanya bisa akses Form jika ada FormAccessControl yang match permission user DAN org subtree user                     |
| BR-FE-03 | FormFieldResponse hanya menyimpan scalar values                                                                           |
| BR-FE-04 | Child FormSubmission hanya bisa dibuat jika parent sudah APPROVED                                                         |
| BR-FE-05 | ReviewFormResponse tidak bisa diedit setelah `status = submitted`                                                         |
| BR-FE-06 | Reviewer hanya bisa membuat ReviewSummary setelah `evaluation_status = completed` atau `not_required`                     |
| BR-FE-07 | FormPhaseDetail.submission_date_id NOT NULL — setiap detail wajib punya deadline                                          |
| BR-FE-08 | Akses ke FormPhaseDetail di-hard-block jika deadline lewat dan tidak ada active FormSubmissionOverride                    |
| BR-FE-09 | FormSubmissionOverride.reason wajib diisi — tidak boleh kosong                                                            |
| BR-FE-10 | Override hanya berlaku untuk satu form_submission_id + satu form_phase_detail_id                                          |
| BR-FE-11 | Field hanya di-enforce is_required jika `created_at ≤ submission.created_at` DAN `required_since ≤ submission.created_at` |
| BR-FE-12 | FormField menggunakan soft delete (`deleted_at`) — tidak boleh hard delete                                                |
| BR-FE-13 | Saat operator ubah Form yang punya active drafts, sistem prompt untuk kirim notifikasi ke researcher terdampak            |
| BR-FE-14 | Concurrent edit dicegah via optimistic locking — `updated_at` di request harus match dengan yang di DB                    |
| BR-FE-15 | Expired FormPhaseDetail tetap tampil di UI sebagai read-only — user bisa lihat apa yang sudah disubmit                    |

---

## Integration Map

| Context           | Arah                     | Keterangan                                                   |
| ----------------- | ------------------------ | ------------------------------------------------------------ |
| Submission        | Form Engine → Downstream | FormSubmission sebagai basis Submission SIMPAS               |
| Review            | Form Engine → Downstream | ReviewEvaluationForm, ReviewSummary, ReviewComment           |
| Monev             | Form Engine → Downstream | FormPhase untuk monev stages, child FormSubmission           |
| Identity & Access | Upstream → Form Engine   | Permission string dan OrganizationId untuk FormAccessControl |
