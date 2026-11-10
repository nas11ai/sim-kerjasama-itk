# Desain: Persetujuan dan Penolakan oleh Operator, Revisi oleh Reviewer

**Tanggal:** 2026-11-10 (revisi kedua, setelah memeriksa sistem lama)
**Status:** Disetujui pemilik 2026-11-10
**Basis kode:** `feat/init-be-development` @ `061f44ae`
**Terkait:** `2026-11-10-fase-reuse-dan-versi-form-design.md` §9 P1

---

## 1. Latar belakang dan alasan

Pemilik produk menetapkan bahwa skor dan catatan reviewer adalah **masukan** bagi
operator, dan bahwa **disetujui dan ditolak adalah keputusan operator**. Revisi tetap
wewenang reviewer.

Pembagian ini diambil dari sistem lama (`../evolusi-simpas`):

| Status lama | Dipicu oleh | Bukti |
| --- | --- | --- |
| Sedang Direvisi | Reviewer yang ditugaskan, begitu ia membuat catatan revisi | `ListRevisiController.php:192` |
| Telah Direvisi | Peneliti mengunggah perbaikan | `ListRevisiController.php:446` |
| Disetujui / Ditolak | Admin atau Super Admin, manual, satu atau banyak sekaligus | `PraPengajuanController.php:167,410`; view `list-pengajuan/index.blade.php:202-208` |

Sistem lama tidak mewajibkan semua reviewer selesai sebelum admin memutuskan; syarat
itu aturan baru dari pemilik (§4.1).

Sistem dan DDD v2 sekarang:

- **APPROVED otomatis** begitu setiap `SubmissionReviewer` aktif selesai
  (`completed` atau `not_required`) dan tidak ada `ReviewSummary` `open`
  (BR-REV-05, `[REV-OUTCOME-01]`, `[REV-OUTCOME-05]`, `[REV-OUTCOME-06]`).
- **NEEDS_REVISION otomatis** pada kondisi yang sama bila ada `ReviewSummary` `open`
  (BR-REV-06, `[REV-OUTCOME-04]`). Ini **sudah sesuai** dengan keputusan pemilik:
  revisi dipicu catatan reviewer, dan baru berlaku setelah semua reviewer selesai
  sehingga peneliti menerima semua catatan sekaligus.
- **Persetujuan hampa** untuk fase dengan `needs_review = false` (ADR 0017,
  `[REV-OUTCOME-09]`).
- Semuanya diputuskan di `app/Actions/Review/EvaluateSubmissionOutcome.php`, yang
  dipanggil `SubmitEvaluationForm` dan `ResolveReviewSummary`.
- Penolakan sudah manual (BR-REV-07, BR-REV-14, ADR 0006, `RejectSubmission`,
  `SubmissionDecisionController`). Policy `reject` hanya memeriksa `UnderReview`, jadi
  Tolak bisa ditekan sebelum semua reviewer selesai.

Masalahnya tinggal di persetujuan: pengajuan bisa disetujui tanpa ada manusia yang
membaca skornya, padahal skor itu dasar keputusan pendanaan.

**Seperti apa sukses itu:** tidak ada pengajuan yang menjadi APPROVED tanpa aksi
operator; revisi tetap mengalir dari catatan reviewer; operator memutuskan dari satu
layar yang memuat semua skor dan catatan reviewer.

---

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**

- Aksi eksplisit operator **Setujui**, di samping **Tolak** yang sudah ada.
- Tidak ada transisi otomatis ke APPROVED.
- Transisi ke NEEDS_REVISION tetap otomatis dari catatan reviewer (BR-REV-06).
- Operator tahu kapan sebuah pengajuan siap diputuskan.

**Non-tujuan**

- Aksi "Minta revisi" oleh operator. Revisi wewenang reviewer.
- Ambang skor otomatis atau rekomendasi otomatis dari sistem.
- Perubahan pada siklus monev. Laporan monev tidak pernah APPROVED/REJECTED
  (`FormSubmission.php`, BR-MON-02 `canProceed()`), dan itu tetap.
- Perubahan pada penugasan atau penggantian reviewer.

**Anti-tujuan**

- Pengajuan menggantung tanpa ada yang tahu ia menunggu keputusan.
- Jalur kode apa pun selain aksi operator yang memindahkan status ke APPROVED atau
  REJECTED.

---

## 3. Batasan

- Aturan `AGENTS.md`: status hanya lewat `transitionTo()`, business logic di
  `app/Actions/`, authorization di `app/Policies/`, scoping BR-SM-07, kode BR di
  komentar.
