# Desain: Keputusan Hasil Review Diambil Operator

**Tanggal:** 2026-11-10
**Status:** Disetujui pemilik 2026-11-10
**Basis kode:** `feat/init-be-development` @ `061f44ae`
**Terkait:** `2026-11-10-fase-reuse-dan-versi-form-design.md` §9 P1

---

## 1. Latar belakang dan alasan

Pemilik produk menetapkan bahwa **disetujui, perlu revisi, dan ditolak adalah
keputusan Operator**. Skor dan catatan reviewer adalah masukan untuk keputusan itu,
bukan keputusan itu sendiri. Di LPPM, operator melihat skor dan catatan reviewer,
lalu memutuskan secara manual.

Sistem dan DDD sekarang bekerja lain:

- **APPROVED otomatis** begitu setiap `SubmissionReviewer` aktif selesai
  (`completed` atau `not_required`) dan tidak ada `ReviewSummary` `open`
  (BR-REV-05, `[REV-OUTCOME-01]`, `[REV-OUTCOME-05]`).
- **NEEDS_REVISION otomatis** pada kondisi yang sama bila ada `ReviewSummary` `open`
  (BR-REV-06, `[REV-OUTCOME-04]`).
- **Persetujuan hampa** untuk fase dengan `needs_review = false` (ADR 0017,
  `[REV-OUTCOME-09]`).
- Semuanya diputuskan di satu tempat, `app/Actions/Review/EvaluateSubmissionOutcome.php`,
  yang dipanggil `SubmitEvaluationForm` dan `ResolveReviewSummary`.
- Hanya penolakan yang manual (BR-REV-07, BR-REV-14, ADR 0006,
  `RejectSubmission`, `SubmissionDecisionController`). Policy `reject` sekarang hanya
  memeriksa `UnderReview`, jadi Tolak bisa ditekan sebelum semua reviewer selesai.

Akibatnya, pengajuan bisa disetujui tanpa ada manusia yang membaca skornya, padahal
skor itu dasar keputusan pendanaan.

**Seperti apa sukses itu:** tidak ada pengajuan yang berpindah ke APPROVED atau
NEEDS_REVISION tanpa aksi operator, dan operator memutuskan dari satu layar yang
memuat semua skor dan catatan reviewer.

---

## 2. Tujuan, non-tujuan, anti-tujuan

**Tujuan**

- Tiga aksi eksplisit operator: **Setujui**, **Minta revisi**, **Tolak**.
- Tidak ada transisi otomatis ke APPROVED atau NEEDS_REVISION.
- Operator tahu kapan sebuah pengajuan siap diputuskan.

**Non-tujuan**

- Ambang skor otomatis atau rekomendasi otomatis dari sistem.
- Perubahan pada siklus monev. Laporan monev tidak pernah APPROVED/REJECTED
  (`FormSubmission.php`, BR-MON-02 `canProceed()`), dan itu tetap.
- Perubahan pada penugasan atau penggantian reviewer.

**Anti-tujuan**

- Pengajuan menggantung tanpa ada yang tahu ia menunggu keputusan.
- Jalur kode apa pun, selain Action keputusan operator, yang memindahkan status ke
  APPROVED, NEEDS_REVISION, atau REJECTED.

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

### 4.1 Kapan operator boleh memutuskan

Ketiga aksi hanya tersedia bila **semua** kondisi berikut terpenuhi (keputusan
pemilik):

- pengajuan berstatus SUBMITTED, UNDER_REVIEW, atau RESUBMITTED (sama dengan
  `AWAITING_DECISION` di `EvaluateSubmissionOutcome` sekarang). Yang belum
  UNDER_REVIEW dilewatkan ke UNDER_REVIEW lebih dulu oleh Action keputusan, seperti
  `settleInto()` sekarang;
- setiap `SubmissionReviewer` aktif sudah selesai (`completed` atau `not_required`,
  definisi `SubmissionReviewer::SETTLED_EVALUATION` yang sudah ada);
- fase yang butuh review punya minimal satu reviewer aktif (sama dengan ADR 0017:
  fase butuh review tanpa reviewer adalah salah konfigurasi).

Ini berlaku juga untuk **Tolak**, yang sekarang bisa ditekan kapan saja selama
UNDER_REVIEW. Reviewer yang tidak kunjung mengisi ditangani dengan mengganti
reviewer (BR-REV-10), bukan dengan memutuskan lebih dulu.

### 4.2 Tiga aksi

