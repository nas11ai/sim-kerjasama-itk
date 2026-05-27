# Navigation: Topbar

**Versi:** 1.0  
**Status:** Draft

---

## Struktur Topbar

```
┌─────────────────────────────────────────────────────────────────┐
│  [Logo ITK]     [🔍 Cari halaman, pengajuan, user...          ] │
└─────────────────────────────────────────────────────────────────┘
```

Topbar hanya berisi dua elemen:
- **Logo ITK** — klik kembali ke `/dashboard`
- **Global Search Bar** — akses cepat ke halaman, submission, atau user

Tidak ada profile dropdown, notifikasi bell, atau elemen lain di topbar. Semua dipindah ke sidebar.

---

## Global Search Bar

**Trigger:** Klik search bar atau shortcut `Ctrl+K` / `Cmd+K`

Search bar membuka **Command Palette** (Shadcn `<Command>`) — overlay modal di tengah layar dengan input dan hasil yang dikelompokkan.

### Scope Pencarian

| Grup | Hasil | Permission |
|------|-------|-----------|
| Navigasi | Halaman-halaman yang bisa diakses user (dari nav sidebar) | Sesuai permission user |
| Pengajuan | Submission by judul atau nama researcher | `submissions.view-own` atau `submissions.view-all` |
| User | User by nama atau NIDN | `users.verify` atau `users.manage` |
| Reviewer | Reviewer by nama | `reviewers.assign` |

### Behavior

- Hasil navigasi muncul **langsung** (client-side, dari list halaman yang tersedia) tanpa request ke server.
- Hasil submission dan user di-fetch via API dengan debounce 300ms.
- Setiap hasil menampilkan: ikon kategori, label utama, sublabel konteks (status, role, dll).
- Enter atau klik item → navigasi langsung ke halaman tersebut.
- Escape → tutup palette.

### Contoh Hasil

```
┌──────────────────────────────────────────┐
│  🔍 "penelitian dasar"                   │
├──────────────────────────────────────────┤
│  NAVIGASI                                │
│  📄  Buat Pengajuan Baru                 │
│  📋  Daftar Pengajuan                    │
├──────────────────────────────────────────┤
│  PENGAJUAN                               │
│  📁  Penelitian Dasar Material Komposit  │
│      UNDER_REVIEW · Ahmad Fauzi          │
│  📁  Penelitian Dasar Algoritma Robotik  │
│      APPROVED · Siti Rahayu             │
└──────────────────────────────────────────┘
```

---

## Catatan Implementasi

Shadcn menyediakan komponen `<Command>` yang bisa langsung dipakai untuk command palette ini. Shortcut `Ctrl+K` / `Cmd+K` cukup di-bind di root layout agar aktif dari halaman manapun.