- State machine `SubmissionStatus` **tidak perlu diubah**: semua transisi keputusan
  sudah berangkat dari `UnderReview`.
- Perubahan DDD dan ADR ditulis **setelah pemilik menyetujui dokumen ini secara
  eksplisit**.
- Skenario BDD yang dicabut dan yang baru diperbarui bersama test-nya;
  `pending.txt` dan `uncovered.txt` hanya boleh menyusut.

---

## 4. Desain

### 4.1 Siapa memicu apa

| Hasil | Dipicu oleh | Syarat |
| --- | --- | --- |
| **NEEDS_REVISION** | Sistem, dari catatan reviewer (BR-REV-06 tetap) | Semua reviewer aktif selesai dan ada `ReviewSummary` `open` |
| **APPROVED** | **Operator**, aksi Setujui | Siap diputuskan (§4.2) dan tidak ada `ReviewSummary` `open` |
| **REJECTED** | **Operator**, aksi Tolak (sudah ada) | Siap diputuskan (§4.2); alasan wajib (BR-REV-07) |

Operator tidak punya aksi "Minta revisi". Kalau operator menilai sebuah pengajuan perlu
diperbaiki, jalurnya lewat reviewer (catatan reviewer), atau Tolak.

### 4.2 Siap diputuskan

Sebuah pengajuan **siap diputuskan** bila semua kondisi berikut terpenuhi:

- pengajuan utama (tanpa `parent_submission_id`) — laporan monev tidak pernah siap
  diputuskan;
- berstatus SUBMITTED, UNDER_REVIEW, atau RESUBMITTED (sama dengan `AWAITING_DECISION`
  di `EvaluateSubmissionOutcome` sekarang). Yang belum UNDER_REVIEW dilewatkan ke
  UNDER_REVIEW lebih dulu oleh Action keputusan, seperti `settleInto()` sekarang;
- setiap `SubmissionReviewer` aktif sudah selesai (`completed` atau `not_required`,
  definisi `SubmissionReviewer::SETTLED_EVALUATION` yang sudah ada);
- fase yang butuh review punya minimal satu reviewer aktif (sama dengan ADR 0017:
  fase butuh review tanpa reviewer adalah salah konfigurasi);
- tidak ada putaran pemeriksaan revisi yang masih berjalan (§4.10).

Syarat "semua reviewer selesai" berlaku untuk Setujui **dan** Tolak (keputusan
pemilik). Tolak yang sekarang bisa ditekan kapan saja selama UNDER_REVIEW ikut
diperketat. Reviewer yang tidak kunjung mengisi ditangani dengan mengganti reviewer
(BR-REV-10).

"Siap diputuskan" adalah keadaan turunan (computed), bukan kolom atau status baru,
sejajar `canProceed()` (BR-MON-02) dan `isArchived()` (BR-SM-13).

### 4.3 Aksi Setujui

- Action tersendiri (satu Action = satu use case), mengikuti pola `RejectSubmission`.
- Ditolak bila ada `ReviewSummary` `open`; pesannya menyebut jumlahnya.
- Tercatat di audit trail (BR-RPT-09) beserta operator yang memutuskan.
- Memicu `ProposalApproved` (lihat §4.7).
- Siapa yang boleh: pemegang permission yang sama dengan Tolak sekarang
  (`submissions.view-all`).

### 4.4 Yang berubah di kode dan aturan

- `EvaluateSubmissionOutcome` **berhenti memindahkan ke APPROVED**. Cabang
  NEEDS_REVISION tetap, dan setelah pengiriman ulang hanya dijalankan saat putaran
  pemeriksaan selesai (§4.10).
- Aksi baru reviewer **"Selesai memeriksa"** beserta penanda per reviewer per putaran.
- BR-REV-05 diganti aturan persetujuan operator. BR-REV-06 tetap.
- ADR 0017 (persetujuan hampa) diganti: pengajuan pada fase tanpa review langsung siap
  diputuskan setelah disubmit dan menunggu Setujui/Tolak operator.
- Policy `reject` ditambah syarat siap diputuskan.
- Skenario `[REV-OUTCOME-01]`, `[REV-OUTCOME-05]`, `[REV-OUTCOME-06]`,
  `[REV-OUTCOME-09]` ditulis ulang: hasilnya "siap diputuskan", bukan APPROVED.
  `[REV-OUTCOME-02]`, `[REV-OUTCOME-03]`, `[REV-OUTCOME-04]`, `[REV-OUTCOME-07]`,
  `[REV-OUTCOME-08]`, `[REV-OUTCOME-10]` tetap.

### 4.5 Operator tahu kapan harus memutuskan

