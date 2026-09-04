Feature: Anggota Penelitian
  Satu orang tidak boleh menilai penelitian yang ia ikuti. Larangan ini berlaku dua
  arah; sisi reviewer dijaga [REV-ASSIGN-07] di M2.

  Background:
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And skema "DIPA Internal" membatasi max_members 4

  @BR-SM-08 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-01] Reviewer submission ini tidak bisa jadi anggotanya
    Given "Rina" ditugaskan sebagai reviewer "P-001"
    When "Budi" menambahkan "Rina" sebagai anggota "P-001"
    Then penambahan ditolak karena konflik kepentingan

  @BR-SM-08 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-MEMBER-02] Anggota biasa bisa ditambahkan
    When "Budi" menambahkan "Sari" sebagai co_investigator "P-001"
    Then "Sari" terdaftar sebagai co_investigator di "P-001"

  @BR-SM-08 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-MEMBER-03] Jumlah anggota dibatasi skema
    Given "P-001" sudah punya 4 anggota
    When "Budi" menambahkan satu anggota lagi ke "P-001"
    Then penambahan ditolak karena melebihi batas skema
