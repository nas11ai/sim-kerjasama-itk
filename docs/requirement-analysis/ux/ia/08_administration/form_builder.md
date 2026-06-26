# IA: Administration — Form Builder

**Roles yang terlibat:** `Admin`  
**DDD Context:** Form Engine  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page                                | Route                                 | Accessible By |
| --- | ----------------------------------- | ------------------------------------- | ------------- |
| 1   | Daftar Form                         | `/admin/forms`                        | Admin         |
| 2   | Edit Form — General & Fields        | `/admin/forms/{id}/edit`              | Admin         |
| 3   | Edit Form — Access Control          | `/admin/forms/{id}/access`            | Admin         |
| 4   | Edit Form — Phases & Deadlines      | `/admin/forms/{id}/phases`            | Admin         |
| 5   | Edit Phase — Details & Review Forms | `/admin/forms/{id}/phases/{phase_id}` | Admin         |

---

## Daftar Form

**Route:** `/admin/forms`  
**Entry points:**

- Sidebar → Konfigurasi Sistem → Form Builder

**Exit points:**

- → Edit Form

### Konten Utama

Tabel semua form: nama, tipe form, jumlah field aktif, jumlah submission aktif yang menggunakan form ini, status (active/inactive).

### Actions

| Aksi             | Kondisi                                          |
| ---------------- | ------------------------------------------------ |
| Buat Form Baru   | Selalu                                           |
| Edit Form        | Selalu                                           |
| Nonaktifkan Form | Tidak ada submission aktif yang menggunakan form |

---

## Edit Form — General & Fields

**Route:** `/admin/forms/{id}/edit`  
**Entry points:**

- Klik "Edit" dari Daftar Form, atau navigasi tab dari halaman edit lainnya

### Konten Utama

**Section General:** judul form, deskripsi, tipe form, status aktif.

**Section Fields:** daftar FormField yang bisa di-reorder (drag & drop), tambah field baru, edit field existing. Setiap field menampilkan: label, tipe (text/number/select/file/scheme_selector/dll), required atau opsional, `required_since`.

### Actions

| Aksi                        | Kondisi                                                    |
| --------------------------- | ---------------------------------------------------------- |
| Tambah field                | Selalu                                                     |
| Edit field                  | Selalu                                                     |
| Reorder field (drag & drop) | Selalu                                                     |
| Soft delete field           | Selalu (tidak bisa hard delete)                            |
| Toggle is_required          | Ada prompt notifikasi ke researcher jika ada active drafts |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/generic/01_form_engine.md#BR-FE-12` — field tidak bisa dihapus permanen, hanya soft delete. Tombol "Hapus" memicu konfirmasi.
- `→ ddd/generic/01_form_engine.md#BR-FE-13` — mengubah `is_required` saat ada active drafts memunculkan dialog: "Ada N draft aktif yang menggunakan form ini. Kirim notifikasi ke researcher terdampak?"
- `→ ddd/generic/01_form_engine.md#BR-FE-11` — saat field baru ditambah, sistem otomatis set `required_since = now()` jika `is_required = true`. Ini ditampilkan sebagai info di form field edit.

---

## Edit Form — Access Control

**Route:** `/admin/forms/{id}/access`  
**Entry points:**

- Tab "Access Control" dari halaman edit form

### Konten Utama

Tabel FormAccessControl yang ada: permission string dan organisasi yang diizinkan. Form untuk menambah rule baru.

### Actions

| Aksi               | Kondisi                    |
| ------------------ | -------------------------- |
| Tambah access rule | Selalu                     |
| Hapus access rule  | Selalu (dengan konfirmasi) |

---

## Edit Form — Phases & Deadlines

**Route:** `/admin/forms/{id}/phases`  
**Entry points:**

- Tab "Phases" dari halaman edit form

### Konten Utama

Daftar FormPhase yang terhubung ke form ini. Setiap phase menampilkan: judul, jumlah FormPhaseDetail, dan periode yang terhubung.

### Actions

| Aksi                  | Kondisi |
| --------------------- | ------- |
| Buat Phase Baru       | Selalu  |
| Edit Phase            | Selalu  |
| Link Phase ke Periode | Selalu  |

---

## Edit Phase — Details & Review Forms

**Route:** `/admin/forms/{id}/phases/{phase_id}`  
**Entry points:**

- Klik "Edit" dari daftar phase

### Konten Utama

Daftar FormPhaseDetail dalam phase ini (ordered). Setiap detail: tipe (researcher / reviewer), akses control, deadline (SubmissionDate), flag `needs_review`.

Jika detail bertipe reviewer dan `needs_review = true`: tampilkan ReviewEvaluationForm yang terhubung beserta ReviewFormField-nya.

### Actions

| Aksi                                   | Kondisi                      |
| -------------------------------------- | ---------------------------- |
| Tambah / edit PhaseDetail              | Selalu                       |
| Tambah ReviewEvaluationForm            | PhaseDetail bertipe reviewer |
| Tambah / reorder ReviewFormField       | Ada ReviewEvaluationForm     |
| Set / update deadline (SubmissionDate) | Selalu                       |
