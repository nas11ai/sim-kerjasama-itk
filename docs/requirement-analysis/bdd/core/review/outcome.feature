Feature: Hasil Review
  Dua transisi otomatis dan satu yang haram otomatis. APPROVED dan NEEDS_REVISION
  lahir dari kombinasi yang sama — semua evaluasi selesai, lalu ada atau tidaknya
  ReviewSummary berstatus open. REJECTED tidak pernah otomatis (ADR 0006,
  BR-REV-07, BR-REV-14).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" dan "Andi" ditugaskan sebagai reviewer "P-001"

  @BR-REV-05 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-01] Semua selesai tanpa summary open menjadi APPROVED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And tidak ada ReviewSummary berstatus open pada "P-001"
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" menjadi APPROVED

  @BR-REV-05 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-02] Satu reviewer belum selesai menahan transisi
    Given evaluasi "Sari" atas "P-001" berstatus completed
    And evaluation_status "Andi" pada "P-001" adalah pending
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" tetap UNDER_REVIEW

  @BR-REV-05 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-03] Summary open menahan APPROVED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And ada ReviewSummary berstatus open pada "P-001"
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" tidak menjadi APPROVED

  @BR-REV-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-04] Semua selesai dengan summary open menjadi NEEDS_REVISION
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And ada ReviewSummary berstatus open pada "P-001"
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" menjadi NEEDS_REVISION
    And "Budi" menerima notifikasi RevisionRequested

  @BR-REV-06 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-05] Summary terakhir yang resolved membuka APPROVED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And ada ReviewSummary berstatus open pada "P-001"
    When "Sari" menandai ReviewSummary terakhir "P-001" resolved
    Then status "P-001" menjadi APPROVED

  @BR-REV-05 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-06] Reviewer not_required ikut dihitung selesai
    Given evaluasi "Sari" atas "P-001" berstatus completed
    And evaluation_status "Andi" pada "P-001" adalah not_required
    And tidak ada ReviewSummary berstatus open pada "P-001"
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" menjadi APPROVED

  @BR-REV-07 @M2 @OPERATOR-02-05 @happy
  Scenario: [REV-OUTCOME-07] Penolakan hanya lewat aksi eksplisit Operator
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    When Operator menolak "P-001" dengan alasan tertulis
    Then status "P-001" menjadi REJECTED
    And "Budi" menerima notifikasi SubmissionRejected

  @BR-REV-07 @BR-REV-14 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-OUTCOME-08] Tidak ada kombinasi evaluasi yang menghasilkan REJECTED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed dengan skor terendah
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" tidak menjadi REJECTED

  @BR-REV-05 @M2 @RESEARCHER-02-10 @boundary
  Scenario: [REV-OUTCOME-09] Fase tanpa review disetujui secara hampa
    Given fase pengajuan "P-002" tidak membutuhkan review
    And submission "P-002" milik "Budi" berstatus SUBMITTED
    And tidak ada reviewer ditugaskan pada "P-002"
    When sistem mengevaluasi hasil review "P-002"
    Then status "P-002" menjadi APPROVED

  @BR-REV-05 @M2 @OPERATOR-03-01 @denial
  Scenario: [REV-OUTCOME-10] Fase yang butuh review tidak disetujui tanpa reviewer
    Given fase pengajuan "P-003" membutuhkan review
    And submission "P-003" milik "Budi" berstatus SUBMITTED
    And tidak ada reviewer ditugaskan pada "P-003"
    When sistem mengevaluasi hasil review "P-003"
    Then status "P-003" tetap SUBMITTED
