Feature: Keterlihatan Skor Review
  Dua aturan berbeda yang sering tertukar: siapa yang boleh melihat skor antar
  reviewer (BR-REV-11), dan kapan researcher boleh melihatnya (BR-REV-12).
  Reviewer eksternal tidak pernah melihat skor rekannya; researcher baru
  melihatnya setelah keputusan final.

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" dan "Andi" ditugaskan sebagai reviewer "P-001"

  @BR-REV-11 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-VIS-01] Reviewer internal melihat skor rekannya setelah semua selesai
    Given "Sari" berstatus reviewer_internal
    And evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    When "Sari" membuka hasil review "P-001"
    Then "Sari" melihat skor "Andi"

  @BR-REV-11 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-VIS-02] Reviewer eksternal tidak pernah melihat skor rekannya
    Given "Sari" berstatus reviewer_external
    And evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    When "Sari" membuka hasil review "P-001"
    Then "Sari" tidak melihat skor "Andi"

  @BR-REV-11 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-VIS-03] Skor rekan masih tertutup selama ada yang belum selesai
    Given "Sari" berstatus reviewer_internal
    And evaluasi "Sari" atas "P-001" berstatus completed
    And evaluation_status "Andi" pada "P-001" adalah pending
    When "Sari" membuka hasil review "P-001"
    Then "Sari" tidak melihat skor "Andi"

  @BR-REV-12 @M2 @RESEARCHER-02-03 @denial
  Scenario: [REV-VIS-04] Researcher tidak melihat skor selama UNDER_REVIEW
    When "Budi" membuka hasil review "P-001"
    Then "Budi" tidak melihat skor reviewer

  @BR-REV-12 @M2 @RESEARCHER-02-03 @happy
  Scenario: [REV-VIS-05] Researcher melihat skor setelah APPROVED
    Given submission "P-001" berstatus APPROVED
    When "Budi" membuka hasil review "P-001"
    Then "Budi" melihat skor reviewer

  @BR-REV-12 @M2 @RESEARCHER-02-03 @RESEARCHER-02-12 @happy
  Scenario: [REV-VIS-06] Researcher melihat skor setelah REJECTED
    Given submission "P-001" berstatus REJECTED
    When "Budi" membuka hasil review "P-001"
    Then "Budi" melihat skor reviewer
