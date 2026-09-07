Feature: Penunjukan Reviewer
  Penunjukan bukan pembuatan akun: alurnya memilih user yang sudah terdaftar.
  Reviewer eksternal karena itu harus diundang lebih dulu lewat [IAM-REG-08].
  reviewer_type yang disetel di sini yang dipakai BR-REV-11 di M2 untuk memutuskan
  keterlihatan skor, dan masa tugas yang disetel di sini yang ditegakkan BR-REV-09.

  Penunjukan idempoten per orang. Dua record reviewer untuk satu orang membuat
  penghitungan beban (BR-REV-04) dan keterlihatan skor membaca dari himpunan yang
  ambigu — dan itu tidak akan gagal secara mencolok, ia cuma akan salah.

  @BR-IAM-11 @M3 @OPERATOR-08-04 @happy
  Scenario: [IAM-REVIEWER-01] Penunjukan reviewer internal
    Given "Sari" sudah terdaftar dan berorganisasi "Teknik Informatika"
    When Operator menunjuk "Sari" sebagai reviewer internal
    Then "Sari" berstatus reviewer_internal
    And record reviewer untuk "Sari" terbentuk

  @BR-IAM-11 @M3 @OPERATOR-08-04 @happy
  Scenario: [IAM-REVIEWER-02] Penunjukan reviewer eksternal menyetel role pembatas
    Given "Rina" sudah terdaftar lewat jalur undangan
    When Operator menunjuk "Rina" sebagai reviewer eksternal
    Then "Rina" berstatus reviewer_external

  @BR-IAM-11 @M3 @OPERATOR-08-04 @denial
  Scenario: [IAM-REVIEWER-03] Menunjuk orang tanpa akun ditolak
    Given "Doni" belum punya akun
    When Operator menunjuk "Doni" sebagai reviewer eksternal
    Then penunjukan ditolak karena user belum terdaftar

  @BR-IAM-11 @M3 @OPERATOR-08-04 @boundary
  Scenario: [IAM-REVIEWER-04] Menunjuk ulang orang yang sudah reviewer tidak membuat record kedua
    Given "Sari" berstatus reviewer_internal
    When Operator menunjuk "Sari" sebagai reviewer internal
    Then record reviewer "Sari" tetap satu

  @BR-IAM-11 @M3 @OPERATOR-08-04 @boundary
  Scenario: [IAM-REVIEWER-05] Mengubah tipe reviewer mencabut role tipe lama
    Given "Sari" berstatus reviewer_internal
    When Operator menunjuk "Sari" sebagai reviewer eksternal
    Then "Sari" berstatus reviewer_external
    And "Sari" tidak punya permission "reviewers.view-scores-others"

  @BR-IAM-11 @M3 @OPERATOR-08-04 @happy
  Scenario: [IAM-REVIEWER-06] Penunjukan menetapkan masa tugas yang ditegakkan BR-REV-09
    Given "Sari" sudah terdaftar dan berorganisasi "Teknik Informatika"
    When Operator menunjuk "Sari" sebagai reviewer internal dengan masa tugas "2026-01-01" sampai "2026-12-31"
    Then "Sari" berstatus reviewer dengan masa tugas "2026-01-01" sampai "2026-12-31"
