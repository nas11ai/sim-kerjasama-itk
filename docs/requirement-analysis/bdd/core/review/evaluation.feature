Feature: Evaluasi Reviewer dan Penguncian Jawaban
  ReviewFormResponse terkunci begitu status-nya submitted — aturan Form Engine
  (BR-FE-05) yang dicerminkan BC Review sebagai BR-REV-08. Satu implementasi,
  dua tempat yang menyebutnya. ReviewSummary baru boleh dibuat setelah evaluasi
  selesai (BR-FE-06).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" ditugaskan sebagai reviewer "P-001"

  @BR-REV-08 @BR-FE-05 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-EVAL-01] Evaluasi draft masih bisa disunting
    Given evaluasi "Sari" atas "P-001" berstatus draft
    When "Sari" menyunting evaluasinya atas "P-001"
    Then penyuntingan berhasil

  @BR-REV-08 @BR-FE-05 @M2 @REVIEWER-03-03 @denial
  Scenario: [REV-EVAL-02] Evaluasi terkunci setelah disubmit
    Given evaluasi "Sari" atas "P-001" berstatus submitted
    When "Sari" menyunting evaluasinya atas "P-001"
    Then penyuntingan ditolak karena evaluasi sudah terkunci

  @BR-REV-08 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-EVAL-03] Submit evaluasi menyelesaikan penugasan
    Given evaluasi "Sari" atas "P-001" berstatus draft
    When "Sari" submit evaluasinya atas "P-001"
    Then evaluation_status "Sari" pada "P-001" menjadi completed

  @BR-FE-06 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-EVAL-04] ReviewSummary ditolak sebelum evaluasi selesai
    Given evaluation_status "Sari" pada "P-001" adalah pending
    When "Sari" membuat ReviewSummary pada "P-001"
    Then penambahan ditolak karena evaluasi belum selesai

  @BR-FE-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-EVAL-05] ReviewSummary terbuka setelah evaluasi selesai
    Given evaluation_status "Sari" pada "P-001" adalah completed
    When "Sari" membuat ReviewSummary pada "P-001"
    Then penambahan berhasil

  @BR-FE-06 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-EVAL-06] Status not_required juga membuka ReviewSummary
    Given evaluation_status "Sari" pada "P-001" adalah not_required
    When "Sari" membuat ReviewSummary pada "P-001"
    Then penambahan berhasil
