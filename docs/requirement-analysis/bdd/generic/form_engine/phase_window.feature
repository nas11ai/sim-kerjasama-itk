Feature: Jendela Kerja Tiap Tahap
  Tiap aktor sebuah FormPhaseDetail punya SubmissionDate sendiri: Batas Submit
  untuk pengisi, Batas Evaluasi untuk reviewer form evaluasi yang melekat pada
  detail itu, dan Batas Revisi untuk pengisi saat NEEDS_REVISION (BR-FE-26).
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

  @BR-FE-17 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-WINDOW-04] Dua detail beraktor sama boleh berbagi satu tanggal
    Given detail Pengajuan dan detail Kelengkapan sama-sama beraktor researcher
    When admin menetapkan keduanya memakai SubmissionDate "Batas Submit"
    Then penyimpanan berhasil

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

  @BR-FE-26 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-WINDOW-08] Detail yang direview tanpa Batas Evaluasi ditolak
    Given detail Pengajuan ber-needs_review berbatas "Batas Submit" pada "2026-03-01"
    When admin menyimpan detail itu tanpa "Batas Evaluasi"
    Then penyimpanan ditolak dengan 422

  @BR-FE-26 @BR-FE-17 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-WINDOW-09] Batas Evaluasi tidak boleh memakai tanggal berjenis submit
    Given detail Pengajuan ber-needs_review berbatas "Batas Submit" pada "2026-03-01"
    When admin menetapkan "Batas Submit" yang sama sebagai Batas Evaluasi detail itu
    Then penyimpanan ditolak dengan 422

  @BR-FE-26 @BR-FE-17 @M2 @RESEARCHER-02-05 @happy
  Scenario: [FE-WINDOW-10] Revisi digerbangi Batas Revisi, bukan Batas Submit
    Given waktu sekarang "2026-03-05 09:00:00"
    And detail Pengajuan berbatas "Batas Submit" pada "2026-03-01" dan "Batas Revisi" pada "2026-03-15"
    And submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil
    And setelah "2026-03-15" penyuntingan ditolak dengan 403

  @BR-FE-26 @BR-MON-05 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-WINDOW-11] Siklus Monev memakai tenggat siklusnya sendiri
    Given detail Laporan Monev I berbatas "Batas Laporan Monev I"
    When admin menetapkan "Batas Evaluasi" pengajuan utama sebagai Batas Evaluasi detail itu
    Then penyimpanan ditolak dengan 422
    And "Batas Evaluasi Monev I" diterima

  @BR-FE-17 @BR-FE-01 @M2 @RESEARCHER-02-02 @denial
  Scenario: [FE-WINDOW-13] Detail yang tenggatnya milik periode lain tidak membuka pengajuan di periode itu
    Given tahap "Pengajuan" terhubung ke periode "2026 Gelombang 1" dan "2026 Gelombang 2"
    And detail "Pengajuan" berbatas "Batas Submit" milik "2026 Gelombang 1"
    When "Budi" memulai pengajuan detail itu di "2026 Gelombang 2"
    Then penyimpanan ditolak dengan 422
    And detail itu tidak ditawarkan di "2026 Gelombang 2"
    And di "2026 Gelombang 1" pengajuan berhasil dimulai
