# BC: Review

**Klasifikasi:** 🔴 Core Domain  
**Versi:** 2.3  
**Status:** Draft

---

## Responsibility

Mengelola penugasan reviewer, evaluasi kuantitatif, dan diskusi revisi. Approval terjadi **otomatis** oleh sistem ketika kondisi terpenuhi. Rejection adalah satu-satunya transisi yang membutuhkan konfirmasi manual operator — karena berimplikasi besar bagi researcher dan butuh pertimbangan.

---

## Activity Diagram

### Alur Assignment & Evaluasi

```mermaid
flowchart TD
    START([Event: ProposalSubmitted]) --> A

    subgraph OPERATOR["🏢 LPPM Operator"]
        A[Lihat submission status PENDING]
        A --> B[Pilih Reviewer<br/>cek conflict of interest + workload]
        B --> WL{Workload reviewer<br/>≥ max_reviewer_workload?}
        WL -->|Ya| WARN[⚠️ Warning — bisa tetap assign<br/>tapi operator perlu confirm]
        WARN --> C
        WL -->|Tidak| C
        C{Sudah ≥ min_reviewer_count?}
        C -->|Belum| B
        C -->|Ya| D[Buat SubmissionReviewer records]
        D --> E[Auto-assign ReviewerFormAssignments]
        E --> F[Status → UNDER_REVIEW]
    end

    subgraph REVIEWER["🔍 Reviewer"]
        G[Terima notifikasi]
        G --> H[Isi ReviewEvaluationForm]
        H --> I[Submit ReviewFormResponse<br/>evaluation_status → completed]
        I --> J{Perlu catatan<br/>revisi?}
        J -->|Ya| K[Buat ReviewSummary status=open<br/>Tulis ReviewComment]
        J -->|Tidak| L[Buat ReviewSummary status=resolved]
    end

    F --> G

    subgraph AUTO["🔄 Sistem — Auto Check"]
        M{Semua SubmissionReviewer<br/>evaluation_status = completed?}
        M -->|Belum| WAIT([Tunggu])
        M -->|Ya| N{Ada ReviewSummary<br/>status = open?}
        N -->|Ya| REV[Status → NEEDS_REVISION<br/>OTOMATIS]
        N -->|Tidak| APP[Status → APPROVED<br/>OTOMATIS]
    end

    K --> M
    L --> M
    REV --> O([Researcher revisi & resubmit])
    O --> H
    APP --> END_A([Event: ProposalApproved])

    subgraph MANUAL_REJ["🏢 Operator — Manual Reject"]
        P[Operator review hasil evaluasi]
        P --> Q[Operator klik Reject]
        Q --> R[Konfirmasi + isi alasan]
        R --> REJ[Status → REJECTED]
    end
```

### Alur Threaded Discussion (Revisi)

```mermaid
flowchart TD
    START([Reviewer submit evaluasi]) --> A[Buat ReviewSummary]
    A --> B[Tulis ReviewComment<br/>detail catatan revisi]
    B --> C[Researcher baca komentar]
    C --> D[Researcher reply<br/>parent_comment_id = komentar reviewer]
    D --> E{Revisi memuaskan?}
    E -->|Belum| F[Reviewer reply balik]
    F --> D
    E -->|Ya| G[Update ReviewSummary status = resolved]
    G --> H{Semua ReviewSummary<br/>resolved?}
    H -->|Belum| WAIT([Tunggu summary lain])
    H -->|Ya| AUTO([Sistem trigger auto approve])
```

### Reviewer Reassignment

```mermaid
flowchart TD
    START([Reviewer tidak bisa lanjutkan]) --> A[Operator buka<br/>Reviewer Management submission]
    A --> B[Mark SubmissionReviewer lama<br/>sebagai replaced]
    B --> C[Pilih reviewer pengganti<br/>cek conflict of interest]
    C --> D[Buat SubmissionReviewer baru]
    D --> E[Duplicate ReviewerFormAssignments<br/>ke reviewer baru]
    E --> F[History review lama tetap ada]
    F --> G[Notifikasi ke reviewer baru]
```

---

## Aggregates

```mermaid
classDiagram
    class Reviewer {
        +ReviewerId id
        +UserId user_id
        +string reviewer_type
        +DateTime start_date
        +DateTime end_date
        +isActive() bool
        +currentWorkload() int
        +isAvailableFor(submissionId) bool
        note "reviewer_type: internal | external"
    }

    class SubmissionReviewer {
        +SubmissionReviewerId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string evaluation_status
        +string status
        +updateEvaluationStatus()
        note "status: active | replaced"
        note "evaluation_status: pending | completed | not_required"
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
        note "status: draft | submitted | locked"
    }

    class ReviewSummary {
        +ReviewSummaryId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string status
        +string summary_notes
        note "status: open | resolved"
    }

    class ReviewComment {
        +ReviewCommentId id
        +ReviewSummaryId review_summary_id
        +ReviewCommentId parent_comment_id
        +UserId user_id
        +ReviewerId reviewer_id
        +string comment_text
    }

    Reviewer "1" --> "0..*" SubmissionReviewer : assigned_to
    SubmissionReviewer "1" --> "0..*" ReviewerFormAssignment : has
    ReviewerFormAssignment "1" --> "0..1" ReviewFormResponse : has
    SubmissionReviewer "1" --> "0..1" ReviewSummary : produces
    ReviewSummary "1" --> "0..*" ReviewComment : has
    ReviewComment "0..1" --> "0..*" ReviewComment : parent_of
```

