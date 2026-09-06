Feature: Deaktivasi User
  Deaktivasi menyetel is_active = false dan tidak menghapus apa pun — data user
  tetap utuh supaya jejak pengajuan lama tetap terbaca. Penjagaan kepemilikan
  submission saat lead researcher dinonaktifkan ada di [SM-OWNER-03/04] (M1).

  @BR-IAM-05 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-DEACT-01] Deaktivasi menyisakan data user utuh
    Given "Sari" berstatus aktif
    When Operator menonaktifkan "Sari"
    Then "Sari" berstatus tidak aktif
    And data profil "Sari" tetap utuh

  @BR-IAM-05 @M3 @SHARED-01-01 @denial
  Scenario: [IAM-DEACT-02] User non-aktif tidak bisa login
    Given "Sari" berstatus tidak aktif
    When "Sari" login
    Then login ditolak karena akun tidak aktif
