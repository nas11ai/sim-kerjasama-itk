Feature: Transfer Kepemilikan Pengajuan
  Lead researcher yang dinonaktifkan tidak boleh meninggalkan pengajuan tanpa
  penanggung jawab. Aturannya kanonik di BR-IAM-10, dicerminkan BR-SM-09 — satu
  Action, dipanggil dari layar deaktivasi maupun layar transfer.

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-02-04 @happy
  Scenario: [SM-OWNER-01] Operator memindahkan kepemilikan ke co-investigator aktif
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari"
    Then submitted_by "P-001" menjadi "Sari"
    And "Budi" tetap terdaftar sebagai anggota "P-001"

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-02-04 @denial
  Scenario: [SM-OWNER-02] Transfer ke member biasa ditolak
    Given "Andi" terdaftar sebagai member di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Andi"
    Then pemindahan ditolak karena penerima bukan co_investigator

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-08-03 @denial
  Scenario: [SM-OWNER-03] Deaktivasi lead ditolak sebelum kepemilikan dipindahkan
    When Operator menonaktifkan "Budi"
    Then deaktivasi ditolak karena masih ada pengajuan aktif

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-08-03 @boundary
  Scenario: [SM-OWNER-04] Tidak ada co-investigator aktif, deaktivasi tetap diblokir
    Given "P-001" tidak punya co_investigator aktif
    When Operator menonaktifkan "Budi"
    Then deaktivasi ditolak karena masih ada pengajuan aktif
    And Operator melihat alasan bahwa tidak ada co_investigator aktif

  @BR-SM-09 @BR-RPT-09 @M1 @OPERATOR-02-04 @denial
  Scenario: [SM-OWNER-05] Pemindahan kepemilikan tanpa alasan ditolak
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari" dengan alasan kosong
    Then pemindahan ditolak dengan error validasi pada field alasan

  @BR-SM-09 @BR-RPT-09 @M1 @OPERATOR-02-04 @happy
  Scenario: [SM-OWNER-06] Alasan pemindahan kepemilikan tercatat di jejak audit
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari" dengan alasan "Ketua pindah institusi"
    Then perubahan tercatat di jejak audit beserta alasan "Ketua pindah institusi"
