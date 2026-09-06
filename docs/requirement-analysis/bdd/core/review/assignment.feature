Feature: Penugasan Reviewer
  Jumlah minimum reviewer berasal dari scheme.rules.min_reviewer_count, bukan
  konstanta di kode. Submission baru berpindah ke UNDER_REVIEW saat minimum itu
  tercapai — sebelum itu ia tetap SUBMITTED (BR-REV-01).

  Background:
    Given skema "DIPA Internal" dengan min_reviewer_count 2
    And submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED

  @BR-REV-01 @M2 @OPERATOR-03-01 @happy
  Scenario: [REV-ASSIGN-01] Reviewer pertama tidak memindahkan status
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan berhasil
    And status "P-001" tetap SUBMITTED

  @BR-REV-01 @M2 @OPERATOR-03-01 @boundary
  Scenario: [REV-ASSIGN-02] Belum mencapai minimum reviewer
    Given "Sari" ditugaskan sebagai reviewer "P-001"
    When Operator membuka penugasan "P-001"
    Then Operator melihat indikator "1 dari minimal 2"
    And status "P-001" tetap SUBMITTED

  @BR-REV-01 @M2 @OPERATOR-03-01 @happy
  Scenario: [REV-ASSIGN-03] Reviewer terakhir memindahkan submission ke UNDER_REVIEW
    Given "Sari" ditugaskan sebagai reviewer "P-001"
    When Operator menugaskan "Andi" sebagai reviewer "P-001"
    Then status "P-001" menjadi UNDER_REVIEW
    And "Budi" menerima notifikasi ReviewStarted

  @BR-REV-02 @M2 @OPERATOR-03-01 @denial
  Scenario: [REV-ASSIGN-04] submitted_by tidak bisa mereview pengajuannya sendiri
    When Operator menugaskan "Budi" sebagai reviewer "P-001"
    Then penugasan ditolak karena konflik kepentingan

  @BR-REV-03 @M2 @OPERATOR-03-01 @denial
  Scenario: [REV-ASSIGN-05] Reviewer yang sama tidak bisa ditugaskan dua kali
    Given "Sari" ditugaskan sebagai reviewer "P-001"
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan ditolak karena reviewer sudah ditugaskan pada siklus ini

  @BR-REV-04 @M2 @OPERATOR-03-01 @boundary
  Scenario: [REV-ASSIGN-06] Beban melebihi batas memberi peringatan, bukan penolakan
    Given skema "DIPA Internal" dengan max_reviewer_workload 5
    And "Sari" sedang memegang 5 penugasan review aktif
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan berhasil
    And Operator melihat peringatan beban reviewer terlampaui

  @BR-REV-02 @BR-SM-08 @BR-IAM-07 @M2 @OPERATOR-03-01 @denial
  Scenario: [REV-ASSIGN-07] Anggota penelitian tidak bisa mereview pengajuan yang sama
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan ditolak karena konflik kepentingan

  @BR-REV-09 @M2 @OPERATOR-08-04 @denial
  Scenario: [REV-ASSIGN-08] Reviewer di luar masa tugasnya tidak bisa ditugaskan
    Given waktu sekarang "2026-03-01 09:00:00"
    And "Sari" berstatus reviewer dengan masa tugas "2026-01-01" sampai "2026-02-28"
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan ditolak karena reviewer di luar masa tugas

  @BR-REV-01 @M2 @REVIEWER-03-01 @SHARED-02-02 @happy
  Scenario: [REV-ASSIGN-09] Reviewer yang ditugaskan menerima notifikasi
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then "Sari" menerima notifikasi ReviewerAssigned
    And "P-001" muncul di daftar penugasan "Sari"
