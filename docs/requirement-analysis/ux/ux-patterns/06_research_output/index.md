# UX Pattern: Research Output (Luaran Penelitian)

**Roles yang terlibat:** `Researcher` `Operator` `Admin`  
**DDD Context:** Research Output  
**Pattern utama:** Wizard (2-step), Simple CRUD List  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/06_research_output/index.md`

---

## Daftar Luaran

**Pattern:** Simple CRUD List (grouped)  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `SubmissionOutputGroup` | Card group per submission: header submission + daftar luaran di bawahnya | Main |

### Molecules yang Notable

- **`OutputTypeBadge`** — badge per tipe luaran: Artikel, Buku, HKI, Prototipe, PKS, Seminar. Warna berbeda per tipe.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Empty | Belum ada luaran | Empty state per submission: "Belum ada luaran. Tambah luaran penelitian." + tombol CTA |
| Read-only (member) | User adalah member, bukan submitted_by / co_investigator | Tombol tambah dan edit disembunyikan |
| Withdrawn | Submission WITHDRAWN | Badge "Ditarik" di header group, luaran tetap tampil sebagai historis read-only |

---

## Tambah Luaran

**Pattern:** Wizard 2-step  
**Layout:** Modal (tidak perlu halaman penuh — form relatif pendek)

### Step 1 — Pilih Tipe Luaran

Grid icon-card untuk pilih tipe: Artikel Jurnal, Buku, HKI, Prototipe, PKS, Seminar. Setiap card dengan ikon dan label. Klik langsung ke step 2.

### Step 2 — Isi Detail Luaran

Form field yang muncul sesuai tipe yang dipilih (driven by `config/research_output_types.php`). Berbeda per tipe — tidak hardcoded di UI.

`FileUploadZone` muncul di bawah field jika tipe memerlukan file lampiran.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Loading — step 2 | Setelah pilih tipe | Skeleton form sebentar |
| Error — file required | Submit tanpa file untuk tipe ip/prototype | Inline error: "Tipe ini wajib memiliki minimal satu file lampiran" |
| Success | Submit berhasil | Modal tutup, item baru muncul di daftar (optimistic update) |

### Business Rules yang Mempengaruhi UI

- `→ ddd/supporting/03_research_output.md#BR-RO-02` — tipe PKS tidak muncul di step 1 grid jika SubmissionType bukan CommunityService.
- `→ ddd/supporting/03_research_output.md#BR-RO-04` — tombol Submit disabled jika tipe ip/prototype dan belum ada file diupload.
