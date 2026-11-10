# Desain: Persetujuan dan Penolakan oleh Operator, Revisi oleh Reviewer

**Tanggal:** 2026-11-10 (revisi ketiga: putaran review dengan tanda "Selesai")
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
itu aturan baru dari pemilik (§4.2).

### Sistem v2 sekarang

- **APPROVED otomatis** begitu setiap `SubmissionReviewer` aktif selesai
  (`completed` atau `not_required`) dan tidak ada `ReviewSummary` `open`
  (BR-REV-05, `[REV-OUTCOME-01]`, `[REV-OUTCOME-05]`, `[REV-OUTCOME-06]`).
- **NEEDS_REVISION otomatis** pada kondisi yang sama bila ada `ReviewSummary` `open`
  (BR-REV-06, `[REV-OUTCOME-04]`).
- **Persetujuan hampa** untuk fase dengan `needs_review = false` (ADR 0017,
  `[REV-OUTCOME-09]`).
- Semuanya diputuskan di `app/Actions/Review/EvaluateSubmissionOutcome.php`, yang
  dipanggil `SubmitEvaluationForm` dan `ResolveReviewSummary`.
- Penolakan sudah manual (BR-REV-07, BR-REV-14, ADR 0006, `RejectSubmission`,
  `SubmissionDecisionController`). Policy `reject` hanya memeriksa `UnderReview`, jadi
  Tolak bisa ditekan sebelum semua reviewer selesai.

### Tiga celah yang ditemukan saat merancang

1. **Persetujuan tanpa manusia.** Pengajuan bisa disetujui tanpa ada yang membaca
   skornya, padahal skor dasar keputusan pendanaan.
2. **Reviewer terakhir tidak bisa meminta revisi.** Catatan (`ReviewSummary`) baru
   boleh dibuka setelah evaluasi reviewer itu selesai (BR-FE-06, `ReviewSummaryGuard`),
   sedangkan pengiriman lembar penilaian terakhir langsung memicu
   `EvaluateSubmissionOutcome` (`SubmitEvaluationForm`). Hasil sudah diputuskan sebelum
   reviewer terakhir sempat menulis catatan.
3. **Revisi berulang sebelum semua reviewer memeriksa.** Setelah peneliti mengirim ulang,
   setiap penutupan catatan memanggil `EvaluateSubmissionOutcome`
   (`ResolveReviewSummary`). Selama catatan reviewer lain masih terbuka, pengajuan
   langsung kembali ke NEEDS_REVISION sebelum reviewer lain sempat memeriksa revisinya.

**Seperti apa sukses itu:** tidak ada pengajuan yang menjadi APPROVED tanpa aksi
operator; revisi mengalir dari catatan reviewer dan diterima peneliti sekaligus; tidak
ada hasil yang diputuskan sebelum setiap reviewer menyatakan selesai; operator
memutuskan dari satu layar yang memuat semua skor dan catatan reviewer.

---

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**

- Aksi eksplisit operator **Setujui**, di samping **Tolak** yang sudah ada.
- Tidak ada transisi otomatis ke APPROVED.
- Transisi ke NEEDS_REVISION tetap otomatis dari catatan reviewer, tapi baru dihitung
  setelah semua peserta putaran menekan **Selesai** (§4.4).
- Operator tahu kapan sebuah pengajuan siap diputuskan.

**Non-tujuan**

- Aksi "Minta revisi" oleh operator. Revisi wewenang reviewer.
- Ambang skor otomatis atau rekomendasi otomatis dari sistem.
- Mengisi ulang lembar penilaian pada putaran revisi (keputusan pemilik R1).
- Perubahan pada siklus monev. Laporan monev tidak pernah APPROVED/REJECTED
  (`FormSubmission.php`, BR-MON-02 `canProceed()`), dan itu tetap.
- Perubahan pada penugasan atau penggantian reviewer.

**Anti-tujuan**

- Hasil review dihitung sebelum seorang reviewer menyatakan selesai.
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
- BR-FE-06 tetap: catatan baru boleh dibuka setelah evaluasi reviewer itu selesai.
- Perubahan DDD dan ADR mengikuti §5; pemilik sudah menyetujui dokumen ini.
- Skenario BDD yang dicabut dan yang baru diperbarui bersama test-nya;
  `pending.txt` dan `uncovered.txt` hanya boleh menyusut.

---

## 4. Desain

### 4.1 Siapa memicu apa

