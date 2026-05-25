# IA: Budget

**Roles yang terlibat:** `Researcher` `Operator` `Admin`  
**DDD Context:** Budget  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

Budget tidak memiliki halaman standalone — diakses sebagai **tab atau section dalam halaman Submission**.

| # | Section | Lokasi | Accessible By |
|---|---------|--------|---------------|
| 1 | Tab Budget — Edit Mode | `/submissions/{id}/edit` (step Anggaran) | Researcher (submitted_by) |
| 2 | Tab Budget — View Mode | `/submissions/{id}` (section Anggaran) | Semua |

---

## Budget — Edit Mode

**Lokasi:** Step "Rencana Anggaran" di wizard Buat/Edit Pengajuan  
**Accessible by:** Researcher (submitted_by)  
**Kondisi akses:** Submission berstatus DRAFT, SUBMITTED, UNDER_REVIEW, NEEDS_REVISION, atau RESUBMITTED

### Konten Utama

Tabel dinamis budget line items. Setiap baris: komponen anggaran (dropdown), nama item, volume, satuan, harga satuan, total (auto-calculated). Baris bisa ditambah dan dihapus. Di bawah tabel: grand total yang auto-calculated dari SUM semua baris.

### Actions

| Aksi | Kondisi |
|------|---------|
| Tambah line item | Selalu (selama tidak locked) |
| Hapus line item | Selalu (selama tidak locked) |
| Edit field line item | Selalu (selama tidak locked) |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/01_budget.md#BR-BUD-01` — jika grand total melebihi `scheme.max_budget`, tampilkan warning merah di bawah grand total. Submit tidak diblok tapi user diperingatkan.
- `→ ddd/supporting/01_budget.md#BR-BUD-02` — status APPROVED/REJECTED/WITHDRAWN: seluruh section budget menjadi read-only, semua input di-disable.
- `→ ddd/supporting/01_budget.md#BR-BUD-05` — BudgetComponent yang `is_active: false` tidak muncul di dropdown pilihan.

---

## Budget — View Mode

**Lokasi:** Section "Rencana Anggaran" di Detail Pengajuan  
**Accessible by:** Semua role dengan akses ke submission tersebut

### Konten Utama

Tabel read-only budget line items. Grand total ditampilkan. Jika status locked, ditampilkan badge "Anggaran Terkunci".

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/01_budget.md#BR-BUD-08` — saat status NEEDS_REVISION, perubahan budget dicatat di audit trail. Operator/Admin bisa lihat history perubahan di tab Audit.
