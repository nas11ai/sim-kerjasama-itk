# UX Pattern: Submission

**Roles yang terlibat:** `Researcher` `Operator` `Admin`  
**DDD Context:** Submission, Form Engine  
**Pattern utama:** Wizard, Table + Filter, Detail Page dengan Tabs, Split Panel  
**Versi:** 1.0  
**Status:** Draft

> Referensi IA: `ia/02_submission/index.md`

---

## Daftar Pengajuan — Researcher View

**Pattern:** Table + Filter (ringan)  
**Layout:** Sidebar + Main Content

### Organisms

| Organism               | Deskripsi                                                           | Posisi     |
| ---------------------- | ------------------------------------------------------------------- | ---------- |
| `SubmissionListFilter` | Filter status + periode — simple, tidak perlu sidebar filter        | Main, atas |
| `SubmissionListTable`  | Tabel submission dengan kolom: judul, skema, status, tanggal submit | Main       |
| `CreateSubmissionCTA`  | Tombol "Buat Pengajuan Baru" — prominent di atas tabel              | Main, atas |

### Molecules yang Notable

- **`StatusBadge`** — warna per status: DRAFT (gray), SUBMITTED (blue), UNDER_REVIEW (yellow), NEEDS_REVISION (orange), APPROVED (green), REJECTED (red), WITHDRAWN (gray muted).
- **`ArchivedBadge`** — muncul di samping StatusBadge untuk DRAFT yang period-nya sudah tutup.

### States

| State         | Trigger                  | Tampilan                                                                                 |
| ------------- | ------------------------ | ---------------------------------------------------------------------------------------- |
| Loading       | Pertama buka             | Skeleton table rows                                                                      |
| Empty         | Belum ada submission     | Empty state: "Belum ada pengajuan. Mulai buat pengajuan baru." + tombol CTA              |
| Period closed | Tidak ada period terbuka | `CreateSubmissionCTA` di-disable dengan tooltip "Tidak ada periode pengajuan yang aktif" |

---

## Daftar Pengajuan — Operator / Admin View

**Pattern:** Table + Filter  
**Layout:** Sidebar + Main Content

### Organisms

| Organism                   | Deskripsi                                                                                                   | Posisi                   |
| -------------------------- | ----------------------------------------------------------------------------------------------------------- | ------------------------ |
| `SubmissionAdvancedFilter` | Filter: status, periode, skema, organisasi, rentang tanggal                                                 | Main, atas (collapsible) |
| `SubmissionFullTable`      | Tabel dengan kolom lebih banyak: judul, lead researcher, org, skema, status, tanggal submit, reviewer count | Main                     |

### States

| State   | Trigger                | Tampilan                                              |
| ------- | ---------------------- | ----------------------------------------------------- |
| Loading | Filter berubah         | Skeleton overlay di atas tabel                        |
| Empty   | Filter tidak ada hasil | Empty state: "Tidak ada pengajuan yang sesuai filter" |

---

## Buat Pengajuan — Wizard

**Pattern:** Wizard (Multi-step Form)  
**Layout:** Sidebar stepper kiri + Form area kanan

### Organisms

| Organism           | Deskripsi                                                                        | Posisi            |
| ------------------ | -------------------------------------------------------------------------------- | ----------------- |
| `WizardStepper`    | Progress indicator vertikal: daftar step dengan status (selesai / aktif / belum) | Sidebar kiri      |
| `WizardFormArea`   | Konten step yang aktif                                                           | Main kanan        |
| `WizardNavigation` | Tombol "Sebelumnya" dan "Lanjut / Submit"                                        | Main kanan, bawah |

### Step Breakdown

**Step 1 — Pilih Form & Periode**
Form fields: dropdown periode aktif, dropdown form yang accessible (berdasarkan org user). Setelah pilih, sistem load field-field dari form tersebut.

**Step 2 — Informasi Dasar**
Render FormFields scalar dari form yang dipilih: text, textarea, number, select, dll. Jumlah dan tipe field driven by konfigurasi Form Engine.

**Step 3 — Skema & TRL** _(opsional — hanya muncul jika form punya `scheme_selector` field)_
Dropdown skema yang tersedia di periode ini. Jika skema dipilih, muncul dropdown TRL yang filtered.

**Step 4 — Anggota Penelitian**
Dynamic table: baris per anggota, kolom: nama (UserSearchPicker), role (co_investigator / member), status. Tombol tambah baris. Section terpisah untuk mahasiswa (nama manual, NIM, prodi).

**Step 5 — Rencana Anggaran**
Dynamic table: baris per line item. Lihat detail di `ux-patterns/04_budget/index.md`.

**Step 6 — Upload Berkas**
`FileUploadZone` untuk proposal PDF (required) + berkas tambahan per tipe file yang dikonfigurasi.

**Step 7 — Review & Submit**
Ringkasan read-only semua input dari step sebelumnya. Checklist validasi: semua required field terisi, proposal PDF ada. Tombol "Submit Pengajuan".

### Interaction Notes

**Auto-save:** draft tersimpan otomatis setiap 30 detik dan setiap kali user pindah step. Ditampilkan sebagai teks kecil "Tersimpan otomatis [waktu]" di bawah WizardNavigation.

**Step navigation:** user bebas kembali ke step sebelumnya kapanpun. Maju ke step berikutnya memerlukan validasi step saat ini lulus. Step yang sudah diisi ditampilkan dengan checkmark di WizardStepper.

