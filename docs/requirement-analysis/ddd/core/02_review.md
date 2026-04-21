# BC: Review

**Klasifikasi:** 🔴 Core Domain  
**Versi:** 2.1  
**Status:** Draft

---

## Responsibility

Mengelola penugasan reviewer, evaluasi kuantitatif, dan diskusi revisi. Tidak ada tabel `ReviewerRole` — role reviewer dikelola sepenuhnya via Spatie Permission (`reviewer_internal`, `reviewer_external`). Jumlah minimum reviewer dikonfigurasi via `submission_rules`, bukan hardcoded.

---

## Activity Diagram

### Alur Assignment & Evaluasi

```mermaid
flowchart TD
    START([Event: ProposalSubmitted]) --> A

    subgraph OPERATOR["🏢 LPPM Operator"]
        A[Lihat submission\nberstatus PENDING]
        A --> B[Pilih Reviewer\ncek conflict of interest]
        B --> C{Jumlah reviewer\n≥ min_reviewer_count\ndari submission_rules?}
        C -->|Belum| B
        C -->|Ya| D[Buat SubmissionReviewer records]
        D --> E[Auto-assign\nReviewerFormAssignments]
        E --> F[status → UNDER_REVIEW]
    end

    subgraph REVIEWER["🔍 Reviewer"]
        G[Terima notifikasi]
        G --> H[Isi ReviewEvaluationForm]
        H --> I[Submit ReviewFormResponse]
        I --> J[evaluation_status → completed]
        J --> K{Perlu revisi?}
        K -->|Ya| L[Buat ReviewSummary\nstatus = open]
        L --> M[Tulis ReviewComment\ndetail revisi]
        K -->|Tidak| N[Buat ReviewSummary\nstatus = resolved]
    end

    F --> G
    M --> O([Researcher revisi\n& resubmit])
    O --> H

    subgraph SYSTEM["🔄 System"]
        P{Semua SubmissionReviewer\nevaluation_status = completed?}
        P -->|Ya| Q{Ada ReviewSummary\nstatus = open?}
        Q -->|Ya| R[status → NEEDS_REVISION]
        Q -->|Tidak| S[status → APPROVED\natau REJECTED]
    end

    N --> P
    R --> O
```

### Threaded Discussion

```mermaid
flowchart TD
    START([Reviewer submit evaluation]) --> A[Buat ReviewSummary]
    A --> B[Tulis ReviewComment\ncatatan revisi]
    B --> C[Researcher reply\nparent_comment_id = komentar reviewer]
    C --> D[Reviewer reply balik\njika perlu]
    D --> E{Revisi memuaskan?}
    E -->|Belum| D
    E -->|Ya| F[Update ReviewSummary\nstatus = resolved]
```

---

## Reviewer Roles via Spatie

Tidak ada tabel `reviewer_roles`. Perbedaan internal vs eksternal dikelola via dua Spatie roles:

| Spatie Role | Permission | Keterangan |
|---|---|---|
| `reviewer_internal` | `reviewers.evaluate`, `submissions.view-assigned`, `review.view-other-scores` | Bisa lihat skor reviewer lain setelah selesai |
| `reviewer_external` | `reviewers.evaluate`, `submissions.view-assigned` | Tidak bisa lihat skor reviewer lain |

`FormAccessControl` bisa reference salah satu role — memungkinkan form evaluasi yang berbeda untuk reviewer internal vs eksternal.

Tabel `reviewers` menyimpan `reviewer_type varchar` (`internal`/`external`) untuk keperluan display dan reporting, tanpa FK ke tabel lain:

```sql
reviewers
  id
  user_id           FK → users
  reviewer_type     varchar   -- 'internal' | 'external'
  start_date        datetime
  end_date          datetime nullable
```

---

## Configurable Min Reviewer

Tidak hardcoded. Diambil dari `submission_rules` yang sudah ada di schema sim-kerjasama:

```sql
-- submission_rules linked ke SubmissionPeriod
label = 'min_reviewer_count', value = 2
```

Admin ubah value-nya di admin panel, langsung berlaku untuk period tersebut. Berbeda period bisa punya min_reviewer berbeda.

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
        +isAvailableFor(submissionId) bool
    }

    class SubmissionReviewer {
        +SubmissionReviewerId id
        +FormSubmissionId form_submission_id
        +ReviewerId reviewer_id
        +string evaluation_status
        +updateEvaluationStatus()
        +canCreateDiscussionThreads() bool
    }

    class ReviewerFormAssignment {
        +ReviewerFormAssignmentId id
        +SubmissionReviewerId submission_reviewer_id
        +ReviewEvaluationFormId review_evaluation_form_id
        +bool is_required
        +DateTime assigned_at
        +DateTime due_date
        +isCompleted() bool
        +isOverdue() bool
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

## Business Rules

| Kode | Rule |
|---|---|
| BR-REV-01 | Jumlah reviewer yang di-assign harus ≥ `submission_rules.min_reviewer_count` untuk period tersebut |
| BR-REV-02 | Reviewer tidak bisa di-assign ke submission yang ia menjadi `submitted_by` atau `ResearchMember`-nya |
| BR-REV-03 | Reviewer yang sama tidak bisa di-assign dua kali ke submission yang sama |
| BR-REV-04 | Reviewer hanya bisa membuat ReviewSummary setelah `evaluation_status = completed` atau `not_required` |
| BR-REV-05 | Submission bisa di-approve hanya jika semua SubmissionReviewer `evaluation_status = completed` DAN tidak ada ReviewSummary berstatus `open` |
| BR-REV-06 | ReviewFormResponse tidak bisa diedit setelah `status = submitted` |
| BR-REV-07 | Reviewer `reviewer_internal` bisa lihat skor reviewer lain setelah semua selesai — `reviewer_external` tidak bisa |

---

## Domain Events

| Event | Trigger | Consumer |
|---|---|---|
| `ReviewerAssigned` | Operator assign reviewer | Notification |
| `EvaluationSubmitted` | Reviewer submit ReviewFormResponse | (internal: cek semua done) |
| `RevisionRequested` | ReviewSummary dibuat status open | Submission (status → NEEDS_REVISION), Notification |
| `RevisionResolved` | ReviewSummary → resolved | (internal: cek semua resolved) |
| `ProposalApprovedByReview` | Semua reviewer done + semua resolved | Submission (status → APPROVED) |
| `ProposalRejectedByReview` | Keputusan penolakan | Submission (status → REJECTED) |