| Aksi | Syarat tambahan | Isian wajib | Transisi |
| --- | --- | --- | --- |
| **Setujui** | Tidak ada `ReviewSummary` `open`. Kalau ada, aksi ditolak dan pesannya menyebut jumlahnya. | — | UNDER_REVIEW → APPROVED |
| **Minta revisi** | Boleh walaupun ada `ReviewSummary` `open` — catatan itu justru alasan revisinya. | Pesan untuk peneliti | UNDER_REVIEW → NEEDS_REVISION |
| **Tolak** | — | Alasan (BR-REV-07, sudah ada) | UNDER_REVIEW → REJECTED |

- Setiap aksi adalah Action tersendiri (satu Action = satu use case). `RejectSubmission`
  sudah ada; Setujui dan Minta revisi menyusul polanya.
- Setiap aksi tercatat di audit trail (BR-RPT-09) beserta operator yang memutuskan.
- Pesan Minta revisi terlihat oleh peneliti di halaman pengajuannya.
- Siapa yang boleh memutuskan: pemegang permission yang sama dengan Tolak sekarang
  (`submissions.view-all`).

### 4.3 Yang dicabut

- Transisi otomatis di `EvaluateSubmissionOutcome`. Action itu berhenti mengubah
  status. Pemanggilnya (`SubmitEvaluationForm`, `ResolveReviewSummary`) tidak lagi
  memicu keputusan, tapi memicu pemberitahuan "siap diputuskan" atau "bisa disetujui"
  (§4.5).
- BR-REV-05 dan BR-REV-06 diganti aturan keputusan operator.
- ADR 0017 (persetujuan hampa) diganti: fase tanpa review juga menunggu keputusan
  operator.
- Skenario `[REV-OUTCOME-01]`, `[REV-OUTCOME-04]`, `[REV-OUTCOME-05]`, `[REV-OUTCOME-09]`
  ditulis ulang. `[REV-OUTCOME-02]`, `[REV-OUTCOME-03]`, `[REV-OUTCOME-06]`,
  `[REV-OUTCOME-10]` dipertahankan dengan makna "siap diputuskan", bukan "berpindah
  status". `[REV-OUTCOME-07]` dan `[REV-OUTCOME-08]` tetap.

### 4.4 Fase tanpa review

Pengajuan pada fase dengan `needs_review = false` tidak punya reviewer. Ia langsung
**siap diputuskan** setelah disubmit, dan operator memutuskannya dengan tiga aksi yang
sama. Untuk masuk APPROVED/NEEDS_REVISION/REJECTED, Action keputusan lebih dulu
melewatkannya ke UNDER_REVIEW, seperti `settleInto()` sekarang, supaya state machine
tidak dilebarkan.

### 4.5 Operator tahu kapan harus memutuskan

- **Hanya pengajuan utama** (tanpa `parent_submission_id`). Laporan monev tidak pernah
  "siap diputuskan", tidak masuk penyaring, hitungan dashboard, maupun pemberitahuan
  (monev tidak berubah, §2; BR-MON-02).
- Operator pemegang `submissions.view-all` diberi tahu in-app **setiap kali "siap
  diputuskan" berubah dari tidak menjadi ya, apa pun pemicunya**: reviewer terakhir
  selesai, reviewer yang belum selesai diganti atau dijadikan `not_required`, fase tanpa
  review disubmit, atau pengajuan dikirim ulang. Karena keadaannya dihitung, setiap
  Action pemicu membandingkan keadaan sebelum dan sesudah aksinya sendiri; tidak ada
  kolom bendera (**keputusan penulis**).
- Pemberitahuan terpisah **"bisa disetujui"** dikirim saat catatan terbuka terakhir
  diselesaikan pada pengajuan yang sudah siap diputuskan, karena saat itulah tombol
  Setujui menjadi aktif (**keputusan penulis**).
- Daftar pengajuan operator punya penyaring **"Menunggu keputusan"**, dan dashboard
  operator menghitungnya.
- "Siap diputuskan" adalah keadaan turunan (computed), bukan kolom atau status baru,
  sejajar `canProceed()` (BR-MON-02) dan `isArchived()` (BR-SM-13).

### 4.6 Layar keputusan

Satu layar pada detail pengajuan, untuk operator:

- skor setiap reviewer per Form Evaluasi, beserta versi lembarnya (dari desain fase;
  sebelum desain fase berjalan, tanpa label versi);
- catatan diskusi reviewer (`ReviewSummary`), terbuka dan selesai, dengan jumlah yang
  masih terbuka;
- tiga tombol aksi. Tombol yang tidak memenuhi syarat dinonaktifkan dengan
  penjelasan, misalnya "2 reviewer belum selesai" atau "1 catatan masih terbuka".

### 4.7 Pemberitahuan ke peneliti

Tabel event di `ddd/generic/03_notification.md` sudah memuat `ProposalApproved`,
`ProposalRejected`, dan `RevisionRequested`. Ketiganya kini dipicu aksi operator.
Kelas notifikasinya belum ditemukan di `app/Notifications/`; builder memastikan
keberadaannya dan melengkapinya bila belum ada.

