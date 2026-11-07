# BC: Research Output

**Klasifikasi:** 🟡 Supporting Domain  
**Versi:** 2.5  
**Status:** Draft

---

## Responsibility

Mengelola semua luaran dari kegiatan yang sudah disetujui. Disimpan di satu tabel generik `research_outputs` dengan JSONB metadata — menambah tipe luaran baru tidak butuh migration. Research Output **bukan** child FormSubmission — dia punya extension table sendiri dengan lifecycle yang berbeda.

---

## Activity Diagram

### Alur Upload Research Output

```mermaid
flowchart TD
    START([Submission status: APPROVED]) --> ACCESS

    subgraph ACCESS["Access Check"]
        A{User role<br/>di submission ini?}
        A -->|submitted_by<br/>atau co_investigator| CAN_EDIT[✅ Bisa tambah & edit]
        A -->|member| READ_ONLY[👁️ Read only]
        A -->|reviewer| READ_ONLY
        A -->|operator/admin| CAN_EDIT
    end

    CAN_EDIT --> B[Pilih output_type<br/>dari output_type_definitions]
    B --> C[Isi form via RepeatableField UI<br/>fields driven by type config]
    C --> D{Perlu file<br/>lampiran?}
    D -->|Ya| E[Upload ke research_output_files]
    D -->|Tidak| F[Submit → research_outputs table]
    E --> F
    F --> G{Tambah output lain?}
    G -->|Ya| B
    G -->|Tidak| END([Selesai])
```

---

## Schema

```sql
research_outputs
  id
  form_submission_id   FK → form_submissions
  output_type          varchar   -- 'article', 'book', 'ip', 'prototype', 'pks', 'meeting'
  metadata             JSONB
  created_by           FK → users
  created_at, updated_at

research_output_files
  id
  research_output_id   FK → research_outputs
  file_path            varchar
  file_url             varchar
  created_at, updated_at
```

### Contoh Metadata per Output Type

```json
// article
{
  "title": "...", "journal_name": "...", "journal_type": "international_reputable",
  "year": 2025, "doi": "10.xxxx/...", "url": "..."
}

// book
{ "title": "...", "publisher": "...", "year": 2025, "isbn": "..." }

// ip (Intellectual Property)
{
  "title": "...", "ip_type": "patent",
  "registration_number": "...", "registration_date": "2025-01-01", "status": "granted"
}

// prototype
{ "name": "...", "prototype_type": "software", "description": "..." }

// pks (Cooperation Agreement)
{
  "title": "...", "involved_parties": "...", "description": "...",
  "start_date": "2025-01-01", "end_date": null
}

// meeting
{ "title": "...", "meeting_type": "seminar", "year": 2025, "description": "..." }
```

### Menambah Tipe Output Baru

Tidak perlu migration. Tambah entry di `config/research_output_types.php`:

```php
'poster' => [
    'label'    => 'Poster Ilmiah',
    'has_files' => true,
    'fields'   => [
        ['key' => 'title', 'label' => 'Judul', 'type' => 'text', 'required' => true],
        ['key' => 'event', 'label' => 'Nama Event', 'type' => 'text', 'required' => true],
        ['key' => 'year',  'label' => 'Tahun', 'type' => 'number', 'required' => true],
    ],
],
```

### Query ke JSONB (PostgreSQL)

```sql
-- Artikel bereputasi internasional tahun ini
SELECT * FROM research_outputs
WHERE output_type = 'article'
  AND metadata->>'journal_type' = 'international_reputable'
  AND (metadata->>'year')::int = 2025;

-- GIN index untuk performa
CREATE INDEX idx_research_outputs_metadata ON research_outputs USING GIN (metadata);
```

---

## Access Control

| User                                    | Akses                                       |
| --------------------------------------- | ------------------------------------------- |
| `submitted_by` (Lead Researcher)        | Full access — tambah, edit, hapus           |
| `ResearchMember` role `co_investigator` | Bisa tambah dan edit                        |
| `ResearchMember` role `member`          | Read only                                   |
| Reviewer (via SubmissionReviewer)       | Read only                                   |
| Operator / Admin                        | Full access via `outputs.manage` **dan** `submissions.view-all` |

> **v2.5 — jalan staf butuh dua izin.** Peran `researcher` juga memegang `outputs.manage`
> ([identity v2.7](../generic/02_identity_access.md)) untuk melaporkan luaran timnya sendiri.
> Kalau `outputs.manage` saja sudah membuka jalan staf, setiap peneliti bisa menyunting luaran
> pengajuan siapa pun, termasuk yang ia tidak ikuti. Staf karenanya dikenali dari
> `outputs.manage` bersama `submissions.view-all` (membaca semua pengajuan, BR-SM-07).