---

## Reviewer Roles via Spatie

Tidak ada tabel `reviewer_roles`. Perbedaan dikelola via dua Spatie roles:

| Spatie Role         | Permissions                                                                   | Keterangan                                          |
| ------------------- | ----------------------------------------------------------------------------- | --------------------------------------------------- |
| `reviewer_internal` | `reviewers.evaluate`, `submissions.view-assigned`, `review.view-other-scores` | Bisa lihat skor reviewer lain setelah semua selesai |
| `reviewer_external` | `reviewers.evaluate`, `submissions.view-assigned`                             | Tidak bisa lihat skor reviewer lain                 |

Tabel `reviewers` menyimpan `reviewer_type varchar` (`internal` / `external`) untuk display dan reporting.

---

## Business Rules

| Kode      | Rule                                                                                                                                                          |
| --------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| BR-REV-01 | Jumlah reviewer yang di-assign harus ≥ `scheme.rules.min_reviewer_count`                                                                                      |
| BR-REV-02 | Reviewer tidak bisa di-assign ke submission yang ia menjadi `submitted_by` atau `ResearchMember`-nya (mutual block — juga berlaku sebaliknya saat add member) |
| BR-REV-03 | Reviewer yang sama tidak bisa di-assign dua kali ke submission yang sama dalam satu siklus                                                                    |
| BR-REV-04 | Jika workload reviewer melebihi `scheme.rules.max_reviewer_workload`, operator mendapat warning — bukan hard block                                            |
| BR-REV-05 | Submission di-approve **otomatis** jika: semua `evaluation_status = completed` DAN tidak ada ReviewSummary `open`                                             |
| BR-REV-06 | Submission masuk NEEDS_REVISION **otomatis** jika: semua `evaluation_status = completed` DAN ada ReviewSummary `open`                                         |
| BR-REV-07 | Rejection harus dikonfirmasi manual oleh Operator — tidak bisa otomatis                                                                                       |
| BR-REV-08 | ReviewFormResponse tidak bisa diedit setelah `status = submitted`                                                                                             |
| BR-REV-09 | Reviewer hanya aktif selama rentang `start_date` hingga `end_date`                                                                                            |
| BR-REV-10 | Saat reviewer diganti (reassignment), SubmissionReviewer lama di-mark `replaced` — tidak dihapus                                                              |
| BR-REV-11 | `reviewer_internal` bisa lihat skor reviewer lain setelah semua selesai — `reviewer_external` tidak bisa                                                      |
| BR-REV-12 | Researcher hanya bisa lihat skor evaluasi reviewer setelah submission berstatus APPROVED atau REJECTED — tidak selama proses review berlangsung               |

---

## Domain Events

| Event                      | Trigger                                                | Consumer                        |
| -------------------------- | ------------------------------------------------------ | ------------------------------- |
| `ReviewerAssigned`         | Operator assign reviewer                               | Notification                    |
| `EvaluationSubmitted`      | Reviewer submit ReviewFormResponse                     | (internal: trigger auto check)  |
| `RevisionRequested`        | ReviewSummary dibuat status open → auto NEEDS_REVISION | Submission, Notification        |
| `RevisionResolved`         | ReviewSummary → resolved → trigger auto check          | (internal)                      |
| `ProposalApprovedByReview` | Kondisi auto approve terpenuhi                         | Submission (status → APPROVED)  |
| `ProposalRejectedByReview` | Operator konfirmasi reject                             | Submission (status → REJECTED)  |
| `ReviewerReassigned`       | Operator ganti reviewer                                | Notification, Reporting (audit) |

---

## Integration Map

| Context           | Arah                | Keterangan                                         |
| ----------------- | ------------------- | -------------------------------------------------- |
| Form Engine       | Upstream → Review   | ReviewEvaluationForm, ReviewSummary, ReviewComment |
| Submission        | Upstream → Review   | Consume ProposalSubmitted                          |
| Identity & Access | Upstream → Review   | UserProfileId untuk conflict of interest check     |
| Submission        | Review → Downstream | Publish approved/rejected event ke Submission      |
| Reporting         | Review → Read       | Data evaluasi untuk statistik dan export           |