- Operator pemegang `submissions.view-all` diberi tahu in-app **setiap kali sebuah
  pengajuan menjadi siap diputuskan tanpa catatan terbuka**, apa pun pemicunya:
  reviewer terakhir selesai, putaran pemeriksaan revisi selesai tanpa catatan terbuka,
  reviewer yang belum selesai diganti atau dijadikan `not_required`, atau fase tanpa
  review disubmit.
  Karena keadaannya dihitung, setiap Action pemicu membandingkan keadaan sebelum dan
  sesudah aksinya sendiri; tidak ada kolom bendera (**keputusan penulis**).
- Daftar pengajuan operator punya penyaring **"Menunggu keputusan"**, dan dashboard
  operator menghitungnya.

### 4.6 Layar keputusan

Satu layar pada detail pengajuan, untuk operator:

- skor setiap reviewer per Form Evaluasi, beserta versi lembarnya (dari desain fase;
  sebelum desain fase berjalan, tanpa label versi);
- catatan diskusi reviewer (`ReviewSummary`), terbuka dan selesai, dengan jumlah yang
  masih terbuka;
- tombol Setujui dan Tolak. Tombol yang tidak memenuhi syarat dinonaktifkan dengan
  penjelasan, misalnya "2 reviewer belum selesai" atau "1 catatan masih terbuka".

### 4.7 Pemberitahuan ke peneliti

Tabel event di `ddd/generic/03_notification.md` memuat `ProposalApproved`,
`ProposalRejected`, dan `RevisionRequested`. `ProposalApproved` kini dipicu aksi
operator; `RevisionRequested` tetap dipicu transisi otomatis ke NEEDS_REVISION
(`[REV-OUTCOME-04]`). Kelas notifikasi `ProposalApproved`/`ProposalRejected` belum
ditemukan di `app/Notifications/`; builder memastikan keberadaannya dan melengkapinya
bila belum ada.

### 4.8 Pengajuan yang sedang berjalan saat rilis

- Pengajuan yang sudah APPROVED/NEEDS_REVISION/REJECTED tidak disentuh.
- Pengajuan yang sedang menunggu tidak lagi akan menjadi APPROVED otomatis; begitu
  memenuhi §4.2, ia muncul di "Menunggu keputusan" karena keadaannya dihitung, bukan
  disimpan.

### 4.9 Setelah revisi

Keputusan pemilik R1, pilihan (a):

- "Reviewer menilai ulang" berarti reviewer memeriksa apakah catatannya sudah dipenuhi
  lalu **menutup catatannya**. Lembar penilaian tidak diisi ulang, skor tetap, dan
  BR-REV-08 tidak berubah.
- Setujui terkunci selama masih ada catatan terbuka.

### 4.10 Putaran pemeriksaan revisi (serentak)

Keputusan pemilik R2, pilihan (iii). Prinsipnya sama dengan putaran pertama: peneliti
menerima semua catatan yang belum dipenuhi **sekaligus**, setelah semua reviewer yang
terlibat selesai memeriksa.

- **Putaran pemeriksaan** dimulai saat peneliti mengirim ulang (RESUBMITTED →
  UNDER_REVIEW).
- **Peserta putaran** adalah reviewer aktif yang punya `ReviewSummary` `open` pada saat
  pengajuan dikirim ulang. Reviewer tanpa catatan terbuka tidak diminta memeriksa.
  Reviewer pengganti mewarisi catatan (BR-REV-15) dan juga kewajiban memeriksanya.
- Setiap peserta memeriksa revisi, **menutup** catatan yang sudah dipenuhi, membiarkan
  terbuka yang belum, lalu menekan **"Selesai memeriksa"**. Penanda ini dicatat per
  reviewer per putaran.
- **Menutup satu catatan tidak lagi memicu evaluasi hasil.** `ResolveReviewSummary`
  berhenti memanggil `EvaluateSubmissionOutcome` selama putaran pemeriksaan berjalan;
  inilah yang menutup celah "kembali ke revisi sebelum reviewer kedua memeriksa".
- Begitu **semua peserta** menekan "Selesai memeriksa":
  - masih ada `ReviewSummary` `open` → NEEDS_REVISION serentak, peneliti menerima
    `RevisionRequested` (BR-REV-06 berlaku per putaran);
  - tidak ada → pengajuan **siap diputuskan** dan operator diberi tahu (§4.5).
- Peserta boleh membuka catatan baru selama memeriksa; catatan itu ikut dihitung
  terbuka (**keputusan penulis**).
- Peserta yang tidak kunjung memeriksa ditangani dengan mengganti reviewer
  (BR-REV-10); penggantinya menjadi peserta putaran itu.
