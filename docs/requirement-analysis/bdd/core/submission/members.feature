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

  @BR-SM-18 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-MEMBER-04] Ketua pengaju mengeluarkan anggota
    Given pengajuan "P-001" berstatus DRAFT dengan anggota "Sari"
    When submitted_by mengeluarkan "Sari" dari "P-001"
    Then "Sari" tidak lagi terdaftar sebagai anggota "P-001"

  @BR-SM-18 @BR-SCH-03 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-MEMBER-05] Mengeluarkan anggota membuka kembali kuota skema
    Given skema "Penelitian Dasar" membatasi anggota maksimal 3
    And pengajuan "P-001" sudah punya 3 anggota
    When submitted_by mengeluarkan satu anggota dari "P-001"
    And submitted_by menambahkan "Tono" sebagai anggota
    Then "Tono" tersimpan sebagai anggota "P-001"

  @BR-SM-18 @BR-SM-07 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-06] Ketua pengaju tidak bisa mengeluarkan dirinya sendiri
    Given pengajuan "P-001" diajukan "Budi"
    When "Budi" mengeluarkan dirinya sendiri dari "P-001"
    Then pengeluaran ditolak karena submitted_by tidak bisa keluar dari pengajuannya

  @BR-SM-18 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-07] Anggota biasa tidak bisa mengeluarkan anggota lain
    Given pengajuan "P-001" punya anggota "Sari" dan "Tono"
    When "Sari" mengeluarkan "Tono" dari "P-001"
    Then pengeluaran ditolak karena bukan wewenangnya

  @BR-SM-18 @M1 @RESEARCHER-02-08 @denial
  Scenario Outline: [SM-MEMBER-08] Anggota tidak bisa dikeluarkan pada status terminal
    Given pengajuan "P-001" berstatus <status> dengan anggota "Sari"
    When submitted_by mengeluarkan "Sari" dari "P-001"
    Then pengeluaran ditolak karena pengajuan sudah terminal

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |
