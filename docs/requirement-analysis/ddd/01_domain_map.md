# 01 — Domain Map

**Versi:** 2.4  
**Status:** Draft

> **Perubahan v2.3 → v2.4.** Bagian *Implementation Priority* diganti dengan
> urutan milestone M0–M5.
> Lihat [ADR 0009](../../adr/0009-milestone-sequencing-critical-path-first.md).

---

## Klasifikasi Domain

| Klasifikasi       | Bounded Context      | Alasan                                                     |
| ----------------- | -------------------- | ---------------------------------------------------------- |
| 🔴 **Core**       | Submission           | Lifecycle pengajuan proposal — inti bisnis SIMPAS          |
| 🔴 **Core**       | Review               | Approval workflow yang membedakan sistem ini               |
| 🟡 **Supporting** | Budget               | Penting tapi tidak differentiating                         |
| 🟡 **Supporting** | Monev                | Penting tapi tidak differentiating                         |
| 🟡 **Supporting** | Research Output      | Pelaporan luaran penelitian                                |
| 🟡 **Supporting** | Scheme               | Katalog skema yang dikontrol admin                         |
| 🟡 **Supporting** | Reporting            | Export, statistik, audit trail, cetak dokumen              |
| 🟢 **Generic**    | Form Engine          | Platform inti dari sim-kerjasama — form, phase, submission |
| 🟢 **Generic**    | Identity & Access    | Auth, org tree, tiga jalur registrasi                      |
| 🟢 **Generic**    | Notification         | Laravel Notification                                       |
| 🟢 **Generic**    | File Management      | MinIO / Cloudflare R2                                      |
| 🟢 **Generic**    | System Configuration | Master data (tipe jurnal, tipe HKI, dll)                   |
| 🟢 **Generic**    | Observability        | Logging, metrics, error tracking, uptime                   |

---

## Bounded Context Map

```mermaid
graph TB
    subgraph CORE["🔴 Core Domain"]
        BC_SUB["**Submission**"]
        BC_REV["**Review**"]
    end

    subgraph SUPPORTING["🟡 Supporting Domain"]
        BC_BUD["**Budget**"]
        BC_MON["**Monev**"]
        BC_OUT["**Research Output**"]
        BC_SCH["**Scheme**"]
        BC_RPT["**Reporting**"]
    end

    subgraph GENERIC["🟢 Generic Domain"]
        BC_FE["**Form Engine**"]
        BC_IAM["**Identity & Access**"]
        BC_NOT["**Notification**"]
        BC_FM["**File Management**"]
        BC_SC["**System Configuration**"]
        BC_OBS["**Observability**"]
    end

    BC_FE -->|"SK — FormSubmission"| BC_SUB
    BC_FE -->|"SK — FormSubmission"| BC_REV
    BC_FE -->|"SK — FormSubmission"| BC_MON
    BC_SCH -->|"CF"| BC_SUB
    BC_IAM -->|"SK — UserProfile + Org"| BC_SUB
    BC_IAM -->|"SK — Reviewer"| BC_REV
    BC_FM -->|"OHS"| BC_SUB
    BC_FM -->|"OHS"| BC_OUT
    BC_FM -->|"OHS"| BC_RPT
    BC_SC -->|"PL"| BC_SCH
    BC_SC -->|"PL"| BC_IAM
    BC_SUB -->|"SK"| BC_BUD
    BC_SUB -->|"OHS"| BC_OUT
    BC_SUB -.->|"Events"| BC_NOT
    BC_REV -.->|"Events"| BC_NOT
    BC_MON -.->|"Events"| BC_NOT
    BC_IAM -.->|"Events"| BC_NOT
    BC_SUB -.->|"Read"| BC_RPT
    BC_REV -.->|"Read"| BC_RPT
    BC_BUD -.->|"Read"| BC_RPT
    BC_OUT -.->|"Read"| BC_RPT
    BC_FE -.->|"Read"| BC_RPT
    BC_SUB -.->|"Logs & Metrics"| BC_OBS
    BC_REV -.->|"Logs & Metrics"| BC_OBS
    BC_FE -.->|"Logs & Metrics"| BC_OBS
    BC_IAM -.->|"Audit Trail"| BC_OBS

    classDef core fill:#fca5a5,stroke:#ef4444,color:#000
    classDef supporting fill:#fde68a,stroke:#f59e0b,color:#000
    classDef generic fill:#bbf7d0,stroke:#22c55e,color:#000
    class BC_SUB,BC_REV core
    class BC_BUD,BC_MON,BC_OUT,BC_SCH,BC_RPT supporting
    class BC_FE,BC_IAM,BC_NOT,BC_FM,BC_SC,BC_OBS generic
```

---

## Main Business Flow