**Concurrent edit:** jika ada conflict (optimistic lock), tampilkan dialog: "Pengajuan ini baru saja diubah dari perangkat lain. Muat ulang untuk melihat perubahan terbaru."

### States

| State                    | Trigger                           | Tampilan                                                                |
| ------------------------ | --------------------------------- | ----------------------------------------------------------------------- |
| Loading — step load      | Pindah step                       | Skeleton di WizardFormArea                                              |
| Saving                   | Auto-save                         | Teks "Menyimpan..." di bawah nav                                        |
| Saved                    | Auto-save selesai                 | Teks "Tersimpan otomatis [waktu]"                                       |
| Error — validasi         | Klik "Lanjut" dengan field kosong | Inline error per field yang gagal validasi                              |
| Error — submit           | Submit gagal (server)             | Error banner di atas WizardFormArea                                     |
| Success — submit         | Submit berhasil                   | Redirect ke Detail Pengajuan dengan toast "Pengajuan berhasil disubmit" |
| Period closed mid-wizard | Period tutup saat user sedang isi | Banner peringatan + tombol Submit di-disable                            |

### Business Rules yang Mempengaruhi UI

- `→ ddd/core/01_submission.md#BR-SM-02` — tombol Submit di step terakhir disabled selama proposal PDF belum diupload. Ditampilkan hint: "Upload proposal PDF untuk dapat submit."
- `→ ddd/core/01_submission.md#BR-SM-01` — jika researcher sudah punya active submission di period + scheme yang sama, step 1 menampilkan pesan block dan tidak bisa lanjut.
- `→ ddd/generic/01_form_engine.md#BR-FE-11` — field dengan `required_since` setelah `submission.created_at` tidak ditampilkan sebagai required (tidak ada asterisk merah).

---

## Detail Pengajuan

**Pattern:** Detail Page dengan Tabs  
**Layout:** Sidebar + Main Content

### Organisms

| Organism                | Deskripsi                                                                | Posisi        |
| ----------------------- | ------------------------------------------------------------------------ | ------------- |
| `SubmissionHeader`      | Judul, status badge, skema, lead researcher, tombol aksi utama           | Main, atas    |
| `SubmissionTabs`        | Tab navigasi: Informasi / Anggota / Anggaran / Berkas / Review / Riwayat | Main, tengah  |
| `SubmissionTabContent`  | Konten tab yang aktif                                                    | Main, bawah   |
| `SubmissionActionPanel` | Panel aksi kontekstual per role (assign reviewer, override, dll)         | Sidebar kanan |

### Tab Contents

| Tab       | Visible To                                              | Konten                                                 |
| --------- | ------------------------------------------------------- | ------------------------------------------------------ |
| Informasi | Semua                                                   | Semua FormField responses, read-only                   |
| Anggota   | Semua                                                   | Tabel research members + student members               |
| Anggaran  | Semua (Reviewer: setelah APPROVED/REJECTED)             | Tabel budget read-only + grand total                   |
| Berkas    | Semua                                                   | Daftar file yang diupload dengan link download/preview |
| Review    | Researcher (setelah APPROVED/REJECTED), Operator, Admin | Daftar SubmissionReviewer + hasil evaluasi             |
| Riwayat   | Semua                                                   | AuditTimeline status history                           |

### Business Rules yang Mempengaruhi UI

- `→ ddd/core/01_submission.md#BR-SM-05` — status terminal (APPROVED/REJECTED/WITHDRAWN): `SubmissionActionPanel` tidak menampilkan tombol edit.
- `→ ddd/core/02_review.md#BR-REV-12` — tab Review tersembunyi untuk Researcher selama status bukan APPROVED atau REJECTED.

---

## Halaman Revisi

**Pattern:** Split Panel  
**Layout:** Split 50/50 — kiri: catatan reviewer, kanan: form edit

### Organisms

| Organism             | Deskripsi                                                          | Posisi      |
| -------------------- | ------------------------------------------------------------------ | ----------- |
| `RevisionNotePanel`  | Daftar ReviewSummary + ReviewComments + form reply                 | Kiri        |
| `SubmissionEditForm` | Form edit submission (sama dengan wizard tapi flat, bukan stepper) | Kanan       |
| `ResubmitButton`     | Tombol resubmit — prominent, di atas form                          | Kanan, atas |

### Interaction Notes

Kedua panel bisa di-scroll secara independen. Jika layar sempit (mobile/tablet), panel ditampilkan secara tab: "Catatan Reviewer" | "Edit Pengajuan".

Saat researcher reply ke komentar reviewer, reply muncul langsung di thread (optimistic update) tanpa reload halaman.

### Business Rules yang Mempengaruhi UI

- `→ ddd/core/01_submission.md#BR-SM-04` — tombol Resubmit di-disable jika tidak ada perubahan yang dilakukan setelah revision request.
- `→ ddd/supporting/01_budget.md#BR-BUD-03` — section budget di form edit tetap editable (tidak di-lock) saat NEEDS_REVISION.

---

## Shared Molecules dalam Feature Area Ini

| Molecule           | Dipakai di                    | Deskripsi                                  |
| ------------------ | ----------------------------- | ------------------------------------------ |
| `StatusBadge`      | List, Detail, Header          | Badge status dengan warna dan label        |
| `UserSearchPicker` | Step Anggota, Assign Reviewer | Search user by nama/NIDN, hasil real-time  |
| `FileUploadZone`   | Step Upload, Tab Berkas       | Drag & drop + preview + remove             |
| `ConfirmDialog`    | Submit, Withdraw, Reject      | Modal konfirmasi sebelum aksi irreversible |
