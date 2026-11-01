Feature: Jendela Kerja Tiap Tahap
  Tiap FormPhaseDetail punya SubmissionDate sendiri. Tanpa itu reviewer tidak
  punya waktu kerja sama sekali — detail evaluasi ikut terkunci saat "Batas
  Submit" lewat — dan revisi mustahil karena detail researcher-nya sudah tertutup
  begitu NEEDS_REVISION tercapai (BR-FE-17).

  @BR-FE-17 @M2 @REVIEWER-03-03 @happy
  Scenario: [FE-WINDOW-01] Evaluasi tetap terbuka setelah batas submit lewat
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    When "Sari" membuka fase "Evaluasi"
    Then detail terbuka

  @BR-FE-17 @M2 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-02] Evaluasi ditolak setelah batas evaluasinya sendiri lewat
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-17 @M2 @RESEARCHER-02-05 @happy
  Scenario: [FE-WINDOW-03] Revisi punya jendelanya sendiri
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01"
    And detail Revisi berbatas "Batas Revisi" pada "2026-03-15"
    And submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil

  @BR-FE-17 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-WINDOW-04] Dua detail beraktor sama boleh berbagi satu tanggal
    Given detail Pengajuan dan detail Kelengkapan sama-sama beraktor researcher
    When admin menetapkan keduanya memakai SubmissionDate "Batas Submit"
    Then penyimpanan berhasil

  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @happy
  Scenario: [FE-WINDOW-05] Override yang berlaku membuka kembali evaluasi yang lewat batas
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then detail terbuka

  @BR-FE-17 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-06] Override yang kedaluwarsa tidak membuka kembali evaluasi
    Given waktu sekarang "2026-04-02 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403

  @BR-FE-10 @BR-FE-17 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @denial
  Scenario: [FE-WINDOW-07] Override fase lain tidak membuka kembali evaluasi
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail Evaluasi berbatas "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And form evaluasi "Sari" melekat pada detail "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Laporan Monev I" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka fase "Evaluasi"
    Then akses ditolak dengan 403
