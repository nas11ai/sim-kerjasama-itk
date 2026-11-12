# IA: Administration — Form Builder

**Roles yang terlibat:** `Admin`  
**DDD Context:** Form Engine  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page                                | Route                               | Accessible By |
| --- | ----------------------------------- | ----------------------------------- | ------------- |
| 1   | Daftar Form                         | `/admin/forms`                      | Admin         |
| 2   | Edit Form — General & Fields        | `/admin/forms/{id}/edit`            | Admin         |
| 3   | Edit Form — Access Control          | `/admin/forms/{id}/access`          | Admin         |
| 4   | Fase — pustaka                      | `/admin/phases` (`?form={id}`)      | Admin         |
| 5   | Edit Phase — Details & Review Forms | `/admin/phases/{phase_id}/edit`     | Admin         |
| 6   | Form Evaluasi — pustaka             | `/admin/evaluation-forms`           | Admin         |
| 7   | Edit Form Evaluasi                  | `/admin/evaluation-forms/{id}/edit` | Admin         |

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

**Section General:** judul form, deskripsi, tipe form, status aktif — disimpan langsung (bukan isi berdraf).

**Section Fields** (BR-FE-34): daftar FormField yang bisa di-reorder (drag & drop), tambah field baru, edit field existing. Setiap field menampilkan: label, tipe (text/number/select/file/scheme_selector/dll), required atau opsional, `required_since`. Field disimpan sebagai **draf**; peneliti tetap melihat field terbitan terakhir sampai Admin menekan **Terbitkan**. Tab **Pratinjau** merender isi editor persis seperti isian peneliti (G3-W8).

Banner draf: "Draf belum diterbitkan. Peneliti masih melihat field terbitan terakhir." — atau, untuk Form yang belum pernah terbit, "Draf belum diterbitkan. Form ini belum bisa dipakai tahap." Daftar Form memberi lencana "Belum terbit".

### Actions

| Aksi                        | Kondisi                                                    |
| --------------------------- | ---------------------------------------------------------- |
| Simpan (General)            | Selalu                                                     |
| Tambah / edit / reorder field | Selalu, masuk ke draf                                    |
| Soft delete field           | Selalu (tidak bisa hard delete), masuk ke draf             |
| Simpan draf                 | Selalu                                                     |
| Buang draf                  | Ada draf                                                   |
| Terbitkan                   | Ada draf tersimpan tanpa perubahan belum disimpan          |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/generic/01_form_engine.md#BR-FE-34` — konfirmasi Terbitkan memuat ringkasan dampak, satu kalimat per butir yang ada: "{a} field baru wajib — berlaku untuk pengajuan yang dibuat sesudah ini.", "{d} draf berjalan tidak terpengaruh.", "Label "{label}" diubah pada field yang sudah dijawab {k} pengajuan.", "{r} field dihapus — jawaban lamanya tetap tersimpan."; tanpa butir: "Terbitkan perubahan Form ini?".
- `→ ddd/generic/01_form_engine.md#BR-FE-34` — mengubah label field atau opsi yang sudah dijawab memunculkan peringatan di dialog field: "Hanya untuk memperbaiki ketikan. Kalau pertanyaannya berbeda, tambahkan field baru — jawaban lama tetap tercatat di field lama."
- `→ ddd/generic/01_form_engine.md#BR-FE-12` — field tidak bisa dihapus permanen, hanya soft delete. Tombol "Hapus" memicu konfirmasi.
- `→ ddd/generic/01_form_engine.md#BR-FE-13` — saat ada active drafts, dialog Terbitkan menawarkan centang "Kirim notifikasi ke N peneliti terdampak".
- `→ ddd/generic/01_form_engine.md#BR-FE-11` — field yang terbit wajib berlaku sejak terbit (`created_at`/`required_since` = waktu terbit), bukan sejak diketik di draf.

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
jumlah FormPhaseDetail, periode yang terhubung, celah jadwal per periode, label versi terbaru (atau
lencana "Belum terbit"), dan banner bila ada draf (BR-FE-33). Tapis `?form=` menampilkan fase yang
langkah mana pun — di versi terbaru atau di drafnya — memakai Form itu.

### Actions

| Aksi                  | Kondisi                                                      |
| --------------------- | ------------------------------------------------------------ |
| Buat Phase Baru       | Selalu                                                       |
| Edit Phase            | Selalu                                                       |
| Link Phase ke Periode | Punya `periods.manage`; tahap sudah pernah terbit (BR-FE-33) |

---

## Edit Phase — Details & Review Forms

**Route:** `/admin/phases/{phase_id}/edit` (buat: `/admin/phases/create`)  
**Entry points:**

- Klik "Edit" dari pustaka fase

### Konten Utama

Kartu Tahap (judul, deskripsi, status) disimpan langsung — bukan isi berversi. Struktur tahap
disunting sebagai **draf** (BR-FE-33) dalam tiga tab:

- **Langkah** — daftar FormPhaseDetail draf (ordered): tipe, aturan akses, jenis langkah (nama
  tenggat), flag `needs_review`. Langkah `needs_review` memuat Form Evaluasi terpasangnya beserta
  label versi dan wajib/aktif per pemasangan; isinya disusun di pustaka Form Evaluasi (BR-FE-30).
- **Pratinjau** — alur langkah baca-saja dari isi editor (G3-W8).
- **Versi & pemakaian** — riwayat versi terbit dan jumlah angkatan yang terpaku pada tiap versi (BR-FE-32).

Banner draf: "Draf belum diterbitkan. Angkatan baru masih memakai {Versi n}." atau, sebelum pernah
terbit, "Draf belum diterbitkan. Tahap ini belum bisa dipakai periode."

### Actions

| Aksi                                           | Kondisi                                                                     |
| ---------------------------------------------- | --------------------------------------------------------------------------- |
| Simpan Tahap (judul/deskripsi/status)          | Selalu                                                                      |
| Tambah / edit / hapus langkah                  | Selalu — berlaku sejak versi berikutnya                                     |
| Pasang Form Evaluasi                           | Langkah `needs_review`; Form Evaluasi sudah terbit, belum terpasang di sana |
| Jadikan wajib / nonaktifkan / lepas pemasangan | Ada pemasangan — berlaku sejak versi berikutnya                             |
| Simpan draf                                    | Selalu                                                                      |
| Buang draf                                     | Ada draf                                                                    |
| Terbitkan (konfirmasi dampak)                  | Ada draf tersimpan, tanpa perubahan yang belum disimpan                     |

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

| Aksi                          | Kondisi                                                                                                                                           |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------- |
| Simpan nama                   | Selalu                                                                                                                                            |
| Tambah / edit / reorder field | Selalu (masuk draf)                                                                                                                               |
| Simpan draf                   | Selalu                                                                                                                                            |
| Buang draf                    | Ada draf                                                                                                                                          |
| Terbitkan                     | Ada draf tersimpan tanpa perubahan belum disimpan; minimal satu field. Konfirmasi menyebut versi berikutnya dan angkatan yang tetap di versi lama |
