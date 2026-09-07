Feature: Deaktivasi User
  Deaktivasi menyetel `is_active = false` dan tidak menghapus apa pun — data user
  tetap utuh supaya jejak pengajuan lama tetap terbaca. Yang berubah adalah
  kemampuannya bertindak: akun nonaktif tidak bisa login dan tidak menerima
  penugasan baru, sementara penugasan yang sudah berjalan tidak ikut hilang.

  Penjagaan kepemilikan submission saat lead researcher dinonaktifkan ada di
  [SM-OWNER-03/04] (M1); penggantian reviewer yang terlanjur nonaktif ada di
  [REV-REASSIGN-01] (M2).

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

  @BR-IAM-05 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-DEACT-03] Pengajuan milik user nonaktif tetap terbaca
    Given submission "P-001" milik "Sari" berstatus APPROVED
    And "Sari" berstatus tidak aktif
    When Operator membuka detail "P-001"
    Then detail terbuka

  @BR-IAM-05 @M3 @OPERATOR-08-04 @denial
  Scenario: [IAM-DEACT-04] Reviewer nonaktif tidak menerima penugasan baru
    Given "Sari" berstatus reviewer_internal
    And "Sari" berstatus tidak aktif
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan ditolak karena reviewer tidak aktif
