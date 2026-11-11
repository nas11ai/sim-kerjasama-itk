# Fase Reuse — Catatan Bug & Keputusan Terbuka

Satu-satunya tempat untuk temuan rencana fase reuse (R1–R8) yang **belum** diperbaiki.
Peta irisan: `docs/plans/2026-11-10-fase-reuse-roadmap.md`.

**Aturan pakai:**

- Temuan dari review atau saat bekerja yang tidak langsung diperbaiki di gelombangnya masuk ke sini,
  lengkap dengan lokasi dan cara perbaikannya. Temuan tidak dilaporkan sepotong-sepotong di chat.
- Dikerjakan sekaligus di awal sesi berikutnya atau di slot **Cadangan** roadmap (sampai 24 Nov),
  dengan TDD dan gate penuh seperti task biasa.
- Butir yang selesai dipindah ke **Selesai** beserta commit-nya. Butir tidak dihapus diam-diam.

## Terbuka

| ID  | Asal            | Masalah                                                                                                                                                                                                                                                                                                   | Lokasi                                                                                                                                                                | Perbaikan                                                                                                                                                                                                                                                                                    |
| --- | --------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| B1  | Review akhir R1 | Kunci tanggal lebih ketat daripada hitungan celah. Batas Evaluasi (dan Batas Revisi) terkunci untuk setiap langkah yang siklusnya cocok, padahal hanya langkah `needs_review` yang membacanya. Akibatnya Operator tidak bisa menghapus baris Batas Evaluasi di periode yang tidak punya langkah direview. | `app/Models/SubmissionDate.php` `stepsNeedingIt()` (`orWhereIn(... Evaluation, Revision)`), dibandingkan `app/Queries/Period/PeriodScheduleGaps.php` `missingTypes()` | Batasi cabang Evaluation/Revision di `stepsNeedingIt()` ke `form_phase_details.needs_review = true`. NEEDS_REVISION hanya lahir dari review, jadi Revision ikut. Cek dulu BR-FE-26. Test: periode dengan langkah tanpa review → baris Batas Evaluasi boleh dihapus.                          |
| B2  | Review akhir R1 | `PeriodScheduleGaps::run()` (`handle()`) tidak punya pemanggil di produksi; yang memakainya hanya test.                                                                                                                                                                                                   | `app/Queries/Period/PeriodScheduleGaps.php:30`, `tests/Feature/Period/PeriodScheduleGapsTest.php:38,54`                                                               | Putuskan saat menulis rencana R8. Dipakai untuk cek kesiapan angkatan, atau dihapus bersama test-nya.                                                                                                                                                                                        |
| B3  | Review akhir R1 | N+1 di ekspor rekap monev. `MonevCycles::run($parent)` membaca kalender periode per induk tanpa eager-load. Ini bukan regresi R1 (jumlah kuerinya sama dengan sebelumnya).                                                                                                                                | `app/Support/Reporting/Exports/MonevRecapExport.php:51`                                                                                                               | `->with(['form:id,title', 'submissionPeriod.submissionDates'])`. Test: hitung kueri untuk 3 induk dan pastikan jumlahnya tetap.                                                                                                                                                              |
| B4  | Review akhir R1 | Basis data tidak punya CHECK untuk jenis/siklus. Selama ini hanya validasi aplikasi yang menjaganya.                                                                                                                                                                                                      | `form_phase_details.deadline_type`, `deadline_cycle`, `submission_dates.cycle`                                                                                        | Migrasi skema: `deadline_type IN ('submit','output')`, `deadline_cycle BETWEEN 1 AND 10` atau null, `output` → `deadline_cycle IS NULL`, `submission_dates.cycle BETWEEN 1 AND 10` atau null. Angka 10 = `DeadlineType::MAX_CYCLE`. Test: insert langsung yang melanggar → `QueryException`. |

## Butuh keputusan pemilik

Tidak ada.

## Selesai

| ID  | Masalah                                                                                                                                                                  | Commit     |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | ---------- |
| —   | Tanggal yang belum diatur memunculkan "Batas waktu fase ini sudah lewat". Sekarang pesannya "Jadwal {jenis langkah} belum diatur di periode ini. Hubungi Operator LPPM." | `ec10bf7b` |
| —   | Siklus periode diterima sampai 32767. Sekarang dibatasi `DeadlineType::MAX_CYCLE` (10), sama dengan langkah.                                                             | `ec10bf7b` |
| —   | Layar periode hanya menawarkan Monev I–V, jadi langkah bersiklus 6–10 tidak bisa diberi tanggal. Sekarang I–X.                                                           | `ec10bf7b` |
| —   | `submission_period_phases` tanpa unique index, jadi banner celah bisa dobel.                                                                                             | `ec10bf7b` |
| K-1 | Jenis langkah yang sudah dipakai pengajuan masih bisa diubah admin. Pemilik memutuskan dikunci (2026-11-11); dicatat di ADR 0019.                                        | `d35c4ce5` |
