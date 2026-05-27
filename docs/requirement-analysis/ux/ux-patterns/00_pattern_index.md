# UX Pattern Index

> Bird's eye view semua pattern yang digunakan di SIMPAS v2. Gunakan ini sebagai referensi konsistensi — sebelum memutuskan pattern untuk halaman baru, cek apakah ada pattern yang sudah dipakai untuk kasus serupa.

---

## Pattern yang Digunakan

### Wizard (Multi-step Form)

Form panjang yang dipecah menjadi beberapa langkah berurutan dengan progress indicator.

| Halaman | File |
|---------|------|
| Buat Pengajuan | `ux-patterns/02_submission/index.md` |
| Edit Pengajuan (Draft) | `ux-patterns/02_submission/index.md` |
| Tambah Luaran (2-step) | `ux-patterns/06_research_output/index.md` |

---

### Table + Filter

Tabel data dengan kemampuan filter, sort, dan search. Digunakan untuk halaman list yang memiliki banyak data dan butuh navigasi.

| Halaman | File |
|---------|------|
| Daftar Pengajuan (Operator) | `ux-patterns/02_submission/index.md` |
| Daftar Pengajuan (Researcher) | `ux-patterns/02_submission/index.md` |
| Daftar Reviewer | `ux-patterns/08_administration/user_management.md` |
| Daftar User | `ux-patterns/08_administration/user_management.md` |
| Audit Log | `ux-patterns/07_reporting/index.md` |
| Riwayat Export | `ux-patterns/07_reporting/index.md` |

---

### Split Panel

Layar dibagi dua secara vertikal: konten referensi di kiri, form aksi di kanan. Digunakan saat user perlu baca dan menulis sekaligus.

| Halaman | File |
|---------|------|
| Form Evaluasi Reviewer | `ux-patterns/03_review/index.md` |
| Halaman Revisi (Researcher) | `ux-patterns/02_submission/index.md` |

---

### Detail Page dengan Tabs

Halaman detail satu entitas yang kontennya dibagi ke beberapa tab berdasarkan kategori informasi.

| Halaman | File |
|---------|------|
| Detail Pengajuan | `ux-patterns/02_submission/index.md` |
| Detail User | `ux-patterns/08_administration/user_management.md` |

---

### Threaded Comments

Komentar bersarang (nested) dengan kemampuan reply. Parent comment di-indent, reply di-indent lebih dalam.

| Halaman | File |
|---------|------|
| Review Summary & Diskusi | `ux-patterns/03_review/index.md` |

---

### Phase Progression

Visual linear yang menampilkan tahapan dari suatu proses beserta status setiap tahap (belum aktif / aktif / selesai / expired).

| Halaman | File |
|---------|------|
| Daftar Monev (Researcher) | `ux-patterns/05_monev/index.md` |
| Aktivasi Siklus Monev (Operator) | `ux-patterns/05_monev/index.md` |

---

### Dynamic Table (Editable Rows)

Tabel yang baris-barisnya bisa ditambah, dihapus, dan diedit inline. Digunakan untuk input data koleksi dengan kalkulasi otomatis.

| Halaman | File |
|---------|------|
| Rencana Anggaran (Budget) | `ux-patterns/04_budget/index.md` |
| Research Members input | `ux-patterns/02_submission/index.md` |

---

### Dashboard — Statistics

Halaman overview dengan kombinasi angka ringkasan (summary cards) dan chart. Data di-load live, tidak di-cache.

| Halaman | File |
|---------|------|
| Dashboard Statistik (Operator) | `ux-patterns/07_reporting/index.md` |
| Dashboard (semua role) | `ux-patterns/01_auth/index.md` |

---

### Tree View

Visualisasi hierarki parent-child yang bisa di-expand dan collapse per node.

| Halaman | File |
|---------|------|
| Org Tree | `ux-patterns/08_administration/user_management.md` |
| OrgTreePicker (komponen) | `ux-patterns/01_auth/index.md` |

---

### Key-Value Editor

Form untuk mengedit data semi-structured dalam format pasangan key-value. Digunakan untuk JSONB config yang extensible.

| Halaman | File |
|---------|------|
| Edit Skema — Extensible Rules | `ux-patterns/08_administration/scheme.md` |
| Konfigurasi Umum | `ux-patterns/08_administration/system_config.md` |

---

### Background Job + Notifikasi

Aksi yang diproses secara async: user klik trigger, dapat konfirmasi bahwa job sudah di-queue, lalu mendapat notifikasi in-app saat selesai.

| Halaman | File |
|---------|------|
| Export Data | `ux-patterns/07_reporting/index.md` |
| Cetak PDF (dokumen kompleks) | `ux-patterns/07_reporting/index.md` |

---

### Simple CRUD List

Halaman daftar sederhana dengan tambah, edit, dan nonaktifkan. Tanpa filter kompleks atau pagination besar.

| Halaman | File |
|---------|------|
| Konfigurasi Umum (per kategori) | `ux-patterns/08_administration/system_config.md` |
| Daftar Form | `ux-patterns/08_administration/form_builder.md` |
| Daftar Skema | `ux-patterns/08_administration/scheme.md` |
| Invitation Token | `ux-patterns/08_administration/user_management.md` |

---

## Shared Molecules Lintas Fitur

Komponen molecule yang dipakai di banyak tempat — didefinisikan sekali di sini sebagai referensi.

| Molecule | Dipakai di | Deskripsi |
|----------|-----------|-----------|
| `StatusBadge` | Submission, Review, Monev, Luaran | Badge warna berbeda per status dengan label teks |
| `OrgTreePicker` | Register, Form Builder (access control) | Dropdown dengan tree structure untuk pilih organisasi |
| `UserSearchPicker` | Research Members, Assign Reviewer, Transfer Ownership | Search user by nama/NIDN, tampilkan hasil sebagai list selectable |
| `FileUploadZone` | Submission Wizard, Tambah Luaran, Laporan Monev | Area drag & drop upload file dengan preview dan remove |
| `DeadlineCountdown` | Daftar Monev, Penugasan Review, Form Evaluasi | Tampilan deadline dengan warna berubah saat mendekati batas |
| `EmptyState` | Semua halaman list | Ilustrasi + teks + CTA opsional untuk kondisi data kosong |
| `ConfirmDialog` | Semua aksi destructive | Modal konfirmasi dengan deskripsi aksi dan tombol confirm/cancel |
| `AuditTimeline` | Detail Submission, Detail User | Timeline vertikal status history dengan timestamp dan causer |
