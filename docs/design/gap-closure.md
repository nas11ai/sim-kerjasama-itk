# Penutupan Gap — Target Luaran, Anggota, Notifikasi, Status Terminal

**Versi:** 1.0
**Tanggal:** 2026-09-04
**Status:** Menunggu review

Menyusun [spec cakupan BDD](bdd-coverage/) memunculkan lima kebutuhan bisnis yang
aturannya sudah ada di DDD tapi **belum punya antarmuka**, satu kontradiksi langsung
antara wireframe dan aturan, dan satu pertanyaan yang belum terjawab. Dokumen ini
menutup ketujuhnya di tiga lapis sekaligus: aturan, skenario, dan layar.

Register terkait: [#9, #10, #11](../requirement-analysis/bdd/open-questions.md).

---

## 1. Target Luaran — di mana ia hidup

**Masalah.** `SubmissionTargetOutput` lahir dari keputusan register #5, jadi ia tidak
pernah masuk wireframe. Wizard tujuh langkah tidak punya tempat untuknya, dan
`RESEARCHER-06-*` yang bernama "Luaran" ternyata `ResearchOutput` — entitas lain.

**Keputusan.** Target luaran masuk **langkah 3**, bergabung dengan Skema & TRL.

Alasannya bukan kerapian melainkan urutan data: BR-SM-14 membatasi pilihan tipe dengan
`scheme.rules.allowed_output_types`, jadi target **tidak bisa** dipilih sebelum skema
ditentukan. Langkah 3 adalah titik paling awal di mana pembatas itu sudah diketahui.
Jumlah langkah tetap tujuh — [ADR 0012](../adr/0012-wizard-driven-submission-lifecycle.md)
tidak dilanggar.

**Konsekuensi kedua.** BR-SM-15 membolehkan target diubah sampai REJECTED/WITHDRAWN,
termasuk setelah APPROVED. Wizard tidak bisa jadi satu-satunya pintu, karena wizard
selesai saat submit. Karena itu target juga mendapat **tab tersendiri di halaman
detail** (`RESEARCHER-02-11`) yang hidup sepanjang siklus.

| Lapis | Wujud |
| ----- | ----- |
| DDD | Tabel wizard `core/01_submission.md` langkah 3 kini menulis ke `submission_target_outputs` |
| Skenario | `[SM-TARGET-01..09]` — sudah dialokasikan di [M1](bdd-coverage/m1-submission-budget.md) |
| UI | `RESEARCHER-02-07` (wizard) dan `RESEARCHER-02-11` (tab detail) |

Layar detail sengaja memuat dua pernyataan yang menegakkan BR-SM-15: target tetap bisa
diubah setelah disetujui, dan **hanya ketua pengaju** yang boleh mengubahnya —
Co-Investigator hanya membaca. Yang terakhir ini berbeda dari BR-RO-07 pada luaran
realisasi, dan perbedaannya disengaja.

---

## 2. Wizard yang hanya punya dua langkah

**Masalah.** Label ketujuh langkah ada di wireframe, tapi hanya langkah 1 dan 5 yang
pernah punya isi. Lima langkah lain tidak pernah didesain — termasuk langkah 4 yang
memuat pengecekan konflik kepentingan (BR-SM-08).

**Keputusan.** Kelima langkah dibuatkan layarnya, masing-masing menegakkan aturan yang
sudah ada:

| Langkah | Frame | Aturan yang terlihat di layar |
| ------- | ----- | ----------------------------- |
| 2 Informasi Dasar | `RESEARCHER-02-06` | BR-FE-11 — field yang diwajibkan **setelah** pengajuan dibuat tidak menghalangi submit |
| 3 Skema, TRL & Target | `RESEARCHER-02-07` | BR-SCH-05 (TRL disaring skema), BR-SM-14 (tipe target dibatasi skema) |
| 4 Anggota Penelitian | `RESEARCHER-02-08` | BR-SM-08 — penolakan eksplisit saat orang yang ditambahkan sudah jadi reviewer |
| 6 Upload Berkas | `RESEARCHER-02-09` | BR-SM-02 — proposal PDF wajib sebelum submit |
| 7 Review & Submit | `RESEARCHER-02-10` | BR-SM-04 (hanya ketua yang bisa submit), BR-FE-11 (validasi penuh baru di sini) |

Langkah 4 mendapat perhatian khusus: pesan penolakan COI ditulis sebagai kalimat yang
menjelaskan **kenapa**, bukan sekadar "tidak dapat ditambahkan". Aturan yang ditolak
tanpa alasan akan dilaporkan sebagai bug oleh penggunanya.

---

## 3. Notifikasi tanpa rumah

**Masalah.** BR-NOT-01 dan BR-NOT-04 mengatur notifikasi in-app yang tersimpan permanen
dan ditandai dibaca saat diklik. Di 57 frame tidak ada satu pun daftar notifikasi —
hanya kartu KPI "4 belum dibaca" yang tidak menuju ke mana-mana.

**Keputusan.** Frame baru `SHARED-02-05`, dijangkau dari Aksi Cepat di dashboard.

Layarnya memuat dua pernyataan yang menegakkan aturan: notifikasi ditandai dibaca
**saat dibuka, bukan saat halaman daftar dibuka** (BR-NOT-04), dan peristiwa ringan
cukup in-app tanpa email (BR-NOT-02). Keduanya keputusan yang mudah salah
diimplementasikan kalau hanya dibaca dari tabel aturan.

Skenario `[NOT-INAPP-01..03]` dan `[NOT-CHANNEL-01..02]` di
[M5](bdd-coverage/m5-reporting-notification-config.md) kini punya layar yang dirujuk.

---

## 4. Tiga status tanpa wajah

**Masalah.** `REJECTED`, `WITHDRAWN`, dan `RESUBMITTED` tidak pernah dirender sebagai
status di satu pun frame — padahal ketiganya justru status yang paling perlu jelas bagi
peneliti. Ini yang selama ini tercatat samar sebagai register #3 "frontend 5 nilai vs
backend 8".

**Keputusan.** Ketiganya masuk daftar pengajuan, dan `REJECTED` mendapat halaman detail
sendiri (`RESEARCHER-02-12`).

Halaman penolakan memuat tiga hal yang tidak ada di halaman detail biasa:

1. **Alasan penolakan beserta siapa yang memutuskan.** Penolakan adalah keputusan
   manual (BR-REV-07), jadi selalu ada manusia yang bertanggung jawab.
2. **Skor evaluasi yang akhirnya terbuka.** BR-REV-12 menahan skor sampai APPROVED atau
   REJECTED; halaman ini adalah salah satu dari dua tempat skor itu boleh muncul.
3. **Pernyataan bahwa penolakan tidak pernah otomatis.** Ini menutup salah paham yang
   paling mungkin muncul dari kode legacy yang justru masih melakukannya
   ([ADR 0006](../adr/0006-rejection-is-manual-only.md)).

---

## 5. Anggaran melebihi batas — kontradiksi yang diperbaiki

**Masalah.** `RESEARCHER-02-04` menyatakan anggaran yang melewati batas skema **tetap
dapat disubmit**. BR-BUD-01 melarangnya.

**Keputusan.** Aturan menang; kalimat di wireframe diganti. Layar kini menyatakan
pengajuan tidak dapat disubmit selama total masih melebihi batas.

Anggaran menyangkut kepatuhan dana dan salahnya baru ketahuan saat audit. Kalau LPPM
justru menghendaki submit tetap boleh dengan Operator sebagai pengadil, itu keputusan
sah — tapi berarti **BR-BUD-01 yang diubah**, bukan UI yang diam-diam melunak.

---

## 6. Keterlihatan hasil evaluasi monev

**Masalah.** Dua layar menerapkan gerbang berbeda: pengajuan menahan skor sampai
APPROVED/REJECTED, monev menampilkannya begitu reviewer submit.

**Keputusan.** Monev **dikecualikan secara eksplisit** lewat BR-MON-09 yang baru,
bukan dibiarkan sebagai tafsir bebas atas BR-REV-12.

Alasannya kebutuhan bisnis: monev bersifat formatif. Tujuannya mengoreksi arah kegiatan
selagi masih berjalan, dan itu mustahil kalau catatan reviewer baru terbaca setelah
siklusnya berakhir. Menyamakan keduanya akan membuat monev kehilangan gunanya.

Pengecualian ini dijaga agar tetap sempit: `[MON-REVIEW-09]` membuktikan gerbang
pengajuan awal **tidak** ikut longgar.

---

## 7. Ringkasan perubahan

| Lapis | Perubahan |
| ----- | --------- |
| DDD | Langkah 3 wizard menulis `submission_target_outputs`; BR-MON-09 baru; BR-REV-12 menautkan pengecualiannya; BR-IAM-11 baru (penunjukan reviewer) |
| Skenario | `[MON-REVIEW-07..09]` baru; `[SM-WIZARD-09]` baru; total 186 ID dialokasikan |
| UI | 8 frame baru (57 → 65), 6 frame disunting, 4 fixture baru |

Yang **tidak** dikerjakan dokumen ini: mengimplementasikan skenarionya. Layar dan
aturannya kini ada, jadi skenario yang selama ini terhalang sudah bisa dikerjakan —
itu pekerjaan M1, M4, dan M5 yang punya jalurnya sendiri.