> **Kecocokan dengan target luaran sengaja TIDAK ditegakkan.** `SubmissionTargetOutput`
> (BR-SM-14/15) adalah luaran yang *dijanjikan*; `ResearchOutput` yang *terwujud*.
> Penelitian memang menyimpang — janji artikel jurnal bisa berakhir sebagai paten.
> Memaksa keduanya cocok akan mendorong peneliti berbohong di target atau tidak
> melaporkan luaran nyata; dua-duanya lebih buruk daripada ketidakcocokan itu sendiri.
>
> Prinsipnya: **batasi yang harus benar, ukur yang sebaiknya benar.** Taksonomi dibatasi
> (BR-SM-14, BR-RO-05); pemenuhan janji adalah **metrik pelaporan**, bukan aturan yang
> memblokir. Tidak ada validasi lintas-aggregate di sini.

Tabel di atas hanya menjawab sumbu **siapa**. Sumbu **kapan** ditentukan deadline
`FormPhaseDetail` tempat luaran bergantung (BR-RO-08), dan sumbu **keadaan** oleh
BR-RO-01 (induk wajib APPROVED). Ketiganya harus lolos — lihat
[ADR 0014](../../../adr/0014-time-boxed-access-single-override-mechanism.md).

---

## Business Rules

| Kode     | Rule                                                                                                          |
| -------- | ------------------------------------------------------------------------------------------------------------- |
| BR-RO-01 | Research Output hanya bisa ditambahkan untuk Submission berstatus APPROVED                                    |
| BR-RO-02 | `output_type = 'pks'` hanya valid untuk Submission dengan SubmissionType = CommunityService                   |
| BR-RO-03 | `metadata.end_date` boleh null untuk PKS yang ongoing                                                         |
| BR-RO-04 | `output_type = 'ip'` dan `output_type = 'prototype'` wajib punya minimal satu file di `research_output_files` |
| BR-RO-05 | `output_type` harus terdaftar di `output_type_definitions` — tidak bisa arbitrary string                      |
| BR-RO-06 | Research Output tetap ada sebagai data historis setelah submission WITHDRAWN — tidak dihapus                  |
| BR-RO-07 | Di dalam **tim penelitian**, hanya `submitted_by` dan `co_investigator` yang bisa tambah atau edit output; `member` dan Reviewer read only. Operator dan Admin pemegang `outputs.manage` tetap bisa menyunting — luaran memasok pelaporan LPPM, dan koreksi datanya memang tugas staf. Ini **berbeda** dari anggaran (BR-BUD-02/09), yang daftar tertutupnya menjadi invarian domain di [ADR 0015](../../../adr/0015-gate-before-never-bypasses-domain-invariants.md): anggaran adalah komitmen peneliti, luaran adalah laporan fakta |
| BR-RO-08 | Menambah dan menyunting Research Output tunduk pada deadline `FormPhaseDetail` tempatnya bergantung (Detail "Upload Luaran"). Lewat deadline luaran **tetap terbaca** (BR-FE-15) tapi tidak bisa diubah, kecuali ada `FormSubmissionOverride` yang masih berlaku (BR-FE-08). Tidak ada mekanisme batas waktu tersendiri untuk luaran — lihat [ADR 0014](../../../adr/0014-time-boxed-access-single-override-mechanism.md). **v2.5:** detail Upload Luaran dikenali dari Batas Submit-nya yang berjenis `output` (Batas Akhir, [BR-FE-25/26](../generic/01_form_engine.md)), dalam tahap yang sama dan sesudah detail pengajuannya. Tahap tanpa detail itu tidak membatasi waktu penulisan luaran. Penutupan paksa periode (BR-SCH-07) tidak menutupnya |
| BR-RO-09 | `metadata` wajib memenuhi field yang ditandai `required` pada definisi tipenya di `output_type_definitions`. Tanpa ini "taksonomi dibatasi" hanya benar separuh: tipenya terkendali, isinya tidak — dan luaran berisi objek kosong lolos ke pelaporan sebagai kegiatan yang seolah terlaporkan. Field yang tidak `required` boleh kosong, termasuk `metadata.end_date` untuk PKS berjalan (BR-RO-03) |

---

## Domain Events

| Event         | Trigger                                       | Consumer                           |
| ------------- | --------------------------------------------- | ---------------------------------- |
| `OutputAdded` | Researcher/Co-Investigator tambah output baru | Notification (opsional), Reporting |

---

## Integration Map

| Context              | Arah                       | Keterangan                                |
| -------------------- | -------------------------- | ----------------------------------------- |
| Submission           | Upstream → Research Output | Eligibility check: hanya untuk APPROVED   |
| File Management      | Upstream → Research Output | Upload research_output_files              |
| System Configuration | Upstream → Research Output | output_type_definitions config            |
| Reporting            | Research Output → Read     | Data luaran untuk export dan laporan LPPM |
