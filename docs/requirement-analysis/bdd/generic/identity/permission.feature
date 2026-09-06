Feature: Permission Additive
  Direct permission menambah, bukan menggantikan, permission yang datang dari role.
  Kasus "direct permission tanpa role sama sekali" sudah dibuktikan [FE-ACCESS-06]
  di M0 — di sini yang diperdalam adalah gabungannya.

  @BR-IAM-09 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-PERM-01] Direct permission menambah permission dari role
    Given "Sari" berperan Operator yang membawa permission "submissions.view-all"
    When Operator memberi "Sari" direct permission "reports.export"
    Then "Sari" punya permission "submissions.view-all"
    And "Sari" punya permission "reports.export"

  @BR-IAM-09 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-PERM-02] Mencabut role tidak mencabut direct permission
    Given "Sari" berperan Operator dan punya direct permission "reports.export"
    When Operator mencabut role Operator dari "Sari"
    Then "Sari" punya permission "reports.export"
    And "Sari" tidak punya permission "submissions.view-all"

  @BR-IAM-09 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-PERM-03] Dua role memberi gabungan permission keduanya
    Given "Sari" berperan Operator dan Reviewer
    When "Sari" membuka daftar permission miliknya
    Then "Sari" punya gabungan permission kedua role itu
