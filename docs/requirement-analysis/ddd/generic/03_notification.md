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
    A --> B[NotificationListener\nmenangkap event]
    B --> C[Resolve recipients\nberdasarkan event type]
    C --> D[Build email content\ndari Blade template]
    D --> E[Dispatch ke Mail Queue]
    E --> F{Berhasil?}
    F -->|Ya| G[Log: success]
    F -->|Tidak| H[Log: failed\ntidak retry otomatis]
```

---

## Events yang Dikonsumsi

| Source | Event | Recipients |
|---|---|---|
| Submission | `ProposalSubmitted` | LPPM Operator |
| Submission | `ProposalApproved` | Lead Researcher + Research Members |
| Submission | `ProposalRejected` | Lead Researcher |
| Review | `ReviewerAssigned` | Reviewer |
| Review | `RevisionRequested` | Lead Researcher |
| Monev | `MonevStageOpened` | Lead Researcher |
| Monev | `MonevEvaluationSubmitted` | Operator |
| Identity & Access | `UserRegistered` | User (welcome) |
| Identity & Access | `UserVerified` | User |
| Identity & Access | `ReviewerAppointed` | Reviewer |
