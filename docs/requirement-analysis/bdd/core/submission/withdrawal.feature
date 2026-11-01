Feature: Penarikan Pengajuan
  Penarikan hanya lewat Operator, dan tidak dapat dibatalkan — state machine
  sengaja dijaga asiklik (BR-SM-11).

  Background:
    Given submission "P-001" milik "Budi" berstatus APPROVED

  @BR-SM-11 @M1 @OPERATOR-02-06 @happy
  Scenario: [SM-WITHDRAW-01] Operator menarik pengajuan yang sudah disetujui
    When Operator menarik "P-001"
    Then status "P-001" menjadi WITHDRAWN

  @BR-SM-11 @M1 @RESEARCHER-02-03 @denial
  Scenario: [SM-WITHDRAW-02] Researcher tidak bisa menarik sendiri
    When "Budi" menarik "P-001"
    Then penarikan ditolak karena bukan self-service

  @BR-SM-11 @M1 @OPERATOR-02-06 @boundary
  Scenario: [SM-WITHDRAW-03] Menarik pengajuan yang belum disetujui ditolak
    Given submission "P-002" milik "Budi" berstatus UNDER_REVIEW
    When Operator menarik "P-002"
    Then penarikan ditolak karena pengajuan belum disetujui

  @BR-SM-11 @M1 @OPERATOR-02-02 @boundary
  Scenario: [SM-WITHDRAW-04] Data historis tetap utuh setelah penarikan
    When Operator menarik "P-001"
    Then seluruh anggaran, anggota, dan berkas "P-001" tetap terbaca

  @BR-SM-11 @M1 @OPERATOR-02-06 @denial
  Scenario: [SM-WITHDRAW-05] Penarikan tidak dapat dibatalkan
    Given submission "P-001" berstatus WITHDRAWN
    When Operator mencoba mengembalikan "P-001" ke APPROVED
    Then perpindahan ditolak

  @BR-SM-11 @BR-RPT-09 @M1 @OPERATOR-02-06 @denial
  Scenario: [SM-WITHDRAW-06] Penarikan tanpa alasan ditolak
    When Operator menarik "P-001" dengan alasan kosong
    Then penarikan ditolak dengan error validasi pada field alasan
    And status "P-001" tetap APPROVED

  @BR-SM-11 @BR-RPT-09 @M1 @OPERATOR-02-06 @happy
  Scenario: [SM-WITHDRAW-07] Alasan penarikan tercatat di jejak audit
    When Operator menarik "P-001" dengan alasan "Duplikasi pendanaan"
    Then status "P-001" menjadi WITHDRAWN
    And perubahan tercatat di jejak audit beserta alasan "Duplikasi pendanaan"
