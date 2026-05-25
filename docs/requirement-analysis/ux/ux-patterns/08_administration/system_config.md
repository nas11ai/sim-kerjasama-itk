# UX Pattern: Administration — System Configuration

**Roles yang terlibat:** `Admin`  
**DDD Context:** System Configuration  
**Pattern utama:** Simple CRUD List  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/08_administration/system_config.md`

---

## Konfigurasi Umum

**Pattern:** Simple CRUD List (per kategori)  
**Layout:** Sidebar + Main Content dengan Tab per kategori

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `ConfigCategoryTabs` | Tab per kategori master data (Tipe Jurnal, TRL, dll) | Main, atas |
| `ConfigItemList` | List item per kategori: nama, status, tombol edit dan nonaktifkan | Main |
| `ConfigItemForm` | Form inline atau drawer untuk tambah/edit item | Overlay |

### Interaction Notes

Semua kategori menggunakan struktur yang sama: list + tambah + edit + nonaktifkan. Tidak ada perbedaan pattern antar kategori — hanya field yang berbeda.

Tambah item baru menggunakan inline form di bawah list (bukan modal) untuk menjaga konteks — user bisa lihat list sambil mengisi form.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Cannot deactivate | Item sedang digunakan | Tombol disabled dengan tooltip penjelasan |
| Inactive item | is_active: false | Item ditampilkan dengan style muted dan badge "Nonaktif" |
| Empty | Belum ada item di kategori | Empty state dengan tombol "Tambah [nama kategori] Pertama" |
