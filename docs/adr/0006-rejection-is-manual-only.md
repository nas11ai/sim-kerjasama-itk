# 0006 — Rejection hanya manual, auto-reject dihapus

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

`FormSubmission::updateStatusBasedOnReviews()` men-set status `Rejected` secara
otomatis begitu ada `ReviewSummary` berstatus `closed`:

```php
if ($this->hasPendingEvaluations())      $target = UnderReview::class;
elseif ($this->hasRejectedReviews())     $target = Rejected::class;   // ← auto
elseif ($this->hasRevisionsRequested())  $target = NeedsRevision::class;
elseif ($this->allReviewersApproved())   $target = Approved::class;
```

Ini melanggar BR-REV-07: *"Rejection harus dikonfirmasi manual oleh Operator —
tidak bisa otomatis."* Alasan bisnisnya jelas di dokumen Review: penolakan
berimplikasi besar bagi researcher dan butuh pertimbangan manusia.

Dua masalah lain di method yang sama:

- Status `closed` **tidak ada** di ubiquitous language. `ReviewSummary` hanya
  mengenal `open` dan `resolved`.
- `allReviewersApproved()` menghitung jumlah summary ber-status `resolved` dan
  membandingkannya dengan jumlah reviewer. BR-REV-05 mensyaratkan hal berbeda:
  semua `evaluation_status = completed` **dan** tidak ada summary `open`.
  Reviewer yang selesai mengevaluasi tanpa membuat summary tidak terhitung.

## Keputusan

`updateStatusBasedOnReviews()` diganti Action `EvaluateSubmissionOutcome`, yang
dipanggil setiap kali evaluasi atau summary berubah:

```
ada SubmissionReviewer aktif dengan evaluation_status = pending
    → tetap UNDER_REVIEW
ada ReviewSummary status = open
    → NEEDS_REVISION                                      (BR-REV-06)
selain itu
    → APPROVED                                            (BR-REV-05)
```

Penolakan dipindah ke Action terpisah `RejectSubmission`, hanya bisa dipanggil
Operator, wajib menyertakan alasan, dan tidak pernah dipicu otomatis.

Status `closed` dihapus dari kode. Perlu dicek saat implementasi apakah ada baris
`review_summaries.status = 'closed'` di database — kalau ada, butuh migrasi data
ke `open`.

## Konsekuensi

- Transisi ke `REJECTED` hanya punya satu jalur masuk, sehingga bisa diaudit dan
  dinotifikasi dengan benar.
- Perhitungan approve/revisi jadi cocok dengan BR-REV-05/06, bukan aproksimasi.
- `SubmissionStatus::config()` tidak berubah — transisi `UnderReview → Rejected`
  tetap sah, yang berubah adalah siapa yang boleh memicunya.
- Butuh test yang menegaskan tidak ada jalur otomatis menuju `REJECTED`.

## Alternatif yang ditolak

**Pertahankan auto-reject dengan gerbang konfirmasi di UI.** Menaruh aturan bisnis
di frontend. Backend tetap wajib jadi penjaga.

**Ubah BR-REV-07 supaya cocok dengan kode.** Aturannya punya alasan bisnis
eksplisit di dokumen; kode yang salah, bukan aturannya.
