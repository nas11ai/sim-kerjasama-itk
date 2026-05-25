# Navigation: Sidebar

**Versi:** 1.0  
**Status:** Draft

---

## Prinsip

Seluruh role — Researcher, Reviewer, Operator, Admin — menggunakan **satu komponen Shadcn Sidebar yang sama**. Tidak ada komponen sidebar berbeda per role. Visibility setiap nav item dikontrol oleh **permission Spatie**, bukan role langsung.

Keuntungan pendekatan ini:
- Satu komponen untuk di-maintain
- Dual-role user (Reviewer yang juga Researcher) tidak perlu switching context — semua nav tersedia sesuai permission yang dimiliki
- Menambah nav item baru cukup tambah satu permission check, tidak perlu update beberapa komponen

---

## Struktur Sidebar

```
┌─────────────────────────────┐
│  [Logo ITK]                 │
├─────────────────────────────┤
│                             │
│  Dashboard                  │
│                             │
│  ── Pengajuan ──────────    │  permission: submissions.view-own ATAU submissions.view-all
│    Daftar Pengajuan         │
│    Buat Pengajuan Baru      │  permission: submissions.create
│                             │
│  ── Review ─────────────    │  permission: reviewers.evaluate
│    Penugasan Review         │
│    Riwayat Review           │
│                             │
│  ── Monev ──────────────    │  permission: submissions.view-own ATAU submissions.view-all
│    Daftar Monev             │  (hanya tampil jika ada submission APPROVED)
│    Kelola Siklus Monev      │  permission: periods.manage
│                             │
│  Luaran Penelitian          │  permission: outputs.manage ATAU submissions.view-own
│                             │  (hanya tampil jika ada submission APPROVED)
│                             │
│  ── Manajemen ──────────    │  permission: submissions.view-all
│    Semua Pengajuan          │
│    Periode Pengajuan        │  permission: periods.manage
│    Reviewer                 │  permission: reviewers.assign
│    User & Verifikasi        │  permission: users.verify ATAU users.manage
│                             │
│  ── Laporan ────────────    │  permission: reporting.view ATAU reporting.export
│    Statistik                │
│    Export Data              │  permission: reporting.export
│    Audit Log                │  permission: reporting.view-audit-log
│                             │
│  ── Konfigurasi ────────    │  permission: forms.manage ATAU schemes.manage
│    Form Builder             │  permission: forms.manage
│    Skema Penelitian         │  permission: schemes.manage
│    Organisasi               │  permission: schemes.manage
│    Konfigurasi Umum         │  permission: schemes.manage
│                             │
├─────────────────────────────┤
│  [Avatar] Nama User         │  ← sticky bottom
│  [Pengaturan]  [Keluar]     │
└─────────────────────────────┘
```

---

## Permission Gate per Nav Item

| Nav Item | Permission yang Diperlukan | Kondisi Tambahan |
|----------|---------------------------|-----------------|
| Dashboard | — (semua authenticated) | — |
| Daftar Pengajuan | `submissions.view-own` atau `submissions.view-all` | — |
| Buat Pengajuan Baru | `submissions.create` | SubmissionPeriod terbuka |
| Penugasan Review | `reviewers.evaluate` | — |
| Riwayat Review | `reviewers.evaluate` | — |
| Daftar Monev | `submissions.view-own` | Ada submission APPROVED milik user |
| Kelola Siklus Monev | `periods.manage` | — |
| Luaran Penelitian | `submissions.view-own` | Ada submission APPROVED milik user |
| Semua Pengajuan | `submissions.view-all` | — |
| Periode Pengajuan | `periods.manage` | — |
| Reviewer | `reviewers.assign` | — |
| User & Verifikasi | `users.verify` atau `users.manage` | — |
| Statistik | `reporting.export` atau `reporting.view-audit-log` | — |
| Export Data | `reporting.export` | — |
| Audit Log | `reporting.view-audit-log` | — |
| Form Builder | `forms.manage` | — |
| Skema Penelitian | `schemes.manage` | — |
| Organisasi | `schemes.manage` | — |
| Konfigurasi Umum | `schemes.manage` | — |

> Referensi permission ke role mapping: `→ ddd/generic/02_identity_access.md` (tabel Spatie Permission Design)

---

## Bagian Bawah Sidebar (Sticky Bottom)

```
┌─────────────────────────────┐
│  [Avatar]  Nama Lengkap     │
│            nama@itk.ac.id   │
│                    [⚙] [→]  │  ← ikon pengaturan + logout
└─────────────────────────────┘
```

- **Avatar + Nama + Email** — link ke `/profile`
- **Ikon Pengaturan (⚙)** — link ke `/profile` (tab pengaturan)
- **Ikon Logout (→)** — trigger logout dengan konfirmasi singkat

Tidak ada dropdown di topbar untuk profil. Semua user action dipusatkan di sini.

---

## Grup dan Separator

Nav item dikelompokkan dengan separator dan label grup. Grup hanya ditampilkan jika **minimal satu item di dalamnya** visible untuk user tersebut. Jika semua item dalam satu grup tidak punya akses, separator dan label grup-nya juga disembunyikan.

Contoh: user yang hanya punya `submissions.view-own` dan `submissions.create` hanya melihat:

```
Dashboard
── Pengajuan ──
  Daftar Pengajuan
  Buat Pengajuan Baru
```

Tidak ada separator atau label grup lain yang muncul.

---

## State Khusus

### User Pending Verification

Sidebar **tidak ditampilkan**. User diarahkan ke halaman `/pending-verification` — full page tanpa layout sidebar.

### "Buat Pengajuan Baru" saat tidak ada period aktif

Item tetap tampil di sidebar, tapi saat diklik menampilkan pesan: "Tidak ada periode pengajuan yang sedang dibuka." Tidak di-disable di sidebar karena user perlu tahu alasannya.

### Conditional item (Monev & Luaran)

Item "Daftar Monev" dan "Luaran Penelitian" disembunyikan sepenuhnya dari sidebar jika user belum memiliki submission berstatus APPROVED. Saat pertama kali ada submission APPROVED, item muncul tanpa perlu reload manual (reactif).
