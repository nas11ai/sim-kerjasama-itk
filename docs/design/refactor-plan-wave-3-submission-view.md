# Gelombang 3 — SubmissionViewController keluar dari daftar legacy

**Status:** selesai — `SubmissionViewController` keluar dari `quality-legacy.txt` (plafon 99 → 98).
Diukur pada `7285bc1`.

## Pengukuran awal

1212 baris, sembilan aksi. `adminShowSubmission()` 253 loc cc 15, `userShowSubmission()` 242 loc cc 13,
`reviewerShowSubmission()` 117 loc; tujuh method lain di atas 40 loc. Blok muat-jawaban, muat-komentar,
dan kartu reviewer tersalin tiga kali.

## Temuan — diverifikasi di kode

**F1 — Daftar periode Operator 500 di PostgreSQL.** `selectRaw` memakai `status = "approved"`; petik
ganda berarti nama kolom, jadi kueri gagal begitu ada satu periode. `pending_review` juga menghitung
`status != approved`, sehingga yang ditolak dan ditarik terhitung "menunggu review".

**F2 — Pengajuan terdaftar di periode yang salah.** Keempat layar periode memetakan pengajuan ke periode
lewat `form → formAccessControls → formPhaseDetails → formPhase → submissionPeriodPhases`. Pivot itu
tanpa unique constraint (lihat migration `add_relations_to_form_submissions`), jadi fase yang dipakai
dua periode memunculkan pengajuan di keduanya. Layar periode peneliti bahkan mencari per `form_id`
dengan `first()` tanpa periode sama sekali.

**F3 — Filter status layar periode Operator mati.** Server mengirim `SubmissionStatus::options()` (map
`Kelas => label`); layar mengharapkan `{value, label}[]`, dan nama kelas tak pernah cocok dengan kolom
`status` yang menyimpan `approved`.

**F4 — Tombol ulir reviewer tidak sepakat dengan guard-nya.** Layar peneliti menurunkan
`hasPendingEvaluations` dari hitungan form wajib sendiri, sementara `OpenReviewThread` dan penutupan ulir
menggerbang lewat `SubmissionReviewer::hasSettledEvaluation()` (BR-FE-06). Evaluasi `pending` tanpa form
wajib ditawari tombol yang ditolak; `not_required` dengan form wajib menganggur ditahan padahal boleh.

**F5 — Cabang mati dan galat yang ditelan.** Route `admin.submissions.show` mensyaratkan `users.manage`,
sedangkan cabang reviewer di `adminShowSubmission` hanya jalan tanpa `users.manage`: `canCreateThread`
selalu false, `canReview` selalu true, `userRole` selalu `admin`. `try/catch (\Exception)` menelan galat
apa pun menjadi layar setengah kosong; `class_exists` atas model sendiri selalu true.

## Keputusan

- **K1** — Controller jadi cangkang: akses (BR-SM-07, penugasan aktif) → Query → `Inertia::render`.
- **K2** — Baca lewat `Queries/Submission/`: `ResearcherPeriodOverview`, `ResearcherPeriodPhases`,
  `OperatorPeriodDirectory`, `OperatorPeriodSubmissions`, `SubmissionStatusChoices`,
  `ResearcherSubmissionView`, `OperatorSubmissionView`, `SubmissionAnswers`, `SubmissionViewerRole`,
  `AvailableChildPhases`, `TargetOutputTypeChoices`, `RealisedOutputTypeChoices`,
  `PrintableDocumentChoices`; dan `Queries/Review/`: `ReviewThreads`, `AssignedReviewerCards`,
  `ReviewerEvaluationChecklist`, `VisibleReviewerScores`, `ReviewerSubmissionList`,
  `ReviewerSubmissionView`.
- **K3** — Periode pengajuan dibaca dari FK `submission_period_id` (ADR 0004). Pivot fase tetap dipakai
  untuk "fase apa saja milik periode ini", bukan untuk "pengajuan milik periode mana".
- **K4** — Statistik periode Operator satu kueri `GROUP BY (periode, status)`, dilipat lewat
  `SubmissionStatusBuckets`; menunggu review = `pending` + `under_review`.
- **K5** — `hasPendingEvaluations` = `!hasSettledEvaluation()`; `canCreateThread` mengikutinya.
- **K6** — Layar Operator mengirim nilai yang memang selalu dihasilkan cabang matinya; `try/catch`
  dicabut, galat sampai ke handler. Daftar reviewer tersedia memakai `ReviewersWithinTenure` (BR-REV-09).
- **K7** — Kartu reviewer di ketiga layar memakai id penugasan; layar reviewer hanya memakainya sebagai
  kunci daftar.

Test: `tests/Feature/Submission/SubmissionViewPagesTest.php`.
