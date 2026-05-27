# IA: Administration — Scheme Management

**Roles yang terlibat:** `Admin`  
**DDD Context:** Scheme  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| # | Page | Route | Accessible By |
|---|------|-------|---------------|
| 1 | Daftar Skema | `/admin/schemes` | Admin |
| 2 | Buat / Edit Skema | `/admin/schemes/{id}/edit` | Admin |

---

## Daftar Skema

**Route:** `/admin/schemes`  
**Entry points:** Sidebar → Konfigurasi Sistem → Skema Penelitian

### Konten Utama

Tabel semua scheme: nama, kode, tipe, max budget, max members, durasi, status (active/inactive), jumlah active submission yang menggunakannya.

### Actions

| Aksi | Kondisi |
|------|---------|
| Buat Skema Baru | Selalu |
| Edit Skema | Selalu |
| Nonaktifkan | Tidak ada active submission yang menggunakan skema ini |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/04_scheme.md#BR-SCH-01` — tombol nonaktifkan di-disable jika ada active submission, ditampilkan tooltip penjelasan.

---

## Buat / Edit Skema

**Route:** `/admin/schemes/{id}/edit`

### Konten Utama

**Core rules** (selalu ada): nama, kode, tipe skema, tipe submission, max budget, max members, durasi bulan, status.

**Extensible rules (JSONB):** UI berupa key-value editor — tampilkan pasangan key/value yang sudah ada, tombol tambah rule baru. Tidak perlu migration untuk menambah rule baru.

**Allowed TRLs:** multi-select dari daftar TRL yang tersedia.

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/04_scheme.md#BR-SCH-03` — kode harus unik, inline validation.
- `→ ddd/supporting/04_scheme.md#BR-SCH-06` — penambahan rule JSONB baru ditampilkan sebagai form sederhana (key + value) tanpa perlu ke database admin.
