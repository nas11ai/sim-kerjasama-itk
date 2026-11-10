# 0017 — Persetujuan hampa hanya kalau memang dikonfigurasi begitu

**Status:** Digantikan [ADR 0018](0018-review-outcome-at-round-end-operator-approves.md)
**Tanggal:** 2026-09-06
**Melanjutkan:** [ADR 0016](0016-submit-never-approves.md)

## Konteks

ADR 0016 mencabut cabang yang mencoba menyetujui submission langsung dari `DRAFT` pada
fase dengan `needs_review = false`. Cabang itu tidak pernah bekerja, dan mencabutnya
menyisakan satu pertanyaan yang sengaja ditunda ke M2: **fase yang tidak butuh review
berhenti di `SUBMITTED`, lalu apa?**

BR-REV-05 berbunyi: semua reviewer selesai **dan** tidak ada `ReviewSummary` berstatus
`open` → APPROVED. Dibaca harfiah pada submission tanpa reviewer sama sekali, syarat
pertama terpenuhi secara **hampa** — tidak ada reviewer yang belum selesai, karena tidak
ada reviewer.

Pembacaan hampa itu benar secara logika tapi berbahaya kalau dipakai tanpa syarat: fase
yang **seharusnya** direview tapi Operator-nya lupa menugaskan siapa pun akan ikut
memenuhi syarat yang sama, lalu menyetujui dirinya sendiri. Kelalaian konfigurasi
berubah jadi persetujuan senyap.

Perlu dicatat bahwa domain sudah memihak pembacaan hampa di satu tempat:
`canProceed()` di [ubiquitous language](../requirement-analysis/ddd/02_ubiquitous_language.md)
berbunyi "sudah disubmit DAN, **bila `needs_review = true`**, evaluasinya selesai tanpa
`ReviewSummary` open". Klausa bersyarat itu sudah menyatakan bahwa fase tanpa review
tidak menunggu apa pun.

## Keputusan

**Persetujuan hampa sah, tapi hanya kalau `needs_review = false` dinyatakan eksplisit.**

Dua hal yang tampak sama diperlakukan berbeda, dan justru perbedaan itulah aturannya:

| Keadaan | Hasil |
| ------- | ----- |
| `needs_review = false`, nol reviewer | BR-REV-05 terpenuhi hampa → **APPROVED** |
| `needs_review = true`, nol reviewer ditugaskan | **tetap SUBMITTED** — ini salah konfigurasi, bukan persetujuan |

Jalurnya tetap lewat `UNDER_REVIEW`: `SUBMITTED → UNDER_REVIEW → APPROVED`, dijalankan
`EvaluateSubmissionOutcome` yang sama dengan jalur review biasa. **Tidak ada perubahan
state machine.**

## Konsekuensi

- `APPROVED` tetap hanya bisa dimasuki dari `UNDER_REVIEW`. Satu pintu, dan itulah yang
  membuat `[REV-OUTCOME-08]` beserta BR-REV-14 tetap bisa dipercaya — kalau jalur kedua
  dibuka, pernyataan "tidak ada transisi otomatis ke REJECTED" jadi lebih sulit dijaga
  karena ada dua tempat yang harus diperiksa.
- Satu implementasi BR-REV-05, dipanggil dari satu tempat. Aturan cermin lintas konteks
  di [`ddd/README.md`](../requirement-analysis/ddd/README.md#aturan-cermin-lintas-konteks)
  tetap dipatuhi.
- Fase tanpa review melewati `UNDER_REVIEW` selama sepersekian detik meski tidak ada yang
  mereview. Itu terlihat aneh di jejak audit, dan diterima secara sadar: harganya satu
  baris riwayat, imbalannya satu pintu masuk ke APPROVED.
- Operator yang lupa menugaskan reviewer akan melihat submission menggantung di
  `SUBMITTED` — gejala yang benar untuk penyebabnya, dan jauh lebih mudah didiagnosis
  daripada pengajuan yang disetujui tanpa ada yang membacanya.

Dikunci `[REV-OUTCOME-09]` (hampa yang sah) dan `[REV-OUTCOME-10]` (salah konfigurasi
yang tidak boleh menyetujui).

## Alternatif yang ditolak

**Memperlebar state machine dengan `allowTransition(Submitted, Approved)`.** Menghemat
satu transisi perantara, tapi menambah pintu kedua ke APPROVED. Ditolak dengan alasan
yang sama seperti ADR 0016: jumlah pintu masuk ke status terminal adalah hal yang ingin
dijaga tetap satu.

**Memperlakukan nol reviewer selalu sebagai persetujuan hampa, tanpa memeriksa
`needs_review`.** Paling sedikit kodenya, dan itu justru masalahnya — ia menghapus
perbedaan antara "memang tidak perlu direview" dan "belum sempat ditugaskan". Yang
pertama keputusan, yang kedua kelalaian; hasilnya tidak boleh sama.

**Melarang `needs_review = false` sama sekali dan mewajibkan setiap fase punya reviewer.**
Konsisten, tapi bertentangan dengan kebutuhan bisnis: sebagian fase memang administratif
belaka — unggah kelengkapan, konfirmasi kehadiran — dan memaksakan reviewer di sana
menciptakan pekerjaan yang tidak ada gunanya.
