Feature: Akses Tingkat Submission
  Lapis kedua di luar FormAccessControl. Form-nya boleh terbuka untuk seluruh
  fakultas, tapi isi submission hanya untuk pemilik dan anggotanya (BR-SM-07).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW

  @BR-SM-07 @M1 @RESEARCHER-02-03 @happy
  Scenario: [SM-ACCESS-01] Pemilik melihat pengajuannya
    When "Budi" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @RESEARCHER-02-03 @happy
  Scenario: [SM-ACCESS-02] Anggota penelitian melihat pengajuan yang ia ikuti
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @RESEARCHER-02-01 @denial
  Scenario: [SM-ACCESS-03] Researcher lain di organisasi yang sama ditolak
    Given "Andi" berorganisasi sama dengan "Budi" dan bukan anggota "P-001"
    When "Andi" membuka detail "P-001"
    Then akses ditolak dengan 403

  @BR-SM-07 @M1 @OPERATOR-02-02 @happy
  Scenario: [SM-ACCESS-04] Operator melihat semua lewat submissions.view-all
    Given "Siti" punya permission "submissions.view-all"
    When "Siti" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @REVIEWER-03-01 @happy
  Scenario: [SM-ACCESS-05] Reviewer yang ditugaskan melihat submission itu
    Given "Rina" ditugaskan sebagai reviewer "P-001"
    When "Rina" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @REVIEWER-03-01 @denial
  Scenario: [SM-ACCESS-06] Reviewer yang tidak ditugaskan ditolak
    Given "Rina" tidak ditugaskan ke "P-001"
    When "Rina" membuka detail "P-001"
    Then akses ditolak dengan 403