| Hasil | Dipicu oleh | Syarat |
| --- | --- | --- |
| **NEEDS_REVISION** | Sistem, saat peserta terakhir sebuah putaran menekan **Selesai** (BR-REV-06, dipicu ulang) | Masih ada `ReviewSummary` `open` |
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
- fase yang butuh review punya minimal satu reviewer aktif (sama dengan ADR 0017:
  fase butuh review tanpa reviewer adalah salah konfigurasi);
- **putaran review terakhirnya sudah selesai** — setiap pesertanya sudah menekan
  Selesai (§4.4) — dan hasilnya tidak NEEDS_REVISION;
- fase tanpa review: langsung siap setelah disubmit (§4.6).

Syarat ini berlaku untuk Setujui **dan** Tolak (keputusan pemilik). Tolak yang sekarang
bisa ditekan kapan saja selama UNDER_REVIEW ikut diperketat. Reviewer yang tidak kunjung
menekan Selesai ditangani dengan mengganti reviewer (BR-REV-10).

"Siap diputuskan" adalah keadaan turunan (computed) dari data putaran, bukan kolom
atau status baru, sejajar `canProceed()` (BR-MON-02) dan `isArchived()` (BR-SM-13).

### 4.3 Aksi Setujui

- Action tersendiri (satu Action = satu use case), mengikuti pola `RejectSubmission`.
- Ditolak bila belum siap diputuskan atau ada `ReviewSummary` `open`; pesannya menyebut
  alasannya dan jumlahnya.
- Tercatat di audit trail (BR-RPT-09) beserta operator yang memutuskan.
- Memicu `ProposalApproved` (lihat §4.8).
- Siapa yang boleh: pemegang permission yang sama dengan Tolak sekarang
  (`submissions.view-all`).

### 4.4 Putaran review dan tanda "Selesai"

Keputusan pemilik R2 (iii) dan R3 (A). Satu konsep untuk putaran pertama dan putaran
revisi: **hasil review hanya dihitung setelah setiap peserta putaran menyatakan
selesai.**

**Putaran pertama — "Selesai menilai"**

- Dimulai saat pengajuan mendapat reviewer dan masuk review.
- Peserta: semua `SubmissionReviewer` aktif.
- Reviewer mengirim lembar penilaiannya (evaluasi `completed`), menulis catatan revisi
  bila ada (BR-FE-06 tetap: catatan setelah evaluasinya selesai), lalu menekan
  **"Selesai menilai"**. Tombol itu baru aktif setelah evaluasinya `completed`.
- Reviewer `not_required` dihitung sudah selesai tanpa menekan apa pun.

**Putaran revisi — "Selesai memeriksa"**

- Dimulai saat peneliti mengirim ulang (RESUBMITTED → UNDER_REVIEW).
- Peserta: reviewer aktif yang punya `ReviewSummary` `open` saat pengajuan dikirim ulang.
  Reviewer tanpa catatan terbuka tidak diminta memeriksa.
- Peserta memeriksa revisi, **menutup** catatan yang sudah dipenuhi, membiarkan terbuka
  yang belum, lalu menekan **"Selesai memeriksa"**. Lembar penilaian tidak diisi ulang
  dan skor tetap (keputusan pemilik R1, BR-REV-08 tidak berubah).

**Berlaku untuk kedua jenis putaran**

- Tanda Selesai dicatat **per reviewer per putaran**; riwayat putaran lama tetap
  terbaca.
- Begitu peserta **terakhir** menekan Selesai, hasil putaran dihitung sekali:
  - masih ada `ReviewSummary` `open` → NEEDS_REVISION serentak, peneliti menerima
    `RevisionRequested` (BR-REV-06 per putaran);
  - tidak ada → pengajuan siap diputuskan dan operator diberi tahu (§4.7).
- **Mengirim lembar penilaian dan menutup catatan tidak lagi memicu evaluasi hasil.**
  `SubmitEvaluationForm` dan `ResolveReviewSummary` berhenti memanggil
  `EvaluateSubmissionOutcome`; satu-satunya pemicunya adalah putaran yang selesai.
  Ini menutup celah 2 dan 3 (§1).
- Reviewer pengganti (BR-REV-10) menjadi peserta putaran berjalan menggantikan yang
  lama, mewarisi catatannya (BR-REV-15), dan belum dihitung selesai. Bila penggantian
  atau perubahan ke `not_required` membuat semua peserta tersisa sudah selesai, putaran
  selesai saat itu juga dan hasilnya dihitung di Action tersebut.
- Tanda Selesai **tidak bisa dibatalkan** (**keputusan penulis**).
- Setelah menekan Selesai, reviewer tidak bisa membuka catatan baru pada putaran itu;
  diskusi pada catatan yang sudah ada (`ReviewComment`) tetap terbuka (**keputusan
  penulis**).
