Feature: Hasil Review
  Hasil review dihitung di akhir putaran review (BR-REV-16): begitu peserta terakhir
  menekan Selesai, ReviewSummary yang masih open membuat pengajuan NEEDS_REVISION
  serentak (BR-REV-06); tanpa itu pengajuan siap diputuskan. APPROVED dan REJECTED
  hanya lewat aksi Operator (BR-REV-05, BR-REV-07, BR-REV-14, ADR 0006, ADR 0018).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" dan "Andi" ditugaskan sebagai reviewer "P-001"

  @BR-REV-05 @BR-REV-16 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-01] Semua peserta Selesai menilai tanpa catatan terbuka membuat pengajuan siap diputuskan
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And tidak ada ReviewSummary berstatus open pada "P-001"
    When "Sari" dan "Andi" menekan Selesai menilai
    Then status "P-001" tetap UNDER_REVIEW
    And "P-001" siap diputuskan

  @BR-REV-16 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-02] Satu peserta belum Selesai menilai menahan putaran
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    When hanya "Sari" yang menekan Selesai menilai
    Then putaran penilaian "P-001" masih berjalan
    And "P-001" belum siap diputuskan

  @BR-REV-05 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-03] Summary open menahan APPROVED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And ada ReviewSummary berstatus open pada "P-001"
    When sistem mengevaluasi hasil review "P-001"
    Then status "P-001" tidak menjadi APPROVED

  @BR-REV-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-04] Peserta terakhir Selesai dengan catatan terbuka menjadikan NEEDS_REVISION
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    And "Sari" membuka ReviewSummary berstatus open pada "P-001"
    When "Sari" dan "Andi" menekan Selesai menilai
    Then status "P-001" menjadi NEEDS_REVISION
    And "Budi" menerima notifikasi RevisionRequested

  @BR-REV-06 @BR-REV-16 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-05] Menutup catatan terakhir tidak mengubah status; putaran pemeriksaan tanpa catatan membuat siap
    Given putaran pemeriksaan "P-001" berjalan dengan peserta "Sari" dan "Andi"
    When "Sari" dan "Andi" menandai ReviewSummary mereka resolved
    Then status "P-001" tetap UNDER_REVIEW
    When "Sari" dan "Andi" menekan Selesai memeriksa
    Then "P-001" siap diputuskan

  @BR-REV-16 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-06] Peserta not_required dihitung selesai
    Given evaluasi "Sari" atas "P-001" berstatus completed
    And evaluation_status "Andi" pada "P-001" adalah not_required
    When "Sari" menekan Selesai menilai
    Then putaran penilaian "P-001" selesai tanpa menunggu "Andi"
    And "P-001" siap diputuskan

  @BR-REV-07 @M2 @OPERATOR-02-05 @happy
  Scenario: [REV-OUTCOME-07] Penolakan hanya lewat aksi eksplisit Operator
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed
    When Operator menolak "P-001" dengan alasan tertulis
    Then status "P-001" menjadi REJECTED
    And "Budi" menerima notifikasi SubmissionRejected

  @BR-REV-07 @BR-REV-14 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-OUTCOME-08] Tidak ada kombinasi yang menghasilkan REJECTED
    Given evaluasi "Sari" dan "Andi" atas "P-001" berstatus completed dengan skor terendah
    When putaran review "P-001" disimpulkan, dengan atau tanpa catatan terbuka
    Then status "P-001" tidak menjadi REJECTED
    And status "P-001" tidak menjadi APPROVED

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

  @BR-REV-16 @M2 @RESEARCHER-02-05 @happy
  Scenario: [REV-OUTCOME-16] Kirim ulang membuka putaran pemeriksaan untuk reviewer bercatatan terbuka
    Given status "P-001" NEEDS_REVISION setelah putaran penilaian selesai
    And hanya "Sari" yang punya ReviewSummary berstatus open pada "P-001"
    When "Budi" mengirim ulang "P-001"
    Then putaran pemeriksaan "P-001" dibuka dengan peserta "Sari" saja
    And "Sari" menerima notifikasi RecheckRequested
    And "Andi" tidak menerima notifikasi RecheckRequested

  @BR-REV-10 @BR-REV-16 @M2 @OPERATOR-03-02 @boundary
  Scenario: [REV-OUTCOME-19] Pengganti reviewer menjadi peserta belum selesai di putaran berjalan
    Given "Sari" sudah menekan Selesai menilai pada putaran berjalan "P-001"
    When Operator mengganti "Sari" dengan "Dewi"
    Then "Dewi" menjadi peserta putaran berjalan "P-001" yang belum selesai
    And "Sari" bukan lagi peserta putaran itu

  @BR-REV-16 @BR-FE-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-13] Reviewer terakhir masih bisa meminta revisi setelah mengirim lembarnya
    Given "Andi" sudah menekan Selesai menilai pada "P-001"
    When "Sari" mengirim lembar penilaian terakhir "P-001"
    Then status "P-001" tetap UNDER_REVIEW
    When "Sari" membuka ReviewSummary lalu menekan Selesai menilai
    Then status "P-001" menjadi NEEDS_REVISION

  @BR-REV-16 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-OUTCOME-14] Selesai menilai ditolak selama evaluasi belum completed
    Given evaluation_status "Andi" pada "P-001" adalah pending
    When "Andi" menekan Selesai menilai
    Then aksi ditolak dengan pesan "Kirim lembar penilaian lebih dulu."

  @BR-REV-17 @M2 @REVIEWER-03-04 @denial
  Scenario: [REV-OUTCOME-15] Setelah Selesai, membuka catatan baru di putaran itu ditolak
    Given "Sari" sudah menekan Selesai menilai pada putaran berjalan "P-001"
    When "Sari" membuka ReviewSummary baru pada "P-001"
    Then aksi ditolak
    And tidak ada ReviewSummary baru pada "P-001"

  @BR-REV-16 @M2 @REVIEWER-03-04 @boundary
  Scenario: [REV-OUTCOME-17] Menutup satu catatan saat catatan rekan terbuka tidak mengembalikan ke revisi
    Given putaran pemeriksaan "P-001" berjalan dengan peserta "Sari" dan "Andi"
    And keduanya punya ReviewSummary berstatus open
    When "Sari" menandai ReviewSummary-nya resolved
    Then status "P-001" tetap UNDER_REVIEW
    And putaran pemeriksaan "P-001" masih berjalan

  @BR-REV-06 @M2 @REVIEWER-03-04 @happy
  Scenario: [REV-OUTCOME-18] Putaran pemeriksaan selesai dengan catatan masih terbuka menjadikan NEEDS_REVISION serentak
    Given putaran pemeriksaan "P-001" berjalan dengan peserta "Sari" dan "Andi"
    And ReviewSummary "Andi" masih berstatus open
    When "Sari" dan "Andi" menekan Selesai memeriksa
    Then status "P-001" menjadi NEEDS_REVISION
    And "Budi" menerima notifikasi RevisionRequested sekali

  @BR-REV-16 @M2 @OPERATOR-03-01 @happy
  Scenario: [REV-OUTCOME-20] Operator diberi tahu saat putaran selesai tanpa catatan terbuka
    Given tidak ada ReviewSummary berstatus open pada "P-001"
    When peserta terakhir putaran "P-001" menekan Selesai
    Then Operator menerima notifikasi SubmissionReadyForDecision
