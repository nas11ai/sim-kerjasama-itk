# DDD Documentation — SIMPAS v2

> Sistem Manajemen Penelitian dan Pengabdian Masyarakat  
> Institut Teknologi Kalimantan

## Struktur Dokumen

```
ddd/
├── README.md
├── 01_domain_map.md
├── 02_ubiquitous_language.md
│
├── core/
│   ├── 01_submission.md          ← Submission (Pengajuan + extension tables)
│   └── 02_review.md              ← Review & Approval
│
├── supporting/
│   ├── 01_budget.md              ← Budget Planning
│   ├── 02_monev.md               ← Monitoring & Evaluation
│   ├── 03_research_output.md     ← Research Output (Luaran)
│   └── 04_scheme.md              ← Scheme Catalog
│
└── generic/
    ├── 01_form_engine.md         ← Form Engine (platform inti)
    ├── 02_identity_access.md     ← Identity, Access & Organization
    ├── 03_notification.md        ← Notification
    ├── 04_file_management.md     ← File Management
    └── 05_system_configuration.md ← System Configuration
```

## Konvensi

**Penamaan kode** (class, field, event, enum, method) → **English**  
**Penjelasan & deskripsi** → **Bahasa Indonesia**

## Prinsip Arsitektur Kunci

- **FormSubmission** adalah entitas submission universal — tidak ada tabel `submissions` terpisah
- **Extension tables** (research_members, budget_line_items, dll) FK ke `form_submission_id`
- **parent_submission_id** di `form_submissions` untuk child submissions (luaran, laporan monev)
- **Organization tree** (adjacency list) menggantikan `faculties` + `study_programs` yang fixed
- **Spatie roles** = apa yang boleh dilakukan (authorization), bukan siapa kamu (identity)
- **RepeatableField** = UI pattern saja — data ke extension tables, bukan JSON
- **Monev** = bagian dari satu FormPhase yang sama dengan pengajuan, bukan phase terpisah
- **ResearchOutput** = satu tabel + JSONB, tidak ada tabel per tipe luaran
- **Scheme** = decoupled dari Form, opsional via scheme_selector field type
- **ReviewerRole** dihapus, diganti Spatie roles: reviewer_internal + reviewer_external — data tetap disimpan ke extension tables, bukan JSON
- **PostgreSQL** sebagai database — recursive CTE untuk org tree traversal

## Cara Update

- Perubahan business rule → update file BC, bump versi di header
- Istilah baru → daftarkan di `02_ubiquitous_language.md` sebelum dipakai di kode
- BC baru → buat file di folder yang sesuai, update README ini
