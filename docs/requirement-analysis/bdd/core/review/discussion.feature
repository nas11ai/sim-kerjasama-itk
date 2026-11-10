Feature: Diskusi Terulir Review
  ReviewSummary hanya berstatus open atau resolved — tidak ada closed di domain
  ini (BR-REV-13). Yang boleh me-resolve adalah reviewer, bukan researcher:
  kalau researcher bisa menutup ulirnya sendiri, ia menghapus permintaan revisi
  tanpa persetujuan siapa pun. Menutup ulir tidak memutuskan hasil review: hasil
  dihitung saat putaran review selesai (BR-REV-16).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And evaluation_status "Sari" pada "P-001" adalah completed

  @BR-FE-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-DISCUSS-01] Reviewer membuka ulir diskusi
    When "Sari" membuat ReviewSummary pada "P-001" beserta komentar pertama
    Then penambahan berhasil
    And ReviewSummary itu berstatus open

  @BR-REV-06 @M2 @RESEARCHER-02-05 @happy
  Scenario: [REV-DISCUSS-02] Researcher membalas di ulir yang sama
    Given ada ReviewSummary berstatus open pada "P-001"
    When "Budi" membalas ReviewSummary itu
    Then penambahan berhasil
    And "Sari" melihat balasan "Budi" di ulir yang sama

  @BR-REV-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-DISCUSS-03] Reviewer menandai ulir resolved
    Given ada ReviewSummary berstatus open pada "P-001"
    When "Sari" menandai ReviewSummary itu resolved
    Then ReviewSummary itu berstatus resolved

  @BR-REV-06 @M2 @RESEARCHER-02-05 @denial
  Scenario: [REV-DISCUSS-04] Researcher tidak bisa me-resolve ulirnya sendiri
    Given ada ReviewSummary berstatus open pada "P-001"
    When "Budi" menandai ReviewSummary itu resolved
    Then penyuntingan ditolak karena hanya reviewer yang boleh me-resolve

  @BR-REV-16 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-DISCUSS-05] Ulir yang resolved tidak memutuskan hasil review
    Given evaluasi seluruh reviewer "P-001" berstatus completed
    And ada satu ReviewSummary berstatus open pada "P-001"
    When "Sari" menandai ReviewSummary itu resolved
    Then status "P-001" tetap UNDER_REVIEW
    And putaran review "P-001" masih menunggu "Sari" menekan Selesai

  @BR-REV-15 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-DISCUSS-06] Ulir reviewer yang diganti diwarisi penggantinya
    Given ada ReviewSummary berstatus open pada "P-001" ditulis "Sari"
    And Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001"
    When "Andi" menandai ReviewSummary itu resolved
    Then ReviewSummary itu berstatus resolved
    And penulis asli ReviewSummary itu tetap terbaca "Sari"
