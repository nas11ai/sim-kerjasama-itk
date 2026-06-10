# IA: Research Output (Luaran Penelitian)

**Roles yang terlibat:** `Researcher` `Operator` `Admin`  
**DDD Context:** Research Output  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page                 | Route                                      | Accessible By                              |
| --- | -------------------- | ------------------------------------------ | ------------------------------------------ |
| 1   | Daftar Luaran        | `/research-outputs`                        | Researcher                                 |
| 2   | Tambah Luaran        | `/research-outputs/create?submission={id}` | Researcher (submitted_by, co-investigator) |
| 3   | Detail / Edit Luaran | `/research-outputs/{id}`                   | Researcher, Operator, Admin                |

---

## Daftar Luaran

**Route:** `/research-outputs`  
**Accessible by:** Researcher (memiliki submission APPROVED)  
**Entry points:**

- Sidebar nav → Luaran Penelitian

**Exit points:**

- → Tambah Luaran
- → Detail / Edit Luaran

### Konten Utama

Luaran dikelompokkan per submission. Setiap submission menampilkan: judul, skema, dan daftar luaran yang sudah diinput per tipe (artikel, buku, HKI, prototipe, PKS, seminar).

### Actions

| Aksi          | Kondisi                                                            |
| ------------- | ------------------------------------------------------------------ |
| Tambah Luaran | Submission APPROVED, user adalah submitted_by atau co_investigator |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/03_research_output.md#BR-RO-07` — user dengan role `member` hanya bisa lihat, tidak bisa tambah atau edit.

---

## Tambah Luaran

**Route:** `/research-outputs/create?submission={id}`  
**Accessible by:** Researcher (submitted_by, co_investigator)  
**Entry points:**

- Tombol "Tambah Luaran" dari Daftar Luaran atau Detail Submission

**Exit points:**

- → Daftar Luaran / Detail Submission

### Konten Utama

Form dua langkah: pilih tipe luaran dulu (artikel / buku / HKI / prototipe / PKS / seminar), lalu muncul field-field yang sesuai dengan tipe tersebut (driven by config, bukan hardcoded). Upload file lampiran tersedia jika tipe memerlukannya.

### Actions

| Aksi              | Kondisi                        |
| ----------------- | ------------------------------ |
| Pilih tipe luaran | Selalu                         |
| Submit            | Semua field required terpenuhi |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/03_research_output.md#BR-RO-02` — tipe `pks` hanya muncul di dropdown jika SubmissionType = CommunityService.
- `→ ddd/supporting/03_research_output.md#BR-RO-04` — tipe `ip` dan `prototype` wajib punya minimal satu file — tombol Submit di-disable sebelum upload.

---

## Detail / Edit Luaran

**Route:** `/research-outputs/{id}`  
**Accessible by:** Researcher (edit: submitted_by dan co_investigator; read: member), Operator, Admin  
**Entry points:**

- Klik item dari Daftar Luaran

### Konten Utama

Detail luaran yang sudah diinput. Mode edit tersedia untuk submitted_by dan co_investigator.

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/03_research_output.md#BR-RO-06` — luaran dari submission WITHDRAWN tetap ditampilkan sebagai data historis read-only.
