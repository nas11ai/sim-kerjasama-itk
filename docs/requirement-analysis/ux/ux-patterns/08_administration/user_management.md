# UX Pattern: Administration — User Management

**Roles yang terlibat:** `Operator` `Admin`  
**DDD Context:** Identity & Access  
**Pattern utama:** Table + Filter, Tree View  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/08_administration/user_management.md`

---

## Daftar User

**Pattern:** Table + Filter  
**Layout:** Sidebar + Main Content

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `UserFilter` | Filter: role, status, organisasi (OrgTreePicker) | Main, atas |
| `UserTable` | Tabel: nama, email, NIDN, org, role(s), status | Main |

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Deactivate — has active submission | Klik nonaktifkan user dengan active submission | `ConfirmDialog` dengan warning: "User ini memiliki N pengajuan aktif sebagai ketua peneliti. Selesaikan transfer kepemilikan terlebih dahulu." + tombol "Pergi ke Transfer" |

### Business Rules yang Mempengaruhi UI

- `→ ddd/generic/02_identity_access.md#BR-IAM-10` — flow transfer ownership wajib sebelum deaktivasi bisa dikonfirmasi.

---

## Org Tree

**Pattern:** Tree View  
**Layout:** Full Width (konten utama, tanpa sidebar ekstra)

### Organisms

| Organism | Deskripsi | Posisi |
|----------|-----------|--------|
| `OrgTreeView` | Visualisasi hierarki adjacency list, node bisa expand/collapse | Main |
| `OrgNodeActions` | Dropdown aksi per node: Tambah Child, Edit, Nonaktifkan | Inline per node (muncul on hover) |
| `OrgNodeForm` | Form inline atau drawer untuk tambah/edit node | Overlay |

### Interaction Notes

Klik node untuk expand/collapse subtree. Node yang dinonaktifkan ditampilkan dengan style muted/strikethrough — tetap terlihat di tree tapi dibedakan secara visual.

Expand/collapse state disimpan di local state (tidak di server) — user bebas navigasi tree tanpa kehilangan posisi.

### States

| State | Trigger | Tampilan |
|-------|---------|----------|
| Cannot deactivate | Node punya UserProfile di subtree | Tombol "Nonaktifkan" disabled, tooltip: "Masih ada user aktif di unit ini" |
| Inactive node | Node `is_active: false` | Style muted, label dengan badge "Nonaktif" |
