# UX Pattern: Reporting

**Roles yang terlibat:** `Operator` `Admin` `Researcher` (terbatas)  
**DDD Context:** Reporting  
**Pattern utama:** Dashboard — Statistics, Table + Filter, Background Job + Notifikasi  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/07_reporting/index.md`

---

## Dashboard Statistik

**Pattern:** Dashboard — Statistics  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `StatsSummaryCards` | 4–6 kartu angka besar: total submission aktif, per status key, dll | Main, baris pertama |
| `StatsFilterBar` | Filter: rentang tahun, periode, skema | Main, atas |
| `SubmissionByStatusChart` | Donut chart atau bar chart submission per status | Main, kiri |
| `SubmissionBySchemeChart` | Bar chart submission per skema per tahun | Main, kanan |
| `SubmissionByOrgTable` | Tabel submission per fakultas/prodi dengan drill-down | Main, bawah |
| `OutputByTypeChart` | Bar chart luaran per tipe | Main, bawah |

### Interaction Notes

Semua chart dan tabel di-query live saat filter berubah. Loading state per widget — tidak semua reload bersamaan. Klik segment chart atau row tabel untuk drill-down ke daftar submission dengan filter pre-applied.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Loading | Filter berubah | Skeleton per widget |
| No data | Filter terlalu spesifik | Widget menampilkan "Tidak ada data" (bukan full empty state) |

---

## Export Data

**Pattern:** Background Job + Notifikasi  
**Layout:** Sidebar + Main Content, dua section

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `ExportRequestForm` | Form: pilih jenis, filter parameter, format | Main, atas |
| `ExportHistoryTable` | Tabel riwayat export: jenis, status, waktu, link download | Main, bawah |

### Interaction Notes

Setelah klik "Request Export", tombol berubah jadi "Memproses..." selama 1–2 detik (saat job di-dispatch ke queue), lalu kembali normal. Tidak ada loading panjang — user bisa langsung lanjut kerja.

Entry baru muncul di `ExportHistoryTable` dengan status "Diproses" segera setelah dispatch (optimistic). Status update via polling ringan (setiap 10 detik) atau WebSocket jika tersedia.

Saat selesai: notifikasi in-app muncul (bell icon di topbar) + status di tabel berubah ke "Selesai" + link download aktif.

### States per Export Job

| State | Visual di Tabel |
|-------|----------------|
| Queued | Badge abu-abu "Antrian" + spinner |
| Processing | Badge kuning "Memproses" + spinner |
| Done | Badge hijau "Selesai" + tombol download |
| Failed | Badge merah "Gagal" + tombol "Coba Lagi" |
| Expired | Badge abu-abu "Kadaluarsa" + tombol "Request Ulang" |

### Business Rules yang Mempengaruhi UI

- `→ ddd/supporting/05_reporting.md#BR-RPT-03` — link download expired setelah 24 jam. Ditampilkan sisa waktu: "Kadaluarsa dalam 3 jam".
- `→ ddd/supporting/05_reporting.md#BR-RPT-08` — setelah 3x retry gagal, status jadi "Gagal" permanen dengan tombol "Request Ulang" (bukan retry otomatis lagi).

---

## Audit Log

**Pattern:** Table + Filter  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `AuditLogFilter` | Filter: rentang waktu, tipe aksi (multiselect), pelaku (search) | Main, atas |
| `AuditLogTable` | Tabel: waktu, subjek, aksi, pelaku, IP (hanya Operator/Admin) | Main |

### Interaction Notes

Filter rentang waktu menggunakan date range picker. Tipe aksi menggunakan multiselect dropdown dengan label yang human-readable (bukan kode teknis): "Pengajuan Disubmit", "Reviewer Di-assign", dll.

Row tabel bisa di-expand untuk melihat detail perubahan (old value / new value) jika ada, misalnya untuk aksi "Anggaran Diubah".

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Loading | Filter berubah | Skeleton rows |
| Empty | Tidak ada log di rentang waktu | Empty state: "Tidak ada aktivitas di periode ini" |
