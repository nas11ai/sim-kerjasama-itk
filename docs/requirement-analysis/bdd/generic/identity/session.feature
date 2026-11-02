Feature: Sesi Login dan Kehadiran
  Sesi login adalah keadaan akun yang dapat dibaca dan diakhiri (BR-IAM-21). "Sedang aktif"
  diturunkan dari aktivitas sesi dalam 5 menit terakhir, bukan dari pelacakan halaman.
  Mengakhiri sesi berbeda dari deaktivasi: akunnya tetap aktif dan bisa login lagi.
  Deaktivasi selalu mengakhiri seluruh sesi ([IAM-DEACT-09]).

  @BR-IAM-21 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-SESSION-01] Mengakhiri seluruh sesi tanpa menonaktifkan akun
    Given "Sari" sedang login di dua perangkat
    When Admin mengakhiri seluruh sesi "Sari"
    Then kedua sesi "Sari" berakhir dan cookie "ingat saya" lamanya tidak berlaku
    And akun "Sari" tetap aktif dan ia bisa login lagi
    And tindakan itu tercatat di jejak audit

  @BR-IAM-21 @M3 @OPERATOR-08-01 @boundary
  Scenario: [IAM-SESSION-02] "Sedang aktif" hanya dalam jendela 5 menit
    Given sesi "Sari" terakhir beraktivitas 4 menit lalu
    And sesi "Budi" terakhir beraktivitas 6 menit lalu
    When Operator membuka daftar user
    Then "Sari" tampil sedang aktif
    And "Budi" tidak tampil sedang aktif tetapi waktu terakhir aktifnya tetap terbaca

  @BR-IAM-21 @M3 @OPERATOR-08-03 @denial
  Scenario: [IAM-SESSION-03] Peneliti tidak melihat kehadiran dan Operator tidak mengakhiri sesi
    Given "Rudi" adalah peneliti tanpa users.verify maupun users.manage
    When "Rudi" membuka daftar atau detail user
    Then akses ditolak
    And Operator pemegang users.verify saja tidak bisa mengakhiri sesi user lain
