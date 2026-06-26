# UX Pattern: Administration — Scheme Management

**Roles yang terlibat:** `Admin`  
**DDD Context:** Scheme  
**Pattern utama:** Simple CRUD List, Key-Value Editor  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/08_administration/scheme.md`

---

## Buat / Edit Skema

**Pattern:** Simple Form dengan Key-Value Editor section  
**Layout:** Sidebar + Main Content

### Organisms

| Organism            | Deskripsi                                                                                         | Posisi       |
| ------------------- | ------------------------------------------------------------------------------------------------- | ------------ |
| `SchemeCoreForm`    | Form core rules: nama, kode, tipe skema, tipe submission, max budget, max members, durasi, status | Main, atas   |
| `SchemeRulesEditor` | Key-value editor untuk rules JSONB extensible                                                     | Main, tengah |
| `TrlMultiSelect`    | Multi-select allowed TRLs dari daftar yang tersedia                                               | Main, bawah  |

### Molecules yang Notable

- **`KeyValueEditor`** — list pasangan key/value yang bisa ditambah dan dihapus. Setiap baris: input key (text), input value (text/number tergantung key), tombol hapus. Tombol "Tambah Rule" di bawah list. Tidak perlu modal — inline saja.

### Interaction Notes

Field kode divalidasi uniqueness secara real-time (debounce 500ms setelah user berhenti mengetik) — inline indicator: "✓ Kode tersedia" atau "✗ Kode sudah dipakai".

### States

| State             | Trigger                     | Tampilan                                                                                         |
| ----------------- | --------------------------- | ------------------------------------------------------------------------------------------------ |
| Code available    | Kode unik (real-time check) | Inline badge hijau di samping field kode                                                         |
| Code taken        | Kode duplikat               | Inline error merah: "Kode sudah digunakan"                                                       |
| Cannot deactivate | Ada active submission       | Tombol "Nonaktifkan" disabled, tooltip: "Masih ada N pengajuan aktif yang menggunakan skema ini" |

### Business Rules yang Mempengaruhi UI

- `→ ddd/supporting/04_scheme.md#BR-SCH-01` — tombol nonaktifkan di-disable jika ada active submission.
- `→ ddd/supporting/04_scheme.md#BR-SCH-06` — penambahan rule JSONB baru tidak perlu migration — ditangani oleh `KeyValueEditor` langsung.
