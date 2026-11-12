# IA: Administration — Form Builder

**Roles yang terlibat:** `Admin`  
**DDD Context:** Form Engine  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page                                | Route                           | Accessible By |
| --- | ----------------------------------- | ------------------------------- | ------------- |
| 1   | Daftar Form                         | `/admin/forms`                  | Admin         |
| 2   | Edit Form — General & Fields        | `/admin/forms/{id}/edit`        | Admin         |
| 3   | Edit Form — Access Control          | `/admin/forms/{id}/access`      | Admin         |
| 4   | Fase — pustaka                      | `/admin/phases` (`?form={id}`)  | Admin         |
| 5   | Edit Phase — Details & Review Forms | `/admin/phases/{phase_id}/edit` | Admin         |
| 6   | Form Evaluasi — pustaka             | `/admin/evaluation-forms`       | Admin         |
| 7   | Edit Form Evaluasi                  | `/admin/evaluation-forms/{id}/edit` | Admin     |

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

## Fase — pustaka

**Route:** `/admin/phases` (opsional `?form={id}`)  
**Entry points:**

- Sidebar → Konfigurasi Sistem → Fase
- Tab "Phases" dari halaman edit form → pustaka bertapis Form itu (R2)

### Konten Utama

Semua FormPhase, urut judul. Fase tidak bersarang di satu Form: satu fase memakai tepat satu Form
pengajuan (BR-FE-28) dan dipakai banyak periode. Setiap phase menampilkan: judul, Form pengajuan,
jumlah FormPhaseDetail, periode yang terhubung, dan celah jadwal per periode. Tapis `?form=`
menampilkan fase yang langkah mana pun memakai Form itu.

### Actions

| Aksi                  | Kondisi                |
| --------------------- | ---------------------- |
| Buat Phase Baru       | Selalu                 |
| Edit Phase            | Selalu                 |
| Link Phase ke Periode | Punya `periods.manage` |

---

## Edit Phase — Details & Review Forms

**Route:** `/admin/phases/{phase_id}/edit` (buat: `/admin/phases/create`)  
**Entry points:**

- Klik "Edit" dari pustaka fase

### Konten Utama

Daftar FormPhaseDetail dalam phase ini (ordered). Setiap detail: tipe (researcher / reviewer), akses control, deadline (SubmissionDate), flag `needs_review`.

Jika detail bertipe reviewer dan `needs_review = true`: tampilkan Form Evaluasi yang **terpasang**
(StepEvaluationForm) beserta label versinya, wajib/aktif per pemasangan, dan jumlah penugasannya di
langkah itu. Isi Form Evaluasi tidak disusun di sini — tautan "Kelola di Form Evaluasi" membuka
pustakanya (BR-FE-30).

### Actions

| Aksi                              | Kondisi                                                                    |
| --------------------------------- | -------------------------------------------------------------------------- |
| Tambah / edit PhaseDetail         | Selalu                                                                     |
| Pasang Form Evaluasi              | Detail `needs_review`; Form Evaluasi sudah terbit, belum terpasang di sana |
| Jadikan wajib / tidak wajib       | Ada pemasangan                                                             |
| Nonaktifkan / aktifkan pemasangan | Ada pemasangan                                                             |
| Lepas Form Evaluasi               | Belum ditugaskan ke reviewer di langkah itu (BR-FE-22)                     |

---

## Form Evaluasi — pustaka

**Route:** `/admin/evaluation-forms`  
**Entry points:**

- Sidebar → Konfigurasi Sistem → Form Evaluasi
- Tautan "Kelola di Form Evaluasi" dari bagian evaluasi editor tahap

### Konten Utama

Semua ReviewEvaluationForm, urut judul. Setiap kartu: judul, label versi terbit terakhir atau lencana
"Belum terbit", jumlah langkah pemasang dan penugasan reviewer, serta banner draf bila ada isi yang
belum diterbitkan (BR-FE-31).

### Actions

| Aksi               | Kondisi                                                                    |
| ------------------ | -------------------------------------------------------------------------- |
| Buat Form Evaluasi | Selalu                                                                     |
| Edit               | Selalu                                                                     |
| Hapus              | Tidak terpasang di langkah mana pun dan belum pernah ditugaskan (BR-FE-22) |

---

## Edit Form Evaluasi

**Route:** `/admin/evaluation-forms/{id}/edit` (buat: `/admin/evaluation-forms/create`)  
**Entry points:**

- Kartu pustaka Form Evaluasi
- "Sunting isi" pada pemasangan di editor tahap

### Konten Utama

Nama pustaka (judul, deskripsi — tak berversi, disimpan langsung), lalu tiga tab: **Field** (draf yang
disusun), **Pratinjau** (lembar seperti dilihat reviewer, terkunci), dan **Versi & pemakaian** (riwayat
versi dan tahap yang memasangnya). Banner draf tampil selama ada draf (BR-FE-31).

### Actions

| Aksi                          | Kondisi                                                                                   |
| ----------------------------- | ----------------------------------------------------------------------------------------- |
| Simpan nama                   | Selalu                                                                                    |
| Tambah / edit / reorder field | Selalu (masuk draf)                                                                       |
| Simpan draf                   | Selalu                                                                                    |
| Buang draf                    | Ada draf                                                                                  |
| Terbitkan                     | Ada draf tersimpan tanpa perubahan belum disimpan; minimal satu field. Konfirmasi menyebut versi berikutnya dan angkatan yang tetap di versi lama |
