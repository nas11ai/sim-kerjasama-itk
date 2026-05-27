# UX Documentation — SIMPAS v2

> Sistem Manajemen Penelitian dan Pengabdian Masyarakat  
> Institut Teknologi Kalimantan

## Tentang Dokumen Ini

Dokumen ini mendefinisikan **Information Architecture (IA)** dan **UX Patterns** untuk SIMPAS v2. Dibuat berdampingan dengan DDD documentation di `ddd/` — bukan pengganti, tapi kelanjutan dari sisi user experience.

Tujuannya: developer dan desainer bisa langsung tahu *halaman apa saja yang ada*, *siapa yang mengaksesnya*, dan *pola interaksi apa yang dipakai* — tanpa harus menebak-nebak dari kode.

---

## Hubungan dengan DDD Documentation

```
ddd/          → apa yang sistem lakukan (domain logic, business rules, events)
ux/           → bagaimana user berinteraksi dengan sistem tersebut
```

Dokumen UX ini **tidak menduplikasi business rules**. Setiap referensi ke business rule ditulis sebagai pointer ke file DDD yang relevan, misalnya `→ ddd/core/01_submission.md#BR-SM-05`.

---

## Struktur Dokumen

```
ux/
├── README.md                        ← dokumen ini
├── _templates/
│   ├── ia_template.md               ← template untuk file IA baru
│   └── ux_pattern_template.md       ← template untuk file UX Pattern baru
│
├── ia/                              ← Information Architecture
│   ├── 00_navigation/               ← struktur navigasi (satu sidebar, permission-based)
│   │   ├── sidebar.md               ← satu sidebar untuk semua role, visibility by permission
│   │   └── topbar.md                ← logo ITK + global search bar
│   ├── 01_auth/
│   ├── 02_submission/
│   ├── 03_review/
│   ├── 04_budget/
│   ├── 05_monev/
│   ├── 06_research_output/
│   ├── 07_reporting/
│   └── 08_administration/
│
└── ux-patterns/                     ← UX Patterns per feature area
    ├── 00_pattern_index.md          ← index semua pattern di seluruh sistem
    ├── 01_auth/
    ├── 02_submission/
    ├── 03_review/
    ├── 04_budget/
    ├── 05_monev/
    ├── 06_research_output/
    ├── 07_reporting/
    └── 08_administration/
```

---

## Urutan Membaca

### Jika kamu baru bergabung ke tim

1. Baca **`ia/00_navigation/`** untuk role yang relevan — pahami navigasi apa saja yang ada.
2. Baca **`ia/`** feature area yang sedang dikerjakan di sprint ini.
3. Baca **`ux-patterns/`** yang bersesuaian untuk tahu pola interaksi dan state yang harus diimplementasi.

### Jika kamu Frontend Developer

Fokus ke bagian **Organisms** dan **States** di setiap file `ux-patterns/`. Bagian ini mendefinisikan komponen apa yang dipakai dan kondisi UI apa yang harus di-handle (loading, empty, error, success).

### Jika kamu Designer

Mulai dari **`ia/`** untuk memahami halaman dan alur, kemudian **`ux-patterns/`** untuk layout dan interaction pattern. `ux-patterns/00_pattern_index.md` berguna sebagai referensi konsistensi antar halaman.

### Jika kamu Product Owner / Analis

Fokus ke **`ia/`** — khususnya bagian Page Inventory, Actions, dan referensi Business Rules per halaman.

---

## Cara Update

- **Halaman baru di feature area yang sudah ada** → update file `ia/` dan `ux-patterns/` yang relevan.
- **Feature area baru** → buat folder baru dengan nomor urut berikutnya, gunakan template di `_templates/`.
- **Navigation berubah** → update file `ia/00_navigation/` yang relevan.
- **Pattern baru dipakai** → daftarkan di `ux-patterns/00_pattern_index.md`.

---

## Konvensi

**Penamaan route** → gunakan format Laravel/Inertia: `/submissions`, `/submissions/{id}`, `/submissions/create`  
**Nama role** → `Researcher`, `Reviewer`, `Operator`, `Admin` (kapital, konsisten dengan DDD)  
**Referensi DDD** → format `→ ddd/{path}#{kode-rule}`, contoh: `→ ddd/core/01_submission.md#BR-SM-02`  
**Nama pattern** → konsisten dengan yang terdaftar di `ux-patterns/00_pattern_index.md`

---

## Design System

SIMPAS v2 menggunakan **Shadcn/UI** sebagai base component library di atas **Vue 3 + Inertia.js**. Design tokens (warna, tipografi, spacing) mengikuti Shadcn defaults — tidak ada custom token tambahan kecuali dinyatakan eksplisit.

Atomic Design digunakan sebagai mental model untuk membangun komponen:

```
Atoms      → Shadcn base components (Button, Input, Badge, dll)
Molecules  → Kombinasi atoms dengan satu fungsi (FormField, StatusBadge, dll)
Organisms  → Section yang berdiri sendiri (SubmissionForm, ReviewPanel, dll)
Templates  → Layout skeleton per halaman
Pages      → Template + data nyata (implementasi di Vue/Inertia)
```
