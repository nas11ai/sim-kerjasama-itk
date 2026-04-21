# 01 — Domain Map

**Versi:** 2.1  
**Status:** Draft

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
| 🟢 **Generic**    | Form Engine          | Platform inti dari sim-kerjasama — form, phase, submission |
| 🟢 **Generic**    | Identity & Access    | Auth, org tree, tiga jalur registrasi                      |
| 🟢 **Generic**    | Notification         | Laravel Notification                                       |
| 🟢 **Generic**    | File Management      | MinIO / Cloudflare R2                                      |
| 🟢 **Generic**    | System Configuration | Master data (tipe jurnal, tipe HKI, dll)                   |
| 🟢 **Generic**    | Observability        | Logging, metrics, error tracking, uptime monitoring        |

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
    BC_FE -->|"SK — FormSubmission"| BC_OUT
    BC_SCH -->|"CF"| BC_SUB
    BC_IAM -->|"SK — UserProfile + Org"| BC_SUB
    BC_IAM -->|"SK — Reviewer"| BC_REV
    BC_FM -->|"OHS"| BC_SUB
    BC_FM -->|"OHS"| BC_OUT
    BC_SC -->|"PL"| BC_SCH
    BC_SC -->|"PL"| BC_IAM
    BC_SUB -->|"SK"| BC_BUD
    BC_SUB -.->|"Events"| BC_NOT
    BC_REV -.->|"Events"| BC_NOT
    BC_MON -.->|"Events"| BC_NOT
    BC_IAM -.->|"Events"| BC_NOT
    BC_SUB -.->|"Logs & Metrics"| BC_OBS
    BC_REV -.->|"Logs & Metrics"| BC_OBS
    BC_FE -.->|"Logs & Metrics"| BC_OBS
    BC_IAM -.->|"Audit Trail"| BC_OBS

    classDef core fill:#fca5a5,stroke:#ef4444,color:#000
    classDef supporting fill:#fde68a,stroke:#f59e0b,color:#000
    classDef generic fill:#bbf7d0,stroke:#22c55e,color:#000
    class BC_SUB,BC_REV core
    class BC_BUD,BC_MON,BC_OUT,BC_SCH supporting
    class BC_FE,BC_IAM,BC_NOT,BC_FM,BC_SC,BC_OBS generic
```

---

## Main Business Flow

```mermaid
flowchart TD
    START([Start]) --> REG

    subgraph REGISTRATION["Registrasi"]
        REG{Tipe User?}
        REG -->|Dosen ITK| SR[Self-register<br/>+ domain check]
        REG -->|External| INV[Invitation link<br/>per organisasi]
        REG -->|Reviewer| OPR_R[Ditunjuk Operator<br/>dari existing user]
        SR --> VERIFY[Verifikasi NIDN<br/>oleh Operator]
        INV --> VERIFY
        VERIFY --> ACTIVE[User Active]
        OPR_R --> ACTIVE
    end

    ACTIVE --> A

    subgraph RESEARCHER["👤 Researcher"]
        A[Pilih Submission Period] --> B[Pilih Scheme<br/>opsional — tergantung Form]
        B --> C[Isi Form Pengajuan<br/>title, abstract, keywords, dll]
        C --> D[Tambah Research Members]
        D --> E[Input Budget Plan]
        E --> F[Upload Proposal PDF<br/>+ Additional Files]
        F --> G{Lengkap?}
        G -->|Belum| C
        G -->|Ya| H[Submit]
    end

    subgraph OPERATOR["🏢 LPPM Operator"]
        I[Assign Reviewer<br/>≥ min_reviewer_count]
    end

    subgraph REVIEWER["🔍 Reviewer"]
        J[Isi Evaluation Form<br/>per component & indicator]
        J --> K{Perlu Revisi?}
        K -->|Ya| L[Request Revision<br/>via ReviewSummary + ReviewComment]
        K -->|Tidak| M{Approve?}
    end

    H --> I --> J
    L --> N[Researcher Revisi<br/>& Resubmit]
    N --> J
    M -->|Tidak| REJECT[❌ Rejected → History]
    M -->|Ya| APPROVE[✅ Approved]

    APPROVE --> P[Monev — dalam satu FormPhase<br/>yang sama dengan pengajuan]
    P --> Q[Isi Laporan Kemajuan<br/>child FormSubmission]
    Q --> R[Reviewer Isi<br/>Evaluation Form Monev]
    R --> S[Upload Research Output<br/>per tipe via metadata JSONB]
    S --> T{Semua siklus<br/>Monev selesai?}
    T -->|Belum| Q
    T -->|Ya| HISTORY[📋 Submission History]
    REJECT --> HISTORY
```

---

## Organization + Access Model

```mermaid
graph TD
    subgraph ORG["Organization Tree (adjacency list)"]
        ITK["ITK (institution)"]
        F1["Fakultas Sains (faculty)"]
        F2["Fakultas Rekayasa (faculty)"]
        J1["Jurusan Ilkom (department) — opsional"]
        P1["Informatika (study_program)"]
        P2["Sistem Informasi (study_program)"]
        EXT["Unmul (institution — external)"]
        PE1["Teknik Sipil Unmul (study_program)"]

        ITK --> F1 --> J1 --> P1
        J1 --> P2
        ITK --> F2
        EXT --> PE1
    end

    subgraph ACCESS["Access Check"]
        FAC["form_access_controls<br/>role_id + organization_id"]
        UP["user_profiles<br/>organization_id"]
        FAC -->|"user org ada di subtree?"| CHECK{✓ / ✗}
        UP --> CHECK
    end
```

---

## Observability Stack per Environment

```mermaid
graph LR
    subgraph DEV["Development"]
        TEL["Laravel<br/>Telescope"]
    end

    subgraph STG["Staging"]
        GC["Grafana Cloud<br/>Free Tier<br/>Loki + Prometheus"]
    end

    subgraph PROD["Production (self-hosted)"]
        VM["VictoriaMetrics<br/>~150 MB"]
        LK["Loki + Promtail<br/>~200 MB"]
        GR["Grafana<br/>~150 MB"]
        UK["Uptime Kuma<br/>~50 MB"]
        GT["GlitchTip<br/>~300 MB"]
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

```
Phase 1 — Foundation
├── System Configuration   (zero dependency)
├── Identity & Access      (org tree + auth + 3 registration flows)
├── File Management
└── Observability          (Telescope di dev, siapkan stack prod dari awal)

Phase 2 — Platform
├── Form Engine            (Form, Phase, Submission — inti sim-kerjasama)
└── Scheme                 (decoupled dari Form, scheme_selector field type)

Phase 3 — Core Features
├── Submission             (extension tables: members, budget)
└── Budget

Phase 4 — Review Workflow
└── Review                 (reviewer_internal + reviewer_external Spatie roles)

Phase 5 — Post-Approval
├── Monev                  (FormPhaseDetail dalam lifecycle yang sama)
└── Research Output        (single table + JSONB metadata)

Phase 6 — Cross-cutting
└── Notification
```
