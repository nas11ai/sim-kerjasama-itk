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

| ID  | Asal            | Masalah                                                                                                 | Lokasi                                                                                                  | Perbaikan                                                                                    |
| --- | --------------- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| B2  | Review akhir R1 | `PeriodScheduleGaps::run()` (`handle()`) tidak punya pemanggil di produksi; yang memakainya hanya test. | `app/Queries/Period/PeriodScheduleGaps.php:30`, `tests/Feature/Period/PeriodScheduleGapsTest.php:38,54` | Diputuskan di rencana G4 (R8): dipakai cek kesiapan angkatan, atau dihapus bersama test-nya. |

## Butuh keputusan pemilik

Tidak ada.

## Selesai

| ID  | Masalah                                                                                                                                                                               | Commit     |
| --- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------- |
| —   | Tanggal yang belum diatur memunculkan "Batas waktu fase ini sudah lewat". Sekarang pesannya "Jadwal {jenis langkah} belum diatur di periode ini. Hubungi Operator LPPM."              | `ec10bf7b` |
| —   | Siklus periode diterima sampai 32767. Sekarang dibatasi `DeadlineType::MAX_CYCLE` (10), sama dengan langkah.                                                                          | `ec10bf7b` |
| —   | Layar periode hanya menawarkan Monev I–V, jadi langkah bersiklus 6–10 tidak bisa diberi tanggal. Sekarang I–X.                                                                        | `ec10bf7b` |
| —   | `submission_period_phases` tanpa unique index, jadi banner celah bisa dobel.                                                                                                          | `ec10bf7b` |
| K-1 | Jenis langkah yang sudah dipakai pengajuan masih bisa diubah admin. Pemilik memutuskan dikunci (2026-11-11); dicatat di ADR 0019.                                                     | `d35c4ce5` |
| B1  | Batas Evaluasi/Revisi terkunci untuk langkah tanpa review. Sekarang `stepsNeedingIt()` hanya mengunci keduanya bagi langkah `needs_review`, sama dengan `PeriodScheduleGaps`.         | `c993c46e` |
| B3  | N+1 di ekspor Rekap Monev. Induk sekarang memuat `formPhaseDetail` dan `submissionPeriod.submissionDates` sekali; test menghitung kueri untuk 3 induk.                                | `c993c46e` |
| B4  | Tanpa CHECK jenis/siklus di basis data. Sekarang ada CHECK jenis kalender (4 jenis), jenis langkah (`submit`/`output`), siklus 1–10, dan `output` tanpa siklus.                       | `c993c46e` |
| —   | Review akhir G1: "Buat Tahap Baru" dari pustaka bertapis Form tidak memilih Form itu. Sekarang `create?form=` mendahulukan aturan akses Form itu.                                     | `4da0c117` |
| —   | Review akhir G1: dua tahap ber-Form pengajuan sama di satu periode — tahap yang ditawarkan bergantung urutan baris. Sekarang selalu tahap tertua (lihat K-2 untuk penjaganya).        | `4da0c117` |
| B5  | Tapis `?form=` hilang sesudah sunting/hapus/Kembali. Sekarang editor membawa `returnForm`; simpan, Kembali, Batalkan kembali ke pustaka bertapis; hapus kembali ke halaman asal.      | `9b560559` |
| K-2 | Dua tahap ber-Form pengajuan sama di satu periode. Pemilik memilih (a) tolak (2026-11-12): BR-FE-29, berlaku di kartu tahap dan layar periode; periode yang bentrok tidak ditawarkan. | commit `feat(period): satu tahap per Form pengajuan di satu periode` |
