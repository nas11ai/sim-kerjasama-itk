Feature: Penggantian Reviewer
  Record reviewer lama ditandai replaced, bukan dihapus. Menghapus evaluasi lama
  berarti menghapus jejak kenapa sebuah keputusan diambil — riwayatnya harus
  tetap terbaca (BR-REV-10).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" ditugaskan sebagai reviewer "P-001"

  @BR-REV-10 @M2 @OPERATOR-03-02 @happy
  Scenario: [REV-REASSIGN-01] Reviewer lama ditandai replaced, bukan dihapus
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001"
    Then penugasan "Sari" pada "P-001" berstatus replaced
    And penugasan "Sari" pada "P-001" tetap ada

  @BR-REV-10 @M2 @OPERATOR-03-02 @REVIEWER-03-02 @boundary
  Scenario: [REV-REASSIGN-02] Evaluasi reviewer lama tetap terbaca sebagai riwayat
    Given evaluasi "Sari" atas "P-001" berstatus submitted
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001"
    Then evaluasi "Sari" atas "P-001" tetap terbaca sebagai riwayat

  @BR-REV-10 @M2 @REVIEWER-03-01 @happy
  Scenario: [REV-REASSIGN-03] Reviewer pengganti mendapat penugasan bersih
    Given evaluasi "Sari" atas "P-001" berstatus submitted
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001"
    Then evaluation_status "Andi" pada "P-001" adalah pending
    And "Andi" tidak melihat isi evaluasi "Sari"

  @BR-REV-10 @BR-RPT-09 @M2 @OPERATOR-03-02 @boundary
  Scenario: [REV-REASSIGN-04] Penggantian tanpa catatan tetap berhasil
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001" tanpa catatan
    Then penugasan "Sari" pada "P-001" berstatus replaced

  @BR-REV-10 @BR-RPT-09 @M2 @OPERATOR-03-02 @happy
  Scenario: [REV-REASSIGN-05] Catatan penggantian tercatat di jejak audit
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001" dengan catatan "Tidak responsif lebih dari 7 hari"
    Then perubahan tercatat di jejak audit beserta catatan "Tidak responsif lebih dari 7 hari"
