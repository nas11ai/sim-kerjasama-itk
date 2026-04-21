# BC: Notification

**Klasifikasi:** 🟢 Generic Domain  
**Versi:** 2.0  
**Status:** Draft

---

## Responsibility

Consumer dari domain events semua context. Tidak punya business logic — hanya translate event ke email. Implementasi: **Laravel Notification** via queue, channel `mail`.

---

## Activity Diagram

```mermaid
flowchart TD
    START([Domain Event Published]) --> A[Event masuk Queue]
    A --> B[NotificationListener<br/>menangkap event]
    B --> C[Resolve recipients<br/>berdasarkan event type]
    C --> D[Build email content<br/>dari Blade template]
    D --> E[Dispatch ke Mail Queue]
    E --> F{Berhasil?}
    F -->|Ya| G[Log: success]
    F -->|Tidak| H[Log: failed<br/>tidak retry otomatis]
```

---

## Events yang Dikonsumsi

| Source            | Event                      | Recipients                         |
| ----------------- | -------------------------- | ---------------------------------- |
| Submission        | `ProposalSubmitted`        | LPPM Operator                      |
| Submission        | `ProposalApproved`         | Lead Researcher + Research Members |
| Submission        | `ProposalRejected`         | Lead Researcher                    |
| Review            | `ReviewerAssigned`         | Reviewer                           |
| Review            | `RevisionRequested`        | Lead Researcher                    |
| Monev             | `MonevStageOpened`         | Lead Researcher                    |
| Monev             | `MonevEvaluationSubmitted` | Operator                           |
| Identity & Access | `UserRegistered`           | User (welcome)                     |
| Identity & Access | `UserVerified`             | User                               |
| Identity & Access | `ReviewerAppointed`        | Reviewer                           |
