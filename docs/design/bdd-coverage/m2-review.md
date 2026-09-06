# M2 — Review

**17 BR perilaku · 44 skenario · 7 berkas `.feature`**

Milestone paling berisiko. Di sinilah kode warisan fork **sudah terbukti bertentangan
dengan DDD**: auto-reject yang melanggar BR-REV-07 dan BR-REV-14. Skenario di sini
bukan cuma menetapkan perilaku baru — ia menyatakan perilaku lama sebagai terlarang.

Sumber: [`core/02_review.md`](../../requirement-analysis/ddd/core/02_review.md),
[ADR 0006](../../adr/0006-rejection-is-manual-only.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `core/review/assignment.feature` | `REV-ASSIGN-01..09` | BR-REV-01, BR-REV-02, BR-REV-03, BR-REV-04, BR-REV-09 |
| `core/review/evaluation.feature` | `REV-EVAL-01..06` | BR-REV-08 (≡BR-FE-05), BR-FE-06 |
| `core/review/outcome.feature` | `REV-OUTCOME-01..10` | BR-REV-05, BR-REV-06, BR-REV-07, BR-REV-14 |
| `core/review/discussion.feature` | `REV-DISCUSS-01..06` | BR-FE-06, BR-REV-06, BR-REV-15 |
| `core/review/reassignment.feature` | `REV-REASSIGN-01..03` | BR-REV-10 |
| `core/review/visibility.feature` | `REV-VIS-01..06` | BR-REV-11, BR-REV-12 |
| `generic/form_engine/phase_window.feature` | `FE-WINDOW-01..04` | BR-FE-17 |

Semua skenario bertag `@M2`.

---

## 2. Penugasan reviewer

Jumlah minimum berasal dari `scheme.rules.min_reviewer_count` — **bukan konstanta di
kode**. Batas beban dari `scheme.rules.max_reviewer_workload`.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `REV-ASSIGN-01` | happy | Operator menugaskan reviewer pertama; submission tetap SUBMITTED | BR-REV-01 | OPERATOR-03-01 |
| `REV-ASSIGN-02` | boundary | Belum mencapai minimum — status tidak berubah, Operator melihat indikator "1 dari minimal 2" | BR-REV-01 | OPERATOR-03-01 |
| `REV-ASSIGN-03` | happy | Reviewer terakhir memindahkan submission ke UNDER_REVIEW dan memicu notifikasi | BR-REV-01 | OPERATOR-03-01 |
| `REV-ASSIGN-04` | denial | `submitted_by` tidak bisa jadi reviewer submission-nya sendiri | BR-REV-02 | OPERATOR-03-01 |
| `REV-ASSIGN-05` | denial | Reviewer yang sama di-assign dua kali dalam satu siklus ditolak | BR-REV-03 | OPERATOR-03-01 |
| `REV-ASSIGN-06` | boundary | Beban melebihi `max_reviewer_workload` memberi **warning, bukan penolakan** | BR-REV-04 | OPERATOR-03-01 |
| `REV-ASSIGN-07` | denial | Anggota penelitian tidak bisa jadi reviewer submission yang sama | BR-REV-02 + BR-SM-08 + BR-IAM-07 | OPERATOR-03-01 |
| `REV-ASSIGN-08` | denial | Reviewer di luar rentang `start_date`–`end_date` tidak bisa ditugaskan | BR-REV-09 | OPERATOR-08-04 |
| `REV-ASSIGN-09` | happy | Setiap reviewer yang ditugaskan menerima notifikasi `ReviewerAssigned` | BR-REV-01 | REVIEWER-03-01 |

`REV-ASSIGN-06` sengaja `@boundary` dan bukan `@denial`: aturannya memang **warning,
bukan hard block**. Kalau diimplementasikan sebagai penolakan, Operator kehilangan
kemampuan menugaskan di masa sibuk — dan itu keputusan produk, bukan bug.

`REV-ASSIGN-07` membawa tiga tag karena tiga BR menyatakan aturan yang sama dari tiga
sisi. Satu Policy, dipanggil dari penugasan reviewer **dan** dari penambahan anggota.

---

## 3. Evaluasi dan penguncian

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `REV-EVAL-01` | happy | Reviewer menyimpan `ReviewFormResponse` sebagai draft dan menyuntingnya lagi | BR-REV-08 + BR-FE-05 | REVIEWER-03-03 |
| `REV-EVAL-02` | denial | Setelah `status = submitted`, `ReviewFormResponse` tidak bisa disunting | BR-REV-08 + BR-FE-05 | REVIEWER-03-03 |
| `REV-EVAL-03` | happy | Submit evaluasi mengubah `evaluation_status` menjadi `completed` | BR-REV-08 | REVIEWER-03-03 |
| `REV-EVAL-04` | denial | Membuat `ReviewSummary` sebelum `evaluation_status = completed` ditolak | BR-FE-06 | REVIEWER-03-04 |
| `REV-EVAL-05` | happy | `ReviewSummary` bisa dibuat setelah `completed` | BR-FE-06 | REVIEWER-03-04 |
| `REV-EVAL-06` | boundary | `evaluation_status = not_required` juga membuka pembuatan `ReviewSummary` | BR-FE-06 | REVIEWER-03-04 |

**Kanonik BR-FE-05, dicerminkan BR-REV-08** — satu-satunya pasangan yang teksnya
benar-benar identik kata per kata di dua dokumen. `REV-EVAL-01/02` bertag keduanya.
`ReviewFormResponse` entitas Form Engine, jadi penguncian itu tinggal di sana; BC Review
menyebutnya ulang supaya bisa dibaca utuh.

---

## 4. Hasil review — bagian paling berisiko

Dua transisi **otomatis** dan satu yang **haram otomatis**. Ketiganya bergantung pada
kombinasi yang sama: semua `evaluation_status = completed`, lalu ada/tidaknya
`ReviewSummary` berstatus `open`.

| ID | Kelas | Yang dibuktikan | BR |
| -- | ----- | --------------- | -- |
| `REV-OUTCOME-01` | happy | Semua reviewer `completed` **dan** tidak ada summary `open` → APPROVED otomatis | BR-REV-05 |
| `REV-OUTCOME-02` | boundary | Satu reviewer masih `pending` → status tetap UNDER_REVIEW, tidak ada transisi | BR-REV-05 |
| `REV-OUTCOME-03` | boundary | Semua `completed` tapi ada summary `open` → **bukan** APPROVED | BR-REV-05 |
| `REV-OUTCOME-04` | happy | Semua `completed` **dan** ada summary `open` → NEEDS_REVISION otomatis | BR-REV-06 |
| `REV-OUTCOME-05` | boundary | Summary terakhir berubah `resolved` → APPROVED, bukan NEEDS_REVISION | BR-REV-06 |
| `REV-OUTCOME-06` | boundary | Reviewer `not_required` ikut dihitung selesai — tanpa ini submission terkunci selamanya | BR-REV-05 |
| `REV-OUTCOME-07` | happy | Operator menolak lewat aksi eksplisit → REJECTED | BR-REV-07 |
| `REV-OUTCOME-08` | denial | **Tidak ada** kombinasi evaluasi yang menghasilkan REJECTED tanpa aksi Operator | BR-REV-07 + BR-REV-14 |

`REV-OUTCOME-06` menutup kebuntuan yang tadinya tersembunyi di bunyi aturan: BR-REV-05
semula menulis "semua `evaluation_status = completed`", dan dibaca harfiah seorang
reviewer `not_required` tidak akan pernah menjadi `completed` — submission-nya terkunci
tanpa ada yang salah. Bunyi BR-nya sudah dikoreksi.

`REV-OUTCOME-08` adalah skenario terpenting di milestone ini. Ia menyatakan sebuah
**ketiadaan** — bahwa tidak ada jalur kode yang men-transisikan submission ke REJECTED
secara otomatis. Bentuknya: semua reviewer selesai dengan skor serendah apa pun,
submission tetap tidak REJECTED.

> Menguji ketiadaan lewat satu skenario tidak pernah lengkap — ia membuktikan satu
> jalur, bukan semua. Karena itu BR-REV-14 juga perlu penjagaan struktural: pencarian
> `transitionTo(Rejected::class)` di luar Action `RejectSubmission` harus nol. Itu
> **bukan** skenario Gherkin melainkan guard test, dan tempatnya
> `non-behavioral.md` sebagai catatan penegakan tambahan — bukan pengganti
> `REV-OUTCOME-08`.

### Kasus hampa BR-REV-05 — diputuskan di M2

Ada kebuntuan **kedua** yang bentuknya sama tapi sumbernya berbeda: fase dengan
`needs_review = false` menghasilkan pengajuan yang tidak punya reviewer sama sekali.
[ADR 0016](../../adr/0016-submit-never-approves.md) mencabut cabang yang dulu mencoba
menyetujuinya langsung dari `DRAFT`, dan menyisakan pertanyaan ini ke M2.

**Diputuskan lewat [ADR 0017](../../adr/0017-vacuous-approval-only-when-configured.md):
persetujuan hampa sah, tapi hanya kalau `needs_review = false` dinyatakan eksplisit.**

| Keadaan | Hasil | Skenario |
| ------- | ----- | -------- |
| `needs_review = false`, nol reviewer | BR-REV-05 terpenuhi hampa → APPROVED | `REV-OUTCOME-09` |
| `needs_review = true`, nol reviewer ditugaskan | tetap SUBMITTED — salah konfigurasi | `REV-OUTCOME-10` |

Justru perbedaan keduanya yang jadi aturannya. Tanpa `REV-OUTCOME-10`, Operator yang
lupa menugaskan reviewer menghasilkan pengajuan yang menyetujui dirinya sendiri —
kelalaian konfigurasi berubah jadi persetujuan senyap.

Jalurnya tetap `SUBMITTED → UNDER_REVIEW → APPROVED` lewat `EvaluateSubmissionOutcome`
yang sama, tanpa perubahan state machine, sehingga `APPROVED` tetap hanya punya satu
pintu masuk.

Rentang `REV-OUTCOME` karenanya tumbuh 08 → 10, dan M2 menjadi **44 skenario**.
---

## 5. Diskusi terulir

`ReviewSummary` berstatus `open` atau `resolved`. **Tidak ada `closed`** — status itu
tidak pernah ada di domain ini (BR-REV-13, non-behavioral).

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `REV-DISCUSS-01` | happy | Reviewer membuat `ReviewSummary` `open` beserta `ReviewComment` pertama | REVIEWER-03-04 |
| `REV-DISCUSS-02` | happy | Researcher membalas di ulir yang sama; balasan terlihat reviewer | RESEARCHER-02-05 |
| `REV-DISCUSS-03` | happy | Reviewer menandai summary `resolved` setelah revisi memadai | REVIEWER-03-04 |
| `REV-DISCUSS-04` | denial | Researcher tidak bisa menandai summary-nya sendiri `resolved` | RESEARCHER-02-05 |
| `REV-DISCUSS-05` | boundary | Semua summary `resolved` memicu evaluasi ulang status submission | REVIEWER-03-04 |
| `REV-DISCUSS-06` | happy | Summary `open` milik reviewer yang di-`replaced` diwarisi penggantinya, dan pengganti itu yang me-resolve — penulis aslinya tetap terbaca | REVIEWER-03-04 |

`REV-DISCUSS-04` menjaga hal yang mudah terlewat: kalau researcher bisa me-resolve
sendiri, ia bisa memaksa submission-nya jadi APPROVED tanpa persetujuan reviewer.

---

## 6. Penggantian reviewer

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `REV-REASSIGN-01` | happy | Operator mengganti reviewer; record lama ditandai `replaced`, bukan dihapus | OPERATOR-03-02 |
| `REV-REASSIGN-02` | boundary | Evaluasi milik reviewer lama tetap terbaca sebagai riwayat | OPERATOR-03-02 |
| `REV-REASSIGN-03` | happy | Reviewer pengganti mendapat penugasan bersih dengan `evaluation_status = pending` | REVIEWER-03-01 |

`REV-REASSIGN-02` penting untuk integritas data: menghapus evaluasi lama berarti
menghapus jejak kenapa sebuah keputusan diambil.

---

## 7. Keterlihatan skor

Dua aturan berbeda, sering tertukar: siapa **antar reviewer**, dan kapan
**researcher**.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `REV-VIS-01` | happy | `reviewer_internal` melihat skor reviewer lain setelah semua selesai | BR-REV-11 | REVIEWER-03-04 |
| `REV-VIS-02` | denial | `reviewer_external` tidak melihat skor reviewer lain, kapan pun | BR-REV-11 | REVIEWER-03-04 |
| `REV-VIS-03` | boundary | `reviewer_internal` **belum** melihat skor selama masih ada yang `pending` | BR-REV-11 | REVIEWER-03-04 |
| `REV-VIS-04` | denial | Researcher tidak melihat skor selama UNDER_REVIEW | BR-REV-12 | RESEARCHER-02-03 |
| `REV-VIS-05` | happy | Researcher melihat skor setelah APPROVED | BR-REV-12 | RESEARCHER-02-03 |
| `REV-VIS-06` | happy | Researcher melihat skor setelah REJECTED | BR-REV-12 | RESEARCHER-02-03 |

`REV-VIS-04` memakai `@denial` karena aksi researcher membuka skor memang ditolak.
Bandingkan dengan `[FE-OVERRIDE-09]` di M0 yang membukanya berhasil tapi satu field
ditahan — di sana kelasnya bukan `@denial`.

---

## 8. Jendela kerja tiap tahap — BR-FE-17

Ditemukan saat audit tiga sumbu ([ADR 0014](../../adr/0014-time-boxed-access-single-override-mechanism.md)):
contoh lifecycle memberi Detail 1 (Pengajuan) dan Detail 2 (Evaluasi) deadline yang
sama, `"Batas Submit"`. Akibatnya reviewer tidak punya waktu kerja sama sekali, dan
revisi mustahil karena detail researcher-nya sudah terkunci saat status NEEDS_REVISION
tercapai.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `FE-WINDOW-01` | happy | Reviewer tetap bisa mengevaluasi setelah "Batas Submit" lewat, karena detail evaluasi punya `SubmissionDate` sendiri | REVIEWER-03-03 |
| `FE-WINDOW-02` | denial | Reviewer ditolak setelah **batas evaluasinya sendiri** lewat | REVIEWER-03-03 |
| `FE-WINDOW-03` | happy | Researcher bisa menyunting saat NEEDS_REVISION meski "Batas Submit" sudah lewat — jendela revisi terpisah | RESEARCHER-02-05 |
| `FE-WINDOW-04` | boundary | Dua detail yang sengaja berbagi satu `SubmissionDate` tetap sah; yang dilarang adalah berbagi tanggal antar detail dengan **aktor berbeda** | ADMIN-08-09 |

`FE-WINDOW-03` menutup pertentangan dengan BR-BUD-03, yang menjanjikan anggaran tetap
bisa disunting saat NEEDS_REVISION. Tanpa jendela revisi tersendiri, janji itu tidak
bisa ditepati.

`FE-WINDOW-04` menjaga aturan ini tidak jadi terlalu kaku: berbagi tanggal antar detail
dengan aktor yang sama — misalnya dua detail researcher berurutan — tetap wajar.

> **Berapa lama jendelanya belum diputuskan** — itu kebijakan LPPM. Terdaftar sebagai
> [register #12](../../requirement-analysis/bdd/open-questions.md) dengan tiga opsi dan
> rekomendasi. Skenario di atas menguji **bahwa jendelanya terpisah**, bukan berapa
> panjangnya, jadi keputusan itu tidak mengubahnya.

---

## 9. Frame yang tercakup

REVIEWER-03-01 · REVIEWER-03-02 · REVIEWER-03-03 · REVIEWER-03-04 · ADMIN-08-09 ·
OPERATOR-03-01 · OPERATOR-03-02 · OPERATOR-08-04 · OPERATOR-02-05 (Tolak Manual,
lewat `REV-OUTCOME-07`) · RESEARCHER-02-03 · RESEARCHER-02-05 · SHARED-02-02
(Dashboard Reviewer, lewat `REV-ASSIGN-09`)

REVIEWER-03-02 (Riwayat Review) tercakup `REV-REASSIGN-02`.

---

## 10. Yang menunggu keputusan

| # | Perkara | Dampak |
| - | ------- | ------ |
| ~~register #2~~ | **Terselesaikan 2026-09-04.** Arahnya tidak pernah jadi pertanyaan — ADR 0006 sudah memutuskannya. Yang tersisa pekerjaan, bukan keputusan: `[REV-OUTCOME-08]` mengunci perilakunya, dan penjagaan struktural (`transitionTo(Rejected::class)` di luar `RejectSubmission` harus nol) dicatat di `non-behavioral.md` |
| ~~baru~~ | **Terselesaikan 2026-09-04.** Ya — dibaca harfiah, `not_required` mengunci submission selamanya. BR-REV-05/06 dikoreksi; register #14 |
| ~~baru~~ | **Terselesaikan 2026-09-04.** Diwarisi reviewer pengganti — **BR-REV-15** baru; dikunci `[REV-DISCUSS-06]`; register #15 |
