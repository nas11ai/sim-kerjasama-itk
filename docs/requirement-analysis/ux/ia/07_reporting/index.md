# IA: Reporting

**Roles yang terlibat:** `Operator` `Admin` `Researcher` (terbatas)  
**DDD Context:** Reporting  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| # | Page | Route | Accessible By |
|---|------|-------|---------------|
| 1 | Dashboard Statistik | `/reporting/stats` | Operator, Admin |
| 2 | Export Data | `/reporting/export` | Operator, Admin |
| 3 | Audit Log | `/reporting/audit` | Operator, Admin |
| 4 | Audit Log — Submission Saya | `/submissions/{id}/audit` | Researcher (own submission) |

---

## Dashboard Statistik

**Route:** `/reporting/stats`  
**Accessible by:** Operator, Admin  
**Entry points:**
- Sidebar nav → Laporan & Export → Dashboard Statistik
- Widget dari Dashboard utama (drill-down)

**Exit points:**
- → Daftar Submission (klik angka/chart untuk filter)

### Konten Utama

Live query — tidak ada caching. Statistik yang ditampilkan:

- Jumlah submission per status (chart donut atau bar)
- Submission per skema per tahun
- Submission per fakultas / prodi
- Total anggaran per periode
- Jumlah luaran per tipe
- Distribusi reviewer per submission

Filter tersedia: rentang tahun, periode, skema.

### Actions

| Aksi | Kondisi |
|------|---------|
| Ganti filter | Selalu |
| Drill-down ke daftar | Klik segmen chart |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/05_reporting.md#BR-RPT-01` — read-only, tidak ada aksi yang mengubah state.
- `→ ddd/supporting/05_reporting.md#BR-RPT-06` — data di-query live, tidak ada cache layer.

---

## Export Data

**Route:** `/reporting/export`  
**Accessible by:** Operator, Admin  
**Entry points:**
- Sidebar nav → Laporan & Export → Export Data

**Exit points:**
- Tetap di halaman (job di-dispatch, user tunggu notifikasi)

### Konten Utama

Dua area: form request export baru dan tabel riwayat export sebelumnya.

**Form request export:**
- Pilih jenis export: Rekap Submission / Detail Anggaran / Daftar Luaran / Rekap Monev / Audit Log
- Filter parameter: rentang tahun, skema, status
- Pilih format: Excel atau CSV
- Tombol "Request Export"

**Riwayat export:**
- Tabel export yang sudah/sedang diproses: jenis, parameter, status job (queued / processing / done / failed), waktu selesai, link download (valid 24 jam)

### Actions

| Aksi | Kondisi |
|------|---------|
| Request export baru | Selalu |
| Download hasil export | Job selesai, link belum expired (< 24 jam) |
| Request ulang (jika gagal) | Job status: failed |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/05_reporting.md#BR-RPT-02` — semua export via background job, tidak ada loading synchronous.
- `→ ddd/supporting/05_reporting.md#BR-RPT-03` — link download expired setelah 24 jam. Item di riwayat tetap ada tapi tombol download berubah jadi "Expired — Request Ulang".
- `→ ddd/supporting/05_reporting.md#BR-RPT-08` — jika job gagal setelah 3 retry, status berubah ke "Gagal" dengan pesan dan tombol "Coba Lagi".

---

## Audit Log — Operator / Admin View

**Route:** `/reporting/audit`  
**Accessible by:** Operator, Admin  
**Entry points:**
- Sidebar nav → Laporan & Export → Audit Log

### Konten Utama

Tabel semua aktivitas di sistem. Kolom: waktu, subjek (submission/user/dll), aksi, pelaku (causer), IP address. Filter tersedia: rentang waktu, tipe aksi, subjek, pelaku.

### Actions

| Aksi | Kondisi |
|------|---------|
| Filter log | Selalu |
| Export audit log | Redirect ke Export Data dengan jenis pre-selected |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/05_reporting.md#BR-RPT-04` — tidak ada tombol hapus. Audit log append-only.
- `→ ddd/supporting/05_reporting.md#BR-RPT-05` — Operator bisa lihat log seluruh sistem.

---

## Audit Log — Researcher View

**Route:** `/submissions/{id}/audit`  
**Accessible by:** Researcher (own submission)  
**Entry points:**
- Tab "Riwayat" di Detail Submission

### Konten Utama

Subset audit log — hanya aktivitas yang berkaitan dengan submission ini. Tidak ada IP address atau informasi internal yang ditampilkan ke researcher.

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/05_reporting.md#BR-RPT-05` — researcher hanya bisa lihat audit log submission miliknya.
