# 0018 — Hasil review dihitung di akhir putaran; persetujuan oleh Operator

**Status:** Diterima
**Tanggal:** 2026-11-10
**Menggantikan:** [ADR 0017](0017-vacuous-approval-only-when-configured.md)
**Memperluas:** [ADR 0006](0006-rejection-is-manual-only.md)
**Desain:** [`docs/design/2026-11-10-keputusan-operator-design.md`](../design/2026-11-10-keputusan-operator-design.md)

## Konteks

Sampai ADR ini, `EvaluateSubmissionOutcome` memutuskan hasil review secara otomatis
setiap kali sebuah lembar penilaian dikirim atau sebuah catatan reviewer ditutup: semua
reviewer selesai tanpa catatan terbuka → APPROVED (BR-REV-05), dengan catatan terbuka →
NEEDS_REVISION (BR-REV-06). ADR 0017 menambahkan persetujuan hampa untuk fase dengan
`needs_review = false`.

Pemilik produk menetapkan bahwa skor dan catatan reviewer adalah **masukan**, dan bahwa
disetujui dan ditolak adalah **keputusan Operator**. Revisi tetap wewenang reviewer.
Pembagian itu sama dengan sistem lama (evolusi-simpas). Saat merancangnya ditemukan tiga
celah pada aturan otomatis:

1. **Persetujuan tanpa manusia.** Pengajuan bisa APPROVED tanpa ada yang membaca skornya,
   padahal skor adalah dasar keputusan pendanaan.
2. **Reviewer terakhir tidak bisa meminta revisi.** Catatan baru boleh dibuka setelah
   evaluasi reviewer itu selesai (BR-FE-06), sedangkan pengiriman lembar terakhir langsung
   memicu keputusan. Hasil sudah jatuh sebelum reviewer terakhir sempat menulis catatan.
3. **Revisi berulang sebelum semua reviewer memeriksa.** Setelah kirim ulang, setiap
   penutupan catatan memicu keputusan. Selama catatan reviewer lain masih terbuka,
   pengajuan langsung kembali ke NEEDS_REVISION sebelum reviewer lain sempat memeriksa.

## Keputusan

**Hasil review dihitung di akhir putaran review, dan APPROVED hanya lewat aksi Operator.**

- **Putaran review** (BR-REV-16). Putaran penilaian dibuka saat reviewer ditugaskan;
  pesertanya semua reviewer aktif, masing-masing menekan *Selesai menilai* setelah
  evaluasinya `completed`. Putaran pemeriksaan dibuka saat peneliti mengirim ulang;
  pesertanya reviewer aktif yang punya catatan `open`, masing-masing menekan *Selesai
  memeriksa*. `not_required` dihitung selesai. Tanda Selesai per reviewer per putaran dan
  tidak bisa dibatalkan.
- **Kesimpulan putaran.** Begitu peserta terakhir Selesai, hasilnya dihitung sekali: ada
  catatan `open` → NEEDS_REVISION serentak (BR-REV-06); tidak ada → pengajuan **siap
  diputuskan** dan Operator diberi tahu. Mengirim lembar penilaian atau menutup catatan
  tidak lagi memicu apa pun.
- **Keputusan Operator** (BR-REV-05, BR-REV-07). Setujui dan Tolak hanya untuk pengajuan
  yang siap diputuskan; Setujui juga ditolak selama masih ada catatan `open`. Tidak ada
  jalur kode lain ke APPROVED atau REJECTED.
- **Fase tanpa review** (menggantikan ADR 0017). Pengajuan pada fase `needs_review = false`
  langsung siap diputuskan setelah disubmit dan menunggu Setujui/Tolak Operator. Fase yang
  butuh review tanpa reviewer tetap tidak siap — salah konfigurasi, bukan persetujuan.
- **Catatan hanya selama putaran Anda** (BR-REV-17). Catatan baru untuk pengajuan utama
  hanya boleh dibuka peserta putaran berjalan yang belum Selesai, supaya tidak ada catatan
  yang lahir setelah putaran disimpulkan dan membuat pengajuan buntu.

"Siap diputuskan" adalah keadaan turunan dari data putaran, bukan status baru. State
machine `SubmissionStatus` tidak berubah: Action keputusan melewatkan pengajuan ke
UNDER_REVIEW lebih dulu bila perlu, seperti sebelumnya.

## Konsekuensi

- Tidak ada pengajuan yang APPROVED tanpa aksi Operator; jejak audit selalu menyebut siapa
  yang menyetujui.
- Reviewer terakhir sempat menulis catatan setelah mengirim lembarnya (celah 2), dan
  revisi dikembalikan ke peneliti sekaligus setelah semua pemeriksa selesai (celah 3).
- Reviewer yang tidak kunjung menekan Selesai menahan putaran. Jalan keluarnya mengganti
  reviewer (BR-REV-10): pengganti menjadi peserta yang belum selesai.
- Satu tabel putaran dan satu tabel peserta baru; pengajuan yang sedang berjalan saat
  rilis diberi putaran lewat migrasi data.
- `EvaluateSubmissionOutcome` dihapus. Kesimpulan putaran (`ConcludeReviewRound`) menjadi
  satu-satunya pemicu otomatis, dan ia hanya mengenal NEEDS_REVISION — `[REV-OUTCOME-08]`
  tetap dijaga di sana.

## Alternatif yang ditolak

**(B) Catatan sebelum lembar dikirim.** Membuka catatan sebelum evaluasi `completed`
menutup celah 2 tanpa konsep baru, tapi melanggar BR-FE-06: catatan revisi ditulis setelah
reviewer menilai keseluruhan, bukan di tengah mengisi lembar. Celah 3 juga tidak tertutup.

**(C) Membuka catatan langsung memicu revisi.** Mengikuti sistem lama secara harfiah —
catatan pertama memindahkan pengajuan ke NEEDS_REVISION. Peneliti lalu menerima revisi
bertahap dari setiap reviewer dan mengirim ulang berkali-kali, dan reviewer lain kehilangan
kesempatan memeriksa versi yang sama. Pemilik memilih revisi serentak (R2, R3).

**Mempertahankan APPROVED otomatis dan hanya menambah Setujui sebagai jalan pintas.**
Dua pintu ke APPROVED, dan pintu otomatisnya tetap menyetujui tanpa ada yang membaca skor.