- Selama putaran pemeriksaan berjalan, pengajuan **tidak** siap diputuskan, jadi Setujui
  dan Tolak sama-sama menunggu (**keputusan penulis**, konsisten dengan keputusan
  pemilik butir 2).
- Putaran berulang sampai tidak ada catatan terbuka, atau operator menolak.

---

## 5. Dampak pada DDD, ADR, BDD

| Dokumen | Yang berubah |
| --- | --- |
| `ddd/core/02_review.md` | BR-REV-05 diganti aturan persetujuan operator (§4.2–4.3); BR-REV-06 tetap; aturan baru: Setujui ditolak selama ada `ReviewSummary` `open`; Tolak juga menunggu semua reviewer selesai; aturan baru putaran pemeriksaan revisi serentak dan aksi "Selesai memeriksa" (§4.10) |
| `ddd/core/01_submission.md` | event `ProposalApproved` dipicu aksi operator, bukan otomatis |
| `docs/adr/0017-…` | digantikan ADR baru: fase tanpa review menunggu keputusan operator |
| `docs/adr/0006-…` | substansinya tetap; diperluas ke persetujuan |
| `ddd/generic/03_notification.md` | pemberitahuan "siap diputuskan" untuk Operator |
| `bdd/core/review/outcome.feature` | skenario ditulis ulang sesuai §4.4; skenario baru untuk Setujui, syarat-syaratnya, pemberitahuan, fase tanpa review, dan putaran pemeriksaan revisi (menutup satu catatan tidak mengembalikan ke revisi; revisi serentak setelah semua peserta selesai memeriksa; siap diputuskan bila semua catatan tertutup) |

## 6. Keputusan pemilik (2026-11-10)

1. Skor dan catatan reviewer adalah masukan; disetujui dan ditolak diputuskan manual
   oleh operator.
2. Operator hanya boleh memutuskan setelah semua reviewer selesai.
3. Setujui ditolak selama masih ada catatan reviewer yang terbuka.
4. Operator diberi tahu saat pengajuan siap diputuskan; fase tanpa review juga menunggu
   keputusan operator.
5. **R1 (a):** setelah revisi, reviewer menutup catatannya bila sudah dipenuhi; lembar
   penilaian tidak diisi ulang dan skor tetap.
6. **Revisi dipicu reviewer, bukan operator** (dicek terhadap sistem lama): catatan
   reviewer yang terbuka membuat pengajuan NEEDS_REVISION setelah semua reviewer selesai
   (BR-REV-06 tetap). Ini menggantikan butir "Minta revisi oleh operator" pada versi
   pertama dokumen ini.
7. **R2 (iii):** setelah pengiriman ulang, pengajuan kembali ke NEEDS_REVISION
   serentak, hanya setelah semua reviewer yang punya catatan terbuka selesai memeriksa
   (§4.10).

### Keputusan penulis (untuk diperiksa pemilik)

- Syarat "semua reviewer selesai" berlaku juga untuk Tolak (§4.2).
- "Siap diputuskan" dihitung, bukan status baru; state machine tidak diubah (§4.2).
- Pemberitahuan "siap diputuskan" hanya in-app, dikirim pada setiap peralihan ke siap
  diputuskan tanpa catatan terbuka, apa pun pemicunya (§4.5).
- Laporan monev dikecualikan dari "siap diputuskan" (§4.2).
- Permission keputusan = `submissions.view-all`, sama dengan Tolak sekarang (§4.3).
- Peserta putaran pemeriksaan hanya reviewer yang punya catatan terbuka; catatan baru
  selama memeriksa ikut dihitung; selama putaran berjalan Setujui dan Tolak menunggu
  (§4.10).

## 7. Diserahkan ke builder

- Nama Action, nama route, dan bentuk FormRequest/DTO.
- Tata letak layar keputusan, mengikuti `docs/design/fe-v2-design.md`.
- Apakah `EvaluateSubmissionOutcome` diganti nama setelah cabang APPROVED-nya dicabut.
- Bentuk penyimpanan putaran pemeriksaan dan penanda "Selesai memeriksa", selama
  tercatat per reviewer per putaran dan riwayat putaran lama tetap terbaca.

## 8. Pertanyaan terbuka

Tidak ada yang menghalangi. R1 (§4.9) dan R2 (§4.10) sudah diputuskan pemilik; dasar
R2 adalah celah sistem sekarang: setelah pengiriman ulang, `ResolveReviewSummary`
memanggil `EvaluateSubmissionOutcome` pada setiap catatan yang ditutup, sehingga
pengajuan kembali ke NEEDS_REVISION sebelum reviewer lain sempat memeriksa revisinya.