### 4.8 Pengajuan yang sedang berjalan saat rilis

- Pengajuan yang sudah APPROVED/NEEDS_REVISION/REJECTED tidak disentuh.
- Pengajuan yang sedang UNDER_REVIEW atau SUBMITTED tidak lagi akan berpindah otomatis;
  begitu memenuhi §4.1, ia muncul sebagai "menunggu keputusan".
- Pengajuan yang **sudah** memenuhi syarat otomatis saat rilis tapi belum dipicu (mis.
  semua reviewer selesai, lalu tidak ada pemicu lagi) otomatis muncul di penyaring
  "Menunggu keputusan" karena keadaannya dihitung, bukan disimpan.

### 4.9 Setelah revisi

Keputusan pemilik R1, pilihan (a):

- "Reviewer menilai ulang" berarti reviewer memeriksa apakah catatannya sudah dipenuhi
  lalu **menutup catatannya**. Lembar penilaian tidak diisi ulang, skor tetap, dan
  BR-REV-08 tidak berubah.
- Saat peneliti mengirim ulang, pengajuan langsung siap diputuskan (semua reviewer
  sudah selesai sejak putaran pertama), dan operator diberi tahu (§4.5). Setujui tetap
  terkunci selama masih ada catatan terbuka.
- Kalau operator menekan Minta revisi tanpa ada catatan reviewer yang terbuka, revisi
  itu sepenuhnya diperiksa operator sendiri: begitu dikirim ulang, Setujui langsung
  aktif. Ini perilaku yang disengaja, bukan celah.

---

## 5. Dampak pada DDD, ADR, BDD

| Dokumen | Yang berubah |
| --- | --- |
| `ddd/core/02_review.md` | BR-REV-05 dan BR-REV-06 diganti aturan keputusan operator (§4.1–4.2); aturan baru: Setujui ditolak selama ada `ReviewSummary` `open`; Minta revisi wajib berpesan; Tolak juga menunggu semua reviewer selesai |
| `ddd/core/01_submission.md` | event `ProposalApproved` dipicu aksi operator, bukan otomatis |
| `docs/adr/0017-…` | digantikan ADR baru: fase tanpa review menunggu keputusan operator |
| `docs/adr/0006-…` | substansinya tetap; diperluas ke tiga keputusan |
| `ddd/generic/03_notification.md` | pemberitahuan "siap diputuskan" untuk Operator |
| `bdd/core/review/outcome.feature` | skenario ditulis ulang sesuai §4.3; skenario baru untuk tiga aksi, syarat-syaratnya, pemberitahuan, dan fase tanpa review |

## 6. Keputusan pemilik (2026-11-10)

1. Disetujui, perlu revisi, dan ditolak diputuskan manual oleh operator setelah melihat
   skor dan catatan reviewer.
2. Operator hanya boleh memutuskan setelah semua reviewer selesai.
3. Setujui ditolak selama masih ada catatan reviewer yang terbuka.
4. Minta revisi wajib berpesan; operator diberi tahu saat pengajuan siap diputuskan;
   setelah revisi, reviewer menilai ulang lalu operator memutuskan lagi; fase tanpa
   review juga menunggu keputusan operator.
5. **R1 (a):** setelah revisi, reviewer menutup catatannya bila sudah dipenuhi; lembar
   penilaian tidak diisi ulang dan skor tetap.

### Keputusan penulis (untuk diperiksa pemilik)

- Syarat "semua reviewer selesai" berlaku juga untuk Tolak (§4.1).
- "Siap diputuskan" dihitung, bukan status baru; state machine tidak diubah (§4.5).
- Pemberitahuan "siap diputuskan" hanya in-app, dikirim pada setiap peralihan tidak→ya
  apa pun pemicunya, ditambah pemberitahuan "bisa disetujui" (§4.5).
- Laporan monev dikecualikan dari "siap diputuskan" (§4.5).
- Permission keputusan = `submissions.view-all`, sama dengan Tolak sekarang (§4.2).

## 7. Diserahkan ke builder

- Nama dan pemecahan Action, nama route, dan bentuk FormRequest/DTO.
- Tata letak layar keputusan, mengikuti `docs/design/fe-v2-design.md`.
- Apakah `EvaluateSubmissionOutcome` dihapus, diganti nama, atau dijadikan kueri
  "siap diputuskan".
- Penyimpanan pesan Minta revisi, selama terlihat peneliti dan tercatat di audit.

## 8. Pertanyaan terbuka

Tidak ada yang menghalangi. R1 diputuskan pemilik (§4.9, §6 butir 5).
