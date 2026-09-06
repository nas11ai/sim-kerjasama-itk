Feature: Penunjukan Reviewer
  Penunjukan bukan pembuatan akun: alurnya memilih user yang sudah terdaftar.
  Reviewer eksternal karena itu harus diundang lebih dulu lewat [IAM-REG-08].
  reviewer_type yang disetel di sini yang dipakai BR-REV-11 di M2 untuk memutuskan
  keterlihatan skor.

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
