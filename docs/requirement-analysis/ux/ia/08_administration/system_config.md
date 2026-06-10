# IA: Administration — System Configuration

**Roles yang terlibat:** `Admin`  
**DDD Context:** System Configuration  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| #   | Page             | Route           | Accessible By |
| --- | ---------------- | --------------- | ------------- |
| 1   | Konfigurasi Umum | `/admin/config` | Admin         |

---

## Konfigurasi Umum

**Route:** `/admin/config`  
**Entry points:** Sidebar → Konfigurasi Sistem → Konfigurasi Umum

### Konten Utama

Master data yang digunakan lintas sistem, ditampilkan dalam tab atau accordion per kategori:

| Kategori                         | Deskripsi                                               |
| -------------------------------- | ------------------------------------------------------- |
| Tipe Jurnal                      | Referensi untuk Research Output tipe artikel            |
| Tipe HKI                         | Referensi untuk Research Output tipe IP                 |
| Technology Readiness Level (TRL) | Digunakan di Scheme allowed TRLs dan trl_selector field |
| Tipe Output Penelitian           | Definisi tipe luaran yang valid                         |
| Tipe Submission                  | Penelitian vs Pengabdian Masyarakat                     |
| Tipe Skema                       | Kategori skema (DIPA, Mandiri, dll)                     |
| Tipe Organisasi                  | Kategori node di org tree                               |
| Komponen Anggaran                | Kategori untuk Budget line items                        |

### Actions

| Aksi                    | Kondisi                                     |
| ----------------------- | ------------------------------------------- |
| Tambah item master data | Per kategori, selalu                        |
| Edit item               | Selalu                                      |
| Nonaktifkan item        | Item tidak sedang digunakan oleh data aktif |

### Catatan

Halaman ini adalah CRUD sederhana untuk master data. Tidak ada business logic kompleks di sini — ini murni referential data yang di-consume oleh BC lain.
