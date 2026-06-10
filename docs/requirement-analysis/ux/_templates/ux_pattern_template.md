# UX Pattern: [Feature Area Name]

**Roles yang terlibat:** `Researcher` `Reviewer` `Operator` `Admin`  
**DDD Context:** [nama BC]  
**Pattern utama:** [Wizard / Table + Detail / Split Panel / Dashboard / dll]  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/[nomor]_[nama]/index.md`

---

## [Nama Page / Screen]

**Pattern:** [nama pattern — lihat `ux-patterns/00_pattern_index.md` untuk daftar yang tersedia]  
**Layout:** [contoh: Sidebar + Main Content / Full Width / Split Panel 50-50 / dll]  
**Accessible by:** [role]

### Organisms

Komponen-komponen level organism yang menyusun halaman ini.

| Organism       | Deskripsi       | Posisi di Layout   |
| -------------- | --------------- | ------------------ |
| [NamaOrganism] | [Fungsinya apa] | Main content, atas |
| [NamaOrganism] | [Fungsinya apa] | Sidebar kanan      |

### Molecules yang Notable

Molecules yang punya behavior spesifik atau perlu perhatian khusus saat implementasi.

- **[NamaMolecule]** — [kenapa perlu dicatat, behavior-nya apa]
- **[NamaMolecule]** — [kenapa perlu dicatat]

### Interaction Notes

[Bagaimana user berinteraksi dengan halaman ini. Fokus ke hal yang tidak obvious — inline edit, drag & drop, optimistic update, auto-save, konfirmasi sebelum aksi destructive, dsb.]

### States

Semua state yang mungkin terjadi di halaman ini dan bagaimana UI meresponsnya.

| State              | Trigger                     | Tampilan                                    |
| ------------------ | --------------------------- | ------------------------------------------- |
| Loading            | Halaman pertama kali dibuka | Skeleton loader di area konten utama        |
| Empty              | Tidak ada data              | Empty state dengan ilustrasi + CTA          |
| Error (fetch)      | API gagal                   | Error banner + tombol retry                 |
| Error (form)       | Validasi gagal              | Inline error per field                      |
| Success            | Aksi berhasil               | Toast notification                          |
| Locked / Read-only | [kondisi bisnis tertentu]   | Field di-disable, tombol aksi disembunyikan |

### Business Rules yang Mempengaruhi UI

- `→ ddd/[path]/[file].md#[kode-rule]` — [dampak konkret ke UI: apa yang berubah di tampilan ketika rule ini aktif]
- `→ ddd/[path]/[file].md#[kode-rule]` — [dampak konkret]

---

## [Nama Page / Screen berikutnya]

[Ulangi struktur di atas untuk setiap halaman dalam feature area ini]

---

## Shared Molecules dalam Feature Area Ini

[Molecules yang dipakai di lebih dari satu halaman dalam feature area ini — definisikan sekali di sini daripada diulang di setiap section]

| Molecule       | Dipakai di     | Deskripsi   |
| -------------- | -------------- | ----------- |
| [NamaMolecule] | Page A, Page B | [Deskripsi] |

---

## Catatan

[Open questions, keputusan desain yang perlu dikonfirmasi, trade-off, edge case yang belum jelas penanganannya]
