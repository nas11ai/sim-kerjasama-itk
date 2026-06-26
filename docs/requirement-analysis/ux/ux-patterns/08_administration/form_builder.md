# UX Pattern: Administration — Form Builder

**Roles yang terlibat:** `Admin`  
**DDD Context:** Form Engine  
**Pattern utama:** Simple CRUD List, Drag & Drop Builder  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/08_administration/form_builder.md`

---

## Edit Form — Fields

**Pattern:** Drag & Drop Builder  
**Layout:** Full Width (tidak perlu sidebar panel ekstra)

### Organisms

| Organism          | Deskripsi                                                                                                                  | Posisi           |
| ----------------- | -------------------------------------------------------------------------------------------------------------------------- | ---------------- |
| `FieldList`       | Daftar field yang bisa di-drag untuk reorder. Setiap item: drag handle, label, tipe, required badge, tombol edit dan hapus | Main             |
| `AddFieldPanel`   | Form inline untuk tambah field baru: label, tipe, required toggle, config tambahan per tipe                                | Main, bawah list |
| `FieldEditDrawer` | Drawer/slideover dari kanan untuk edit field existing secara detail                                                        | Overlay kanan    |

### Interaction Notes

Drag & drop reorder menggunakan visual feedback: item yang di-drag terangkat (shadow) dan gap muncul di posisi drop target.

Saat toggle `is_required` untuk field yang form-nya punya active drafts, muncul **dialog konfirmasi** sebelum perubahan disimpan:

> "Ada N pengajuan dalam status Draft yang menggunakan form ini. Perubahan ini akan memengaruhi validasi mereka. Kirim notifikasi ke researcher terdampak?"
> [Kirim Notifikasi] [Simpan Tanpa Notifikasi] [Batal]

### States

| State              | Trigger               | Tampilan                                                                                               |
| ------------------ | --------------------- | ------------------------------------------------------------------------------------------------------ |
| Empty              | Form baru tanpa field | Empty state dengan prompt "Tambah field pertama"                                                       |
| Soft-deleted field | Field di-delete       | Field masih tampil dengan style strikethrough dan badge "Dihapus (tersimpan)" — tidak hilang dari list |

### Business Rules yang Mempengaruhi UI

- `→ ddd/generic/01_form_engine.md#BR-FE-12` — field tidak bisa hard delete. Tombol hapus melakukan soft delete dan field masih tampil di list dengan visual berbeda.
- `→ ddd/generic/01_form_engine.md#BR-FE-13` — dialog konfirmasi notifikasi muncul saat is_required berubah dengan active drafts.
