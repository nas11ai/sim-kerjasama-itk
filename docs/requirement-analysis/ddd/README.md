# DDD Documentation — SIMPAS v2

> Sistem Manajemen Penelitian dan Pengabdian Masyarakat  
> Institut Teknologi Kalimantan

## Tentang Dokumen Ini

Dokumen ini adalah **sumber kebenaran tunggal** untuk arsitektur domain SIMPAS.

Tujuannya satu: agar developer bisa **langsung mulai kerja** tanpa perlu menebak-nebak maksud bisnis di balik kode.

---

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

---

## Cara Membaca Dokumen Ini

### 🎥 Video Panduan Membaca Dokumentasi

Untuk memahami cara membaca dan menggunakan dokumentasi ini dengan benar, silakan tonton video panduan berikut:

👉 [Klik untuk menonton video tutorial](https://drive.google.com/file/d/1LDI7zPthj61LB5r7HlpQPjCTt8Yv3jR2/view?usp=sharing)

### Urutan yang Disarankan

Jika kamu baru bergabung ke tim, baca dalam urutan berikut:

1. **[Domain Map](01_domain_map.md)** — mulai dari sini. Pahami gambaran besar: apa saja domain yang ada, mana yang Core vs Supporting vs Generic, dan bagaimana mereka saling berkaitan.

2. **[Ubiquitous Language](02_ubiquitous_language.md)** — baca kamus ini sebelum menyentuh kode. Setiap istilah di sini adalah bahasa yang digunakan oleh bisnis dan developer secara bersamaan.

3. **Domain yang relevan dengan sprint kamu** — buka file domain yang sedang dikerjakan. Tidak perlu baca semuanya sekaligus.

### Jika Kamu Frontend Developer

Fokus ke bagian **Aggregate Design** (untuk memahami shape data yang akan dikonsumsi) dan **Business Rules** (untuk validasi di UI). Bagian **Catatan Implementasi → `[FE]`** di setiap file berisi catatan khusus untuk frontend.

### Jika Kamu Backend Developer

Baca semua bagian, terutama **Business Rules** dan **Domain Events**. Bagian **Catatan Implementasi → `[BE]`** berisi catatan teknis spesifik untuk implementasi backend.

### Jika Kamu Analis

Fokus ke **Business Rules** dan **Interaction Map**. Bagian **Catatan Implementasi → `[A]`** berisi catatan khusus untuk analis.

---

## Struktur Setiap File Domain

Setiap file domain mengikuti struktur yang sama:

| Bagian                   | Isi                                                          |
| ------------------------ | ------------------------------------------------------------ |
| **Aggregate Design**     | Entitas, value object, dan relasi antar entitas dalam domain |
| **Business Rules**       | Aturan bisnis yang harus di-enforce di level domain          |
| **Domain Events**        | Kejadian penting yang dipublish domain ini dan efeknya       |
| **Interaction Map**      | Bagaimana domain ini berinteraksi dengan domain lain         |
| **Catatan Implementasi** | Catatan teknis per tim (`[A]`, `[BE]`, `[FE]`)               |

---

## Konvensi

**Penamaan kode** (class, field, event, enum, method) → **English**  
**Penjelasan & deskripsi** → **Bahasa Indonesia**

---

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

---

## Cara Update

- Perubahan business rule → update file BC, bump versi di header
- Istilah baru → daftarkan di `02_ubiquitous_language.md` sebelum dipakai di kode
- BC baru → buat file di folder yang sesuai, update README ini