```mermaid
flowchart TD
    START([Start]) --> REG

    subgraph REGISTRATION["Registrasi"]
        REG{Tipe User?}
        REG -->|Dosen ITK| SR[Self-register + domain check]
        REG -->|External| INV[Invitation link per organisasi]
        REG -->|Reviewer| OPR_R[Ditunjuk Operator dari existing user]
        SR --> VERIFY[Verifikasi NIDN oleh Operator]
        INV --> VERIFY
        VERIFY --> ACTIVE[User Active]
        OPR_R --> ACTIVE
    end

    ACTIVE --> A

    subgraph RESEARCHER["👤 Researcher"]
        A[Pilih Submission Period] --> B[Pilih Scheme<br/>opsional — tergantung Form]
        B --> C[Isi Form Pengajuan]
        C --> D[Tambah Research Members]
        D --> E[Input Budget Plan]
        E --> F[Upload Files]
        F --> G{Lengkap?}
        G -->|Belum| C
        G -->|Ya| H[Submit]
    end

    subgraph OPERATOR["🏢 LPPM Operator"]
        I[Assign Reviewer<br/>≥ min_reviewer_count dari scheme.rules]
    end

    subgraph REVIEWER["🔍 Reviewer"]
        J[Isi ReviewEvaluationForm<br/>lalu Selesai menilai / memeriksa]
        J --> K{Semua peserta putaran<br/>Selesai?}
        K -->|Belum| WAIT([Tunggu])
        K -->|Ya| L{Ada ReviewSummary<br/>open?}
        L -->|Ya| REV_REQ[Status → NEEDS_REVISION<br/>Otomatis]
        L -->|Tidak| READY[Siap diputuskan]
    end

    H --> I --> J
    REV_REQ --> N[Researcher Revisi & Resubmit]
    N --> J
    READY --> DEC[Operator: Setujui / Tolak]
    DEC --> APPROVE[✅ Approved]
    APPROVE --> P[Monev — dalam satu FormPhase<br/>yang sama dengan pengajuan]
    P --> Q[Laporan Kemajuan<br/>child FormSubmission]
    Q --> R[Reviewer Isi Evaluation Monev]
    R --> S[Upload Research Output]
    S --> T{Semua siklus selesai?}
    T -->|Belum| Q
    T -->|Ya| HISTORY[📋 Submission History]

    OPERATOR_REJ([Operator Reject Manual]) --> REJECT[❌ Rejected → History]
    REJECT --> HISTORY
```

---

## Observability Stack per Environment

```mermaid
graph LR
    subgraph DEV["Development"]
        TEL["Laravel Telescope"]
    end
    subgraph STG["Staging"]
        GC["Grafana Cloud<br/>Free Tier"]
    end
    subgraph PROD["Production"]
        VM["VictoriaMetrics"]
        LK["Loki + Promtail"]
        GR["Grafana"]
        UK["Uptime Kuma"]
        GT["GlitchTip"]
    end
    APP["Laravel App"] -->|logs| TEL
    APP -->|logs| GC
    APP -->|logs| LK
    APP -->|metrics| VM
    APP -->|errors| GT
    GR --- VM
    GR --- LK
    UK -->|ping /health| APP
```

---

## Implementation Priority

Urutan di bawah menggantikan pembagian Phase 1–6 versi 2.3. Alasannya: Phase 1
dan 2 versi lama (System Configuration, Identity & Access, Form Engine, Scheme)
justru bagian yang **sudah paling jadi** di codebase, sehingga mengikutinya
berarti mengerjakan ulang yang sudah ada sambil membiarkan loop inti tetap rusak.
Notification juga tidak bisa ditaruh paling akhir karena domain event dibutuhkan
sejak Submission. Lihat
[ADR 0009](../../adr/0009-milestone-sequencing-critical-path-first.md).

```
M0 — Fondasi
├── form_phase_detail_id, scheme_id, trl_id, submission_period_id
├── Policy + isAccessibleBy()
├── Events / Listeners + Laravel Notification
├── StorageService (MinIO / R2)
└── field type scheme_selector & trl_selector, optimistic locking

M1 — Submission
├── research_members, student_members, partners, additional_files
├── SubmitFormSubmission (Temporal Field Binding, proposal PDF, deadline)
├── Budget: lock per status, max_budget, audit nilai lama/baru
└── transfer ownership, withdrawal

M2 — Review
├── EvaluateSubmissionOutcome (menggantikan auto-reject yang melanggar BR-REV-07)
├── AssignReviewers: min_reviewer_count, workload, conflict of interest
└── reassignment, visibilitas skor

    ⬇ setelah M2 sistem fungsional end-to-end

M3 — Identity & Access
└── tiga jalur registrasi, verifikasi NIDN, deaktivasi + guard ownership

M4 — Monev & Research Output
M5 — Reporting
```
