# 0004 — `form_phase_detail_id` di `form_submissions`

**Status:** Diterima
**Tanggal:** 2026-08-16

## Konteks

`form_submissions` tidak punya kolom yang menghubungkannya ke `FormPhaseDetail`.
Relasinya **ditebak** saat runtime — `FormSubmission::getFormPhaseDetail()`
mencari detail pertama yang `form_access_control.form_id`-nya cocok, lalu
memanggil `->first()`.

Tebakan itu pecah di skenario Monev. Satu FormPhase berisi tujuh detail:

```
Detail 1  Pengajuan            Detail 5  Laporan Monev II
Detail 2  Evaluasi Pengajuan   Detail 6  Evaluasi Monev II
Detail 3  Laporan Monev I      Detail 7  Upload Luaran
Detail 4  Evaluasi Monev I
```

"Laporan Monev I" dan "Laporan Monev II" boleh memakai `Form` yang sama, sehingga
`->first()` mengembalikan detail yang salah. Semua yang bergantung padanya ikut
salah: deadline, `FormSubmissionOverride`, `is_archived`, dan gating
`can_proceed`.

BR-MON-03 ("satu child FormSubmission per FormPhaseDetail") juga tidak bisa
ditegakkan tanpa kolom ini.

## Keputusan

```sql
ALTER TABLE form_submissions
  ADD form_phase_detail_id BIGINT REFERENCES form_phase_details(id);
-- backfill memakai inferensi lama (data existing belum punya kasus ambigu)
-- lalu jadikan NOT NULL setelah backfill diverifikasi

CREATE UNIQUE INDEX one_child_submission_per_phase_detail
  ON form_submissions (parent_submission_id, form_phase_detail_id)
  WHERE parent_submission_id IS NOT NULL;
```

Baris dibuat pada **langkah 1 wizard** (saat Form & Periode dipilih), bukan
belakangan — lihat [0005](0005-scheme-selector-writes-to-column.md) untuk alasan
temporalnya.

## Konsekuensi

- `getFormPhaseDetail()` berubah dari query tebakan menjadi relasi `belongsTo`.
- Period submission jadi bisa diturunkan secara pasti:
  `form_phase_detail → submission_date → submission_period`. Jalur lewat
  `submission_period_phases` tidak bisa dipakai karena pivot itu tidak punya
  unique constraint — satu FormPhase boleh dimiliki banyak period.
- Skema jadi konsisten: `form_submission_overrides` **sudah** punya
  `form_phase_detail_id`.
- Butuh migration dua tahap (nullable → backfill → NOT NULL) agar aman.

## Alternatif yang ditolak

**Turunkan dari `form_id` saja.** Justru inilah yang rusak sekarang; dua phase
detail boleh berbagi Form yang sama.

**Simpan `form_phase_id` (bukan detail).** Tidak cukup — ambiguitasnya ada di
antar-detail dalam satu phase, persis level yang mau dibedakan.
