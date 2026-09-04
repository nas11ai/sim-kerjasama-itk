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
│   ├── 01_submission.md          ← Submission lifecycle + extension tables
│   └── 02_review.md              ← Review, approval, revision
│
├── supporting/
│   ├── 01_budget.md              ← Budget Planning
│   ├── 02_monev.md               ← Monitoring & Evaluation
│   ├── 03_research_output.md     ← Research Output (Luaran)
│   ├── 04_scheme.md              ← Scheme Catalog
│   └── 07_reporting.md           ← Reporting & Export (BC Baru)
│
└── generic/
    ├── 01_form_engine.md         ← Form Engine (platform inti)
    ├── 02_identity_access.md     ← Identity, Access & Organization
    ├── 03_notification.md        ← Notification
    ├── 04_file_management.md     ← File Management
    ├── 05_system_configuration.md ← System Configuration
    └── 06_observability.md       ← Logging, Metrics, Error Tracking
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
- **parent_submission_id** di `form_submissions` hanya untuk child submissions (laporan monev, kelengkapan) — bukan untuk research output
- **Organization tree** (adjacency list) menggantikan `faculties` + `study_programs` yang fixed
- **Spatie Permission** = apa yang boleh dilakukan; **Organization** = dari mana user berasal
- **FormAccessControl** menggunakan `permission` + `organization_id` — bukan role_id
- **Submission-level access** (submitted_by + research_members) adalah lapis terpisah dari FormAccessControl
- **spatie/laravel-model-states** untuk state machine FormSubmission
- **Temporal Field Binding** — validasi `is_required` berdasarkan `required_since` vs waktu submission dibuat
- **RepeatableField** = UI pattern saja — data tetap ke extension tables, bukan form_field_responses
- **scheme_selector & trl_selector** mengikuti preseden yang sama — menulis ke `form_submissions.scheme_id` / `.trl_id`, bukan ke form_field_responses ([ADR 0005](../../adr/0005-scheme-selector-writes-to-column.md))
- **form_phase_detail_id** di `form_submissions` — relasi eksplisit, bukan ditebak dari form_id ([ADR 0004](../../adr/0004-form-phase-detail-id-on-submissions.md))
- **Pembuatan pengajuan berupa wizard 7 langkah** — baris dibuat di langkah 1 agar Temporal Field Binding punya jangkar yang benar ([ADR 0012](../../adr/0012-wizard-driven-submission-lifecycle.md))
- **REJECTED tidak pernah otomatis** — hanya lewat aksi eksplisit Operator ([ADR 0006](../../adr/0006-rejection-is-manual-only.md))
- **scheme.rules JSONB** untuk rule opsional yang extensible tanpa migration
- **PostgreSQL** sebagai database — recursive CTE untuk org tree, JSONB untuk metadata

---

## Aturan Cermin Lintas Konteks

Tujuh aturan ditulis di **dua bounded context** karena keduanya berkepentingan. Ini
disengaja — tiap BC harus bisa dibaca utuh tanpa melompat ke BC lain. Yang **tidak**
boleh terjadi adalah dua implementasi.

| Kanonik | Cermin | Aturan | Satu implementasi di |
| ------- | ------ | ------ | -------------------- |
| BR-FE-05 | BR-REV-08 | `ReviewFormResponse` terkunci setelah `submitted` | Form Engine |
| BR-FE-08 | BR-MON-05 | Deadline keras + override pada `FormPhaseDetail` | Form Engine |
| BR-FE-14 | BR-SM-12 | Optimistic locking lewat `updated_at` | Form Engine |
| BR-BUD-01 | BR-SM-06 | Grand total ≤ `scheme.max_budget` | Budget |
| BR-REV-02 | BR-SM-08, BR-IAM-07 | Konflik kepentingan reviewer ↔ anggota (mutual block) | Review |
| BR-IAM-10 | BR-SM-09 | Transfer ownership sebelum deaktivasi lead researcher | Identity & Access |

**Aturannya:** kolom *Kanonik* yang memegang definisi dan tempat implementasinya. Baris
cermin menyebut ulang aturan yang sama supaya BC-nya utuh dibaca, dan menautkan balik ke
kanonik. Satu Action atau Policy, dipanggil dari beberapa tempat — bukan dua yang
kebetulan mirip lalu menyimpang diam-diam.

Skenario BDD yang membuktikan aturan cermin membawa **dua tag `@BR-`** sekaligus, karena
ia memang membuktikan keduanya. Itu satu-satunya kasus di mana dua tag dibenarkan; di
luar itu, tag kedua yang tidak benar-benar dibuktikan adalah tag dekoratif.

### Yang tampak kembar tapi bukan

| A | B | Kenapa berbeda |
| - | - | -------------- |
| BR-SM-14 | BR-RO-05 | Taksonomi `output_type` yang sama, tapi **dua entitas berbeda**: target yang dijanjikan (`SubmissionTargetOutput`) vs luaran yang terealisasi (`ResearchOutput`). Dua momen, dua aturan |
| BR-FE-04 | BR-MON-01 | FE-04 mengatur **pembuatan** child submission; MON-01 mengatur **akses** ke fase monev. Guard-nya berbeda meski syaratnya sama-sama induk APPROVED |

Keduanya sengaja **tidak** digabung. Menyamakannya akan menghapus perbedaan yang justru
penting: BR-SM-15 melarang Co-Investigator mengubah target, sementara BR-RO-07 justru
membolehkannya mengisi luaran realisasi.

---

## Cara Update

- Perubahan business rule → update file BC, bump versi di header
- Istilah baru → daftarkan di `02_ubiquitous_language.md` sebelum dipakai di kode
- BC baru → buat file di folder yang sesuai, update README ini
- **Keputusan yang menyimpang dari dokumen ini → tulis ADR di [`docs/adr/`](../../adr/)**, lalu tautkan dari file BC yang terdampak. Dokumen DDD menyatakan *apa* aturannya; ADR menyimpan *kenapa* aturan itu berubah beserta alternatif yang ditolak.

Rencana implementasi turunan dokumen ini ada di
[`docs/design/backend-implementation.md`](../../design/backend-implementation.md).
