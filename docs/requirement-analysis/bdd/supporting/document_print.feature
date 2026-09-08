Feature: Cetak Dokumen PDF
  Cetak mengikuti ambang yang sama dengan ekspor tabular: yang sederhana
  dihasilkan langsung, yang kompleks lewat background job. Yang membedakannya
  dari ekspor bukan mekanismenya melainkan isinya — dokumen cetak memuat data
  satu pengajuan, jadi hak membacanya mengikuti hak membaca pengajuan itu.

  Cetak tidak pernah menjadi pintu belakang. Skor yang masih ditahan BR-REV-12
  tetap ditahan di PDF-nya; kalau tidak, satu tombol unduh membatalkan seluruh
  aturan keterlihatan.

  Ambangnya sama persis dengan BR-RPT-02 — lebih dari 100 baris — karena
  BR-RPT-11 memang menyatakan demikian. Angka kedua yang berbeda akan membuat
  "ambang yang sama" berhenti benar pada pemakaian pertamanya.

  Background:
    Given submission "P-001" milik "Budi" berstatus APPROVED

  @BR-RPT-11 @M5 @OPERATOR-02-02 @happy
  Scenario: [RPT-PRINT-01] Dokumen sederhana dihasilkan langsung
    When Operator mencetak "Surat Keterangan Diterima" untuk "P-001"
    Then berkas PDF dihasilkan langsung

  @BR-RPT-11 @M5 @OPERATOR-07-02 @boundary
  Scenario: [RPT-PRINT-02] Dokumen kompleks masuk background job
    Given "Budi" punya 101 baris pada rekap yang diminta
    When Operator "Dewi" mencetak "Rekap Submission per Peneliti" untuk "Budi"
    Then cetak dijadwalkan sebagai background job
    And "Dewi" menerima notifikasi in-app saat cetak selesai

  @BR-RPT-11 @M5 @RESEARCHER-02-03 @denial
  Scenario: [RPT-PRINT-03] Cetak tidak membuka skor yang masih ditahan
    Given submission "P-003" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" sudah submit evaluasinya atas "P-003"
    When "Budi" mencetak "Lembar Penilaian" untuk "P-003"
    Then cetak ditolak karena hasil review belum terbuka

  @BR-RPT-11 @M5 @RESEARCHER-02-03 @happy
  Scenario: [RPT-PRINT-04] Lembar Penilaian terbuka setelah keputusan akhir
    Given "Sari" sudah submit evaluasinya atas "P-001"
    When "Budi" mencetak "Lembar Penilaian" untuk "P-001"
    Then berkas PDF dihasilkan langsung

  @BR-RPT-11 @M5 @RESEARCHER-02-01 @denial
  Scenario: [RPT-PRINT-05] Cetak pengajuan orang lain ditolak
    Given submission "P-007" milik "Sari" berstatus APPROVED
    When "Budi" mencetak "Surat Keterangan Diterima" untuk "P-007"
    Then cetak ditolak dengan 403

  @BR-RPT-11 @BR-MON-09 @M5 @RESEARCHER-05-03 @happy
  Scenario: [RPT-PRINT-06] Laporan Monev bisa dicetak begitu evaluasinya masuk
    Given laporan "Laporan Monev I" pada "P-001" sudah disubmit
    And "Sari" sudah submit evaluasi siklus "Laporan Monev I" pada "P-001"
    When "Budi" mencetak "Laporan Monev" untuk "P-001"
    Then berkas PDF dihasilkan langsung
