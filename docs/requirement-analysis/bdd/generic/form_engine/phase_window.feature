Feature: Jendela Kerja Tiap Tahap
  Tiap aktor sebuah langkah (FormPhaseDetail) membaca jenis tenggatnya sendiri dari
  kalender periode milik pengajuan: Batas Submit untuk pengisi, Batas Evaluasi untuk
  reviewer form evaluasi yang melekat pada langkah itu, dan Batas Revisi untuk
  pengisi saat NEEDS_REVISION — semuanya pada siklus langkah (BR-FE-26, ADR 0019).
  Tanpa itu reviewer tidak punya waktu kerja sama sekali — evaluasi ikut terkunci
  saat "Batas Submit" lewat — dan revisi mustahil (BR-FE-17).

  @BR-FE-17 @M2 @REVIEWER-03-03 @happy
  Scenario: [FE-WINDOW-01] Evaluasi tetap terbuka setelah batas submit lewat
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01"
    And detail Pengajuan punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    When "Sari" membuka fase "Evaluasi"
    Then detail terbuka

  @BR-FE-17 @M2 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-02] Evaluasi ditolak setelah batas evaluasinya sendiri lewat
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Pengajuan punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-17 @M2 @RESEARCHER-02-05 @happy
  Scenario: [FE-WINDOW-03] Revisi punya jendelanya sendiri
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01"
    And detail Pengajuan punya "Batas Revisi" pada "2026-03-15"
    And submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil

  @BR-FE-26 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-WINDOW-04] Dua langkah bersiklus sama di satu periode membaca satu tanggal yang sama
    Given tahap "Penelitian DIPA" punya dua langkah berjenis "Laporan Monev I"
    And periode "2026" punya "Batas Laporan Monev I" pada "2027-03-01"
    When tenggat kedua langkah dibaca untuk pengajuan periode "2026"
    Then keduanya membaca "Batas Laporan Monev I" yang sama

  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @happy
  Scenario: [FE-WINDOW-05] Override yang berlaku membuka kembali evaluasi yang lewat batas
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Pengajuan punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then detail terbuka

  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-06] Override yang kedaluwarsa tidak membuka kembali evaluasi
    Given waktu sekarang "2026-04-02 09:00:00"
    And detail Pengajuan punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-10 @BR-FE-17 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-07] Override fase lain tidak membuka kembali evaluasi
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Pengajuan punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Laporan Monev I" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-25 @M2 @OPERATOR-09-01 @denial
  Scenario: [FE-WINDOW-12] Satu periode tidak boleh punya dua tanggal berjenis dan bersiklus sama
    Given periode "2026 Gelombang 1" punya "Batas Evaluasi" pada "2026-03-20"
    When Operator menambah "Batas Evaluasi" kedua pada "2026-03-25" ke periode itu
    Then penyimpanan ditolak
    And "Batas Evaluasi Monev I" pada periode yang sama tetap boleh ditambahkan

  @BR-FE-26 @M2 @ADMIN-08-08 @denial
  Scenario: [FE-WINDOW-08] Langkah yang direview di periode tanpa Batas Evaluasi siklusnya muncul sebagai celah jadwal fase
    Given fase "Penelitian" punya langkah Pengajuan ber-needs_review
    And fase itu ditautkan ke periode "Periode A" yang hanya punya "Batas Submit"
    When admin membuka pustaka fase
    Then kartu fase "Penelitian" menyebut "Periode Periode A belum punya 1 tanggal yang dibutuhkan tahap ini."
    And celah jadwal periode itu memuat "Batas Evaluasi" langkah 1

  @BR-FE-26 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-WINDOW-09] Langkah Upload Luaran tidak bisa bersiklus dan tidak bisa direview
    When admin menyimpan langkah berjenis Upload Luaran dengan siklus 1
    Then penyimpanan ditolak dengan pesan "Upload Luaran tidak bersiklus."
    When admin menyimpan langkah berjenis Upload Luaran ber-needs_review
    Then penyimpanan ditolak dengan pesan "Detail Upload Luaran tidak direview."

  @BR-FE-26 @BR-FE-17 @M2 @RESEARCHER-02-05 @happy
  Scenario: [FE-WINDOW-10] Revisi digerbangi Batas Revisi, bukan Batas Submit
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01" dan "Batas Revisi" pada "2026-03-15"
    And submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil
    And setelah "2026-03-15" penyuntingan ditolak dengan 403

  @BR-FE-26 @BR-MON-05 @M2 @RESEARCHER-05-01 @happy
  Scenario: [FE-WINDOW-11] Siklus Monev memakai tanggal siklusnya sendiri di kalender periode
    Given tahap "Penelitian DIPA" punya langkah "Laporan Monev I" dan "Laporan Monev II"
    And periode "2025" punya "Batas Laporan Monev I" pada "2026-03-01" dan "Batas Laporan Monev II" pada "2026-09-01"
    When siklus monev pengajuan APPROVED periode "2025" dibaca
    Then Monev I bertenggat "2026-03-01"
    And Monev II bertenggat "2026-09-01"

  @BR-FE-17 @BR-FE-01 @M2 @RESEARCHER-02-02 @denial
  Scenario: [FE-WINDOW-13] Pengajuan baru ditolak di periode yang tidak menautkan fasenya
    Given tahap "Pengajuan" terhubung ke periode "2026 Gelombang 1" saja
    And periode "2026 Gelombang 1" dan "2026 Gelombang 2" sama-sama punya "Batas Submit" yang belum lewat
    When "Budi" memulai pengajuan tahap itu di "2026 Gelombang 2"
    Then penyimpanan ditolak dengan 422 "Tahap ini tidak dibuka di periode ini."
    And tahap itu tidak ditawarkan di "2026 Gelombang 2"
    And di "2026 Gelombang 1" tahap itu ditawarkan

  @BR-FE-26 @M2 @RESEARCHER-02-05 @happy
  Scenario: [FE-WINDOW-14] Satu fase di dua periode: tiap pengajuan digerbangi tanggal periodenya
    Given waktu sekarang "2026-03-05 09:00:00"
    And tahap "Penelitian DIPA" ditautkan ke periode "2025" dan "2026"
    And periode "2025" punya "Batas Submit" pada "2026-03-01"
    And periode "2026" punya "Batas Submit" pada "2026-04-01"
    When pengisi pengajuan periode "2025" dan pengajuan periode "2026" membuka langkah Pengajuan
    Then langkah tertutup bagi pengajuan periode "2025"
    And langkah terbuka bagi pengajuan periode "2026"

  @BR-FE-26 @M2 @RESEARCHER-02-05 @denial
  Scenario: [FE-WINDOW-16] Tanggal kerja yang tidak ada di kalender periode menutup langkah itu
    Given tahap "Penelitian DIPA" ditautkan ke periode "2026" yang belum punya "Batas Submit"
    When pengisi pengajuan periode "2026" membuka langkah Pengajuan
    Then langkah tertutup
    But reviewer langkah itu tetap terbuka bila periode belum punya "Batas Evaluasi"

  @BR-SCH-07 @M2 @OPERATOR-09-01 @boundary
  Scenario: [FE-WINDOW-17] Force close periode A tidak menutup pengajuan periode B pada fase yang sama
    Given tahap "Penelitian DIPA" ditautkan ke periode "A" dan "B" yang Batas Submit-nya belum lewat
    When Operator menutup paksa periode "A"
    Then langkah Pengajuan tertutup bagi pengajuan periode "A"
    And langkah Pengajuan tetap terbuka bagi pengajuan periode "B"

  @BR-FE-26 @BR-MON-05 @M2 @OPERATOR-09-01 @boundary
  Scenario: [FE-WINDOW-15] Menyiapkan periode baru tidak mengubah tanggal siklus monev angkatan lama
    Given pengajuan "P-2025" APPROVED di periode "2025" pada tahap "Penelitian DIPA"
    And periode "2025" punya "Batas Laporan Monev II" pada "2026-06-01"
    When Operator menautkan tahap "Penelitian DIPA" ke periode "2026" dengan "Batas Laporan Monev II" pada "2027-06-01"
    Then Monev II "P-2025" tetap bertenggat "2026-06-01"
