# IA: Administration — User Management

**Roles yang terlibat:** `Operator` `Admin`  
**DDD Context:** Identity & Access  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page                    | Route                  | Accessible By   |
| --- | ----------------------- | ---------------------- | --------------- |
| 1   | Daftar User             | `/users`               | Operator, Admin |
| 2   | Antrian Verifikasi NIDN | `/users/pending`       | Operator, Admin |
| 3   | Detail User             | `/users/{id}`          | Operator, Admin |
| 4   | Manajemen Reviewer      | `/reviewers`           | Operator, Admin |
| 5   | Invitation Token        | `/admin/invitations`   | Admin           |
| 6   | Org Tree                | `/admin/organizations` | Admin           |

---

## Daftar User

**Route:** `/users`  
**Entry points:** Sidebar → Manajemen User → Daftar User

### Konten Utama

Tabel semua user: nama, email, NIDN, organisasi, role(s), status (active/pending/inactive). Filter: role, status, organisasi.

### Actions

| Aksi                     | Accessible By   | Kondisi                                                                                       |
| ------------------------ | --------------- | --------------------------------------------------------------------------------------------- |
| Lihat detail             | Operator, Admin | Selalu                                                                                        |
| Nonaktifkan user         | Operator, Admin | Status active, tidak ada active submission sebagai lead researcher — atau harus transfer dulu |
| Tambah direct permission | Admin           | Selalu                                                                                        |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/generic/02_identity_access.md#BR-IAM-10` — jika user punya active submission sebagai lead, nonaktifkan memunculkan warning dan flow transfer ownership wajib diselesaikan dulu.

---

## Antrian Verifikasi NIDN

**Route:** `/users/pending`  
**Entry points:** Sidebar → Manajemen User → Antrian Verifikasi NIDN (dengan badge counter)

### Konten Utama

Daftar user dengan status `pending`. Setiap item: nama, email, NIDN yang diinput, organisasi yang dipilih, tanggal registrasi.

### Actions

| Aksi                 | Kondisi                  |
| -------------------- | ------------------------ |
| Verifikasi (approve) | Selalu                   |
| Tolak (reject)       | Selalu, wajib isi alasan |

---

## Detail User

**Route:** `/users/{id}`  
**Entry points:** Klik item dari Daftar User

### Konten Utama

Profil lengkap user: data personal, organisasi, NIDN, status, role(s), dan direct permissions. Tab terpisah untuk: submission history, reviewer history (jika pernah jadi reviewer), dan activity log.

### Actions

| Aksi                    | Accessible By   | Kondisi       |
| ----------------------- | --------------- | ------------- |
| Edit role / permission  | Admin           | Selalu        |
| Nonaktifkan             | Operator, Admin | Status active |
| Tunjuk sebagai Reviewer | Operator, Admin | Status active |

---

## Manajemen Reviewer

**Route:** `/reviewers`  
**Entry points:** Sidebar → Manajemen Reviewer → Daftar Reviewer

### Konten Utama

Tabel semua reviewer: nama, tipe (internal/external), periode aktif (start - end date), workload saat ini. Filter: tipe, status (aktif/expired).

### Actions

| Aksi                              | Kondisi                                 |
| --------------------------------- | --------------------------------------- |
| Tambah reviewer baru              | Selalu — cari dari daftar user existing |
| Edit periode aktif                | Selalu                                  |
| Lihat detail & assignment history | Selalu                                  |

---

## Invitation Token

**Route:** `/admin/invitations`  
**Accessible by:** Admin only  
**Entry points:** Sidebar → Konfigurasi Sistem → (sub-item)

### Konten Utama

Tabel invitation token yang sudah dibuat: organisasi tujuan, permissions, max uses, used count, expires at, status. Form buat token baru.

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/generic/02_identity_access.md#BR-IAM-06` — token expired atau `used_count >= max_uses` ditampilkan dengan badge "Tidak Valid".

---

## Org Tree

**Route:** `/admin/organizations`  
**Accessible by:** Admin only

### Konten Utama

Visualisasi tree adjacency list organisasi. Node bisa di-expand/collapse. Aksi per node: tambah child, edit nama/tipe, nonaktifkan.

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/generic/02_identity_access.md#BR-IAM-08` — node yang masih memiliki UserProfile di subtree-nya tidak bisa dinonaktifkan. Tombol di-disable dengan tooltip.
