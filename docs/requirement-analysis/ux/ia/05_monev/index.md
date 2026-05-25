# IA: Monev (Monitoring & Evaluation)

**Roles yang terlibat:** `Researcher` `Reviewer` `Operator` `Admin`  
**DDD Context:** Monev, Form Engine  
**Versi:** 1.0  
**Status:** Draft

---

## Page Inventory

| # | Page | Route | Accessible By |
|---|------|-------|---------------|
| 1 | Daftar Monev (Researcher) | `/monev` | Researcher |
| 2 | Isi Laporan Monev | `/monev/{id}/fill` | Researcher |
| 3 | Detail Laporan Monev | `/monev/{id}` | Semua |
| 4 | Manajemen Siklus Monev | `/monev/manage` | Operator, Admin |
| 5 | Aktivasi Siklus & Assign Reviewer | `/monev/manage/{submission_id}` | Operator, Admin |

---

## Daftar Monev — Researcher View

**Route:** `/monev`  
**Accessible by:** Researcher (memiliki submission APPROVED)  
**Entry points:**
- Sidebar nav → Monev

**Exit points:**
- → Isi Laporan Monev (klik siklus aktif)
- → Detail Laporan Monev (klik siklus yang sudah disubmit)

### Konten Utama

Daftar submission yang APPROVED, masing-masing beserta **phase progression** visual — menunjukkan di mana posisi submission dalam keseluruhan siklus (Pengajuan → Monev I → Monev II → Upload Luaran).

Setiap siklus menampilkan: nama stage, status (belum aktif / aktif / sudah disubmit / expired), dan deadline.

### Actions

| Aksi | Kondisi |
|------|---------|
| Isi Laporan Monev | Siklus aktif, belum disubmit, deadline belum lewat |
| Lihat Detail | Siklus sudah disubmit |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/02_monev.md#BR-MON-01` — hanya submission APPROVED yang muncul.
- `→ ddd/supporting/02_monev.md#BR-MON-02` — siklus berikutnya ditampilkan sebagai locked jika siklus sebelumnya belum selesai.
- `→ ddd/supporting/02_monev.md#BR-MON-05` — siklus yang sudah lewat deadline ditampilkan dengan badge "Ditutup" kecuali ada FormSubmissionOverride aktif.
- `→ ddd/supporting/02_monev.md#BR-MON-08` — jika submission di-withdraw saat monev aktif, semua siklus yang belum disubmit ditampilkan sebagai "Dibekukan".

---

## Isi Laporan Monev

**Route:** `/monev/{id}/fill`  
**Accessible by:** Researcher (submitted_by submission utama)  
**Entry points:**
- Klik siklus aktif dari Daftar Monev

**Exit points:**
- → Detail Laporan Monev (setelah submit)
- → Daftar Monev (batalkan)

### Konten Utama

Form isian laporan kemajuan — ini adalah child FormSubmission dengan field-field sesuai konfigurasi FormPhaseDetail. Bisa mencakup field teks, angka, dan upload file kelengkapan.

### Actions

| Aksi | Kondisi |
|------|---------|
| Simpan Draft | Selalu |
| Submit Laporan | Semua field required terpenuhi, deadline belum lewat |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/02_monev.md#BR-MON-03` — hanya satu child FormSubmission per FormPhaseDetail per researcher. Tidak bisa submit ulang jika sudah ada.
- `→ ddd/generic/01_form_engine.md#BR-FE-08` — jika deadline lewat dan tidak ada override aktif, form di-blok dan tampilkan pesan dengan info kontak operator.

---

## Detail Laporan Monev

**Route:** `/monev/{id}`  
**Accessible by:** Researcher (own), Reviewer (assigned), Operator, Admin  
**Entry points:**
- Klik siklus yang sudah disubmit dari Daftar Monev
- Link dari Manajemen Siklus Monev (Operator)

### Konten Utama

Read-only view laporan yang sudah disubmit. Ditampilkan bersama hasil evaluasi reviewer (jika sudah ada) dan thread diskusi.

---

## Manajemen Siklus Monev

**Route:** `/monev/manage`  
**Accessible by:** Operator, Admin  
**Entry points:**
- Sidebar nav → Monev → Manajemen Siklus Monev

**Exit points:**
- → Aktivasi Siklus per Submission

### Konten Utama

Tabel semua submission APPROVED yang memiliki siklus monev, beserta status progress monev mereka. Filter tersedia per periode, skema, dan status siklus.

### Actions

| Aksi | Kondisi |
|------|---------|
| Buka detail & kelola siklus | Selalu |

---

## Aktivasi Siklus & Assign Reviewer

**Route:** `/monev/manage/{submission_id}`  
**Accessible by:** Operator, Admin  
**Entry points:**
- Klik submission dari Manajemen Siklus Monev

**Exit points:**
- → Manajemen Siklus Monev

### Konten Utama

Phase progression visual untuk submission ini. Per siklus yang belum aktif: tombol aktivasi. Per siklus yang aktif: status laporan researcher dan form assign reviewer.

Saat assign reviewer, sistem menampilkan **suggestion** reviewer dari pengajuan awal yang masih aktif — dibedakan secara visual dari reviewer lain yang tersedia.

### Actions

| Aksi | Kondisi |
|------|---------|
| Aktifkan siklus monev | Siklus sebelumnya sudah selesai |
| Assign reviewer | Siklus aktif |
| Perpanjang deadline siklus | Selalu (via FormSubmissionOverride) |

### Business Rules yang Mempengaruhi Tampilan

- `→ ddd/supporting/02_monev.md#BR-MON-04` — reviewer di-assign manual, tidak otomatis.
- `→ ddd/supporting/02_monev.md#BR-MON-06` — satu SubmissionReviewer baru per siklus, tidak reuse dari siklus sebelumnya.
- `→ ddd/supporting/02_monev.md#BR-MON-07` — reviewer yang sudah expired tidak masuk suggestion tapi tetap bisa dicari manual.
