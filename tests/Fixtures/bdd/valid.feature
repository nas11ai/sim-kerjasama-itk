Feature: Fixture Parser
  Dipakai unit test FeatureCatalog. Bukan spesifikasi sungguhan.

  Background:
    Given kondisi awal apa pun

  @BR-FE-11 @M0 @RESEARCHER-02-02 @happy
  Scenario: [FX-SAMPLE-01] Skenario biasa dengan tiga jenis tag
    When sesuatu terjadi
    Then hasilnya terlihat

  @BR-FE-11 @BR-FE-15 @M0 @denial
  Scenario Outline: [FX-SAMPLE-02] Outline dengan dua business rule
    Given submission berstatus <status>
    When sesuatu terjadi
    Then ditolak

    Examples:
      | status    |
      | APPROVED  |
      | WITHDRAWN |