- Selama putaran berjalan, peserta boleh membuka catatan baru; catatan itu ikut dihitung
  (**keputusan penulis**).
- Selama putaran berjalan, pengajuan tidak siap diputuskan, jadi Setujui dan Tolak
  sama-sama menunggu (**keputusan penulis**, konsisten dengan keputusan pemilik butir 2).
- Putaran berulang sampai tidak ada catatan terbuka, atau operator menolak.

### 4.5 Yang berubah di kode dan aturan

- `EvaluateSubmissionOutcome` **berhenti memindahkan ke APPROVED**. Cabang
  NEEDS_REVISION tetap, dan hanya dijalankan saat sebuah putaran selesai.
- `SubmitEvaluationForm` dan `ResolveReviewSummary` tidak lagi memanggilnya.
- Aksi baru reviewer **Selesai** (satu Action, dua label: "Selesai menilai" di putaran
  pertama, "Selesai memeriksa" di putaran revisi) beserta penyimpanan putaran dan
  pesertanya.
- `OpenReviewThread` menolak reviewer yang sudah menekan Selesai pada putaran berjalan.
- BR-REV-05 diganti aturan persetujuan operator. BR-REV-06 tetap, pemicunya pindah ke
  akhir putaran.
- ADR 0017 (persetujuan hampa) diganti: pengajuan pada fase tanpa review langsung siap
  diputuskan setelah disubmit dan menunggu Setujui/Tolak operator.
- Policy `reject` ditambah syarat siap diputuskan.
- Skenario `[REV-OUTCOME-01]`..`[REV-OUTCOME-06]` dan `[REV-OUTCOME-09]` ditulis ulang
  ke pemicu baru (akhir putaran) dan hasil baru ("siap diputuskan", bukan APPROVED).
  `[REV-OUTCOME-07]`, `[REV-OUTCOME-08]`, `[REV-OUTCOME-10]` tetap.

### 4.6 Fase tanpa review

Pengajuan pada fase dengan `needs_review = false` tidak punya reviewer dan tidak punya
putaran. Ia langsung **siap diputuskan** setelah disubmit, dan operator memutuskannya
dengan Setujui/Tolak. Action keputusan lebih dulu melewatkannya ke UNDER_REVIEW, seperti
`settleInto()` sekarang, supaya state machine tidak dilebarkan.

### 4.7 Operator tahu kapan harus memutuskan

- Operator pemegang `submissions.view-all` diberi tahu in-app **setiap kali sebuah
  pengajuan menjadi siap diputuskan**: putaran review selesai tanpa catatan terbuka,
  atau fase tanpa review disubmit. Pemicunya selalu satu Action yang menyelesaikan
  putaran (Selesai, penggantian reviewer, perubahan ke `not_required`) atau submit; tidak
  ada kolom bendera (**keputusan penulis**).
- Daftar pengajuan operator punya penyaring **"Menunggu keputusan"**, dan dashboard
  operator menghitungnya.

### 4.8 Pemberitahuan ke peneliti dan reviewer

- `ProposalApproved` kini dipicu aksi operator; `RevisionRequested` tetap dipicu
  transisi ke NEEDS_REVISION di akhir putaran; `ProposalRejected` dipicu Tolak. Tabel
  event di `ddd/generic/03_notification.md` sudah memuat ketiganya. Kelas notifikasi
  `ProposalApproved`/`ProposalRejected` belum ditemukan di `app/Notifications/`; builder
  memastikan keberadaannya dan melengkapinya bila belum ada.
- Peserta putaran revisi diberi tahu saat pengajuan dikirim ulang, karena merekalah yang
  harus memeriksa (**keputusan penulis**, sejalan BR-MON-10: yang diberi tahu adalah
  pihak yang harus bertindak).

### 4.9 Layar

- **Reviewer:** tombol "Selesai menilai" / "Selesai memeriksa" pada halaman review
  pengajuan, nonaktif dengan penjelasan bila evaluasinya belum `completed`, dan
  keterangan "Anda sudah menyelesaikan putaran ini" sesudahnya.
