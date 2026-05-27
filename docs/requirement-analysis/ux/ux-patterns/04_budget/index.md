# UX Pattern: Budget

**Roles yang terlibat:** `Researcher` `Operator` `Admin`  
**DDD Context:** Budget  
**Pattern utama:** Dynamic Table (Editable Rows)  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/04_budget/index.md`

---

## Budget — Edit Mode

**Pattern:** Dynamic Table (Editable Rows)  
**Layout:** Embedded dalam Wizard Step atau tab di Detail Submission

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `BudgetTable` | Tabel editable dengan baris dinamis | Main |
| `GrandTotalBar` | Bar yang menampilkan grand total computed + perbandingan dengan max budget skema | Main, bawah tabel |
| `AddLineItemButton` | Tombol tambah baris baru di bawah tabel | Main, bawah tabel |

### Molecules yang Notable

- **`BudgetLineItemRow`** — satu baris tabel: dropdown komponen anggaran, input nama item, input volume (number), input satuan (text), input harga satuan (currency), kolom total (auto-calculated, read-only). Tombol hapus di ujung kanan baris.

### Interaction Notes

**Auto-calculate:** total per baris = volume × harga satuan, dikalkulasi **client-side on-change** — langsung update tanpa request ke server. Grand total juga update otomatis.

**Currency formatting:** harga satuan dan total diformat sebagai Rupiah (Rp) dengan pemisah ribuan. Input menerima angka saja, display dengan format.

**Hapus baris:** konfirmasi inline (bukan modal) — tombol hapus pertama kali berubah jadi "Yakin hapus?" dengan tombol konfirmasi kecil di sampingnya. Tidak perlu modal untuk aksi non-destructive ini.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Empty | Belum ada line item | Satu baris kosong default + teks guide "Tambah komponen anggaran" |
| Over budget | Grand total > scheme.max_budget | `GrandTotalBar` berubah merah, warning: "Total anggaran melebihi batas skema (Rp [max])" |
| Within budget | Grand total ≤ scheme.max_budget | `GrandTotalBar` normal/hijau |
| Locked | Status APPROVED / REJECTED / WITHDRAWN | Semua input disabled, tombol hapus dan tambah disembunyikan, banner "Anggaran Terkunci" |

### Business Rules yang Mempengaruhi UI

- `→ ddd/supporting/01_budget.md#BR-BUD-01` — warning over budget ditampilkan tapi tidak memblok submit.
- `→ ddd/supporting/01_budget.md#BR-BUD-02` — status terminal: seluruh tabel jadi read-only.
- `→ ddd/supporting/01_budget.md#BR-BUD-03` — status NEEDS_REVISION: tabel tetap editable.
- `→ ddd/supporting/01_budget.md#BR-BUD-05` — komponen dengan `is_active: false` tidak muncul di dropdown komponen anggaran.