- **Operator — layar keputusan** pada detail pengajuan: skor setiap reviewer per Form
  Evaluasi (beserta versi lembarnya bila desain fase sudah berjalan), catatan reviewer
  terbuka dan selesai beserta jumlah yang masih terbuka, status putaran ("2 dari 3
  reviewer selesai"), dan tombol Setujui dan Tolak. Tombol yang tidak memenuhi syarat
  dinonaktifkan dengan penjelasan.

### 4.10 Pengajuan yang sedang berjalan saat rilis

- Pengajuan yang sudah APPROVED/NEEDS_REVISION/REJECTED tidak disentuh.
- Pengajuan yang sedang menunggu mendapat satu putaran berjalan: putaran pertama bila
  belum pernah direvisi, putaran revisi bila sedang dalam pemeriksaan revisi. Reviewer
  yang evaluasinya sudah `completed` saat rilis **dihitung sudah selesai** pada putaran
  itu, karena di aturan lama mereka sudah bisa membuka catatan; yang belum `completed`
  harus menekan Selesai (**keputusan penulis**). Pengajuan yang dengan begitu putarannya
  sudah selesai langsung tampil di "Menunggu keputusan" atau dihitung hasilnya sekali
  oleh migrasi data.

---

## 5. Dampak pada DDD, ADR, BDD

| Dokumen | Yang berubah |
| --- | --- |
| `ddd/core/02_review.md` | BR-REV-05 diganti aturan persetujuan operator (§4.2–4.3); BR-REV-06 tetap dengan pemicu akhir putaran; aturan baru: putaran review dan tanda Selesai per reviewer per putaran (§4.4); Setujui ditolak selama ada `ReviewSummary` `open`; Tolak juga menunggu putaran selesai; istilah baru di activity diagram review |
| `ddd/02_ubiquitous_language.md` | istilah baru: Putaran Review, Selesai menilai, Selesai memeriksa, Siap diputuskan |
| `ddd/core/01_submission.md` | event `ProposalApproved` dipicu aksi operator, bukan otomatis |
| `docs/adr/0017-…` | digantikan ADR baru: fase tanpa review menunggu keputusan operator |
| `docs/adr/0006-…` | substansinya tetap; diperluas ke persetujuan |
| ADR baru | "Hasil review dihitung di akhir putaran" — alasan: celah 2 dan 3 (§1) |
| `ddd/generic/03_notification.md` | pemberitahuan "siap diputuskan" untuk Operator; pemberitahuan peserta putaran revisi |
| `bdd/core/review/outcome.feature` | skenario ditulis ulang sesuai §4.5; skenario baru untuk Setujui, tanda Selesai di kedua jenis putaran, reviewer terakhir bisa meminta revisi, menutup satu catatan tidak mengembalikan ke revisi, penggantian reviewer di tengah putaran, pemberitahuan, dan fase tanpa review |

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
   reviewer yang terbuka membuat pengajuan NEEDS_REVISION setelah semua reviewer selesai.
7. **R2 (iii):** setelah pengiriman ulang, pengajuan kembali ke NEEDS_REVISION
   serentak, hanya setelah semua reviewer yang punya catatan terbuka selesai memeriksa.
8. **R3 (A):** satu tanda "Selesai" per reviewer per putaran, berlaku sejak putaran
   pertama ("Selesai menilai") maupun putaran revisi ("Selesai memeriksa").

### Keputusan penulis (untuk diperiksa pemilik)

- Syarat siap diputuskan berlaku juga untuk Tolak (§4.2).
- "Siap diputuskan" dihitung dari data putaran, bukan status baru; state machine tidak
  diubah (§4.2).
- Tanda Selesai tidak bisa dibatalkan; setelah Selesai, reviewer tidak membuka catatan
  baru di putaran itu, tapi tetap bisa berdiskusi (§4.4).
- Selama putaran berjalan, Setujui dan Tolak menunggu (§4.4).
- Pemberitahuan "siap diputuskan" hanya in-app (§4.7); peserta putaran revisi diberi
  tahu saat pengajuan dikirim ulang (§4.8).
- Laporan monev dikecualikan dari "siap diputuskan" (§4.2).
- Permission keputusan = `submissions.view-all`, sama dengan Tolak sekarang (§4.3).
- Saat rilis, reviewer yang evaluasinya sudah `completed` dihitung sudah selesai
  (§4.10).

## 7. Diserahkan ke builder

- Nama Action, nama route, dan bentuk FormRequest/DTO.
- Bentuk penyimpanan putaran dan tanda Selesai, selama tercatat per reviewer per putaran
  dan riwayat putaran lama tetap terbaca.
- Tata letak layar, mengikuti `docs/design/fe-v2-design.md`.
- Apakah `EvaluateSubmissionOutcome` diganti nama setelah cabang APPROVED-nya dicabut.

## 8. Pertanyaan terbuka

Tidak ada yang menghalangi. R1 (§4.4), R2 (§4.4), dan R3 (§4.4) sudah diputuskan
pemilik.
