Feature: Siklus Monitoring dan Evaluasi
  Monev bukan phase terpisah — ia FormPhaseDetail berurutan dalam FormPhase yang
  sama, dan tiap laporannya child FormSubmission yang menunjuk induk lewat
  parent_submission_id. canProceed() adalah turunan keadaan, bukan kolom yang
  disetel seseorang (BR-MON-02).

  Background:
    Given submission "P-001" milik "Budi" berstatus APPROVED

  @BR-MON-01 @M4 @RESEARCHER-05-01 @denial
  Scenario: [MON-CYCLE-01] Fase monev tertutup selama induk belum APPROVED
    Given submission "P-009" milik "Budi" berstatus UNDER_REVIEW
    When "Budi" membuka fase "Laporan Monev I" pada "P-009"
    Then akses ditolak dengan 403

  @BR-MON-01 @M4 @RESEARCHER-05-01 @happy
  Scenario: [MON-CYCLE-02] Induk APPROVED membuka fase Laporan Monev I
    When "Budi" membuka fase "Laporan Monev I" pada "P-001"
    Then detail terbuka

  @BR-MON-02 @M4 @RESEARCHER-05-02 @denial
  Scenario: [MON-CYCLE-03] Monev II tertutup selama Monev I belum disubmit
    Given laporan "Laporan Monev I" pada "P-001" belum disubmit
    When "Budi" membuka fase "Laporan Monev II" pada "P-001"
    Then akses ditolak dengan 403

  @BR-MON-02 @M4 @RESEARCHER-05-01 @happy
  Scenario: [MON-CYCLE-04] Monev I yang tuntas membuka Monev II
    Given detail "Laporan Monev I" berstatus needs_review true
    And laporan "Laporan Monev I" pada "P-001" sudah disubmit
    And evaluasi laporan itu selesai tanpa ReviewSummary open
    When "Budi" membuka fase "Laporan Monev II" pada "P-001"
    Then detail terbuka

  @BR-MON-02 @M4 @RESEARCHER-05-01 @boundary
  Scenario: [MON-CYCLE-05] Detail tanpa review cukup disubmit
    Given detail "Laporan Monev I" berstatus needs_review false
    And laporan "Laporan Monev I" pada "P-001" sudah disubmit
    When "Budi" membuka fase "Laporan Monev II" pada "P-001"
    Then detail terbuka

  @BR-MON-03 @M4 @RESEARCHER-05-02 @denial
  Scenario: [MON-CYCLE-06] Satu induk hanya punya satu laporan per detail
    Given laporan "Laporan Monev I" pada "P-001" sudah ada
    When "Budi" membuat laporan "Laporan Monev I" lagi pada "P-001"
    Then penambahan ditolak karena laporan untuk detail itu sudah ada

  @BR-MON-03 @M4 @RESEARCHER-05-01 @boundary
  Scenario: [MON-CYCLE-07] Batas laporan berlaku per induk, bukan per researcher
    Given submission "P-002" milik "Budi" berstatus APPROVED
    And laporan "Laporan Monev I" pada "P-001" sudah ada
    When "Budi" membuat laporan "Laporan Monev I" pada "P-002"
    Then penambahan berhasil

  @BR-MON-05 @BR-FE-08 @M4 @RESEARCHER-05-02 @denial
  Scenario: [MON-CYCLE-08] Lewat deadline monev tanpa override, pengisian ditolak
    Given waktu sekarang "2026-06-10 09:00:00"
    And detail "Laporan Monev I" berbatas "Batas Monev" pada "2026-06-01"
    And tidak ada FormSubmissionOverride berlaku untuk "P-001"
    When "Budi" mengisi laporan "Laporan Monev I" pada "P-001"
    Then penyimpanan ditolak dengan 403

  @BR-MON-05 @BR-FE-08 @M4 @OPERATOR-02-03 @happy
  Scenario: [MON-CYCLE-09] Override yang masih berlaku membuka kembali fase monev
    Given waktu sekarang "2026-06-10 09:00:00"
    And detail "Laporan Monev I" berbatas "Batas Monev" pada "2026-06-01"
    And Operator membuat override untuk "P-001" pada fase "Laporan Monev I" yang berlaku sampai "2026-06-20 23:59:59"
    When "Budi" mengisi laporan "Laporan Monev I" pada "P-001"
    Then penyimpanan berhasil

  @BR-MON-08 @M4 @RESEARCHER-05-01 @denial
  Scenario: [MON-CYCLE-10] Penarikan membekukan siklus monev berjalan
    Given submission "P-001" berstatus WITHDRAWN
    When "Budi" membuat laporan "Laporan Monev II" pada "P-001"
    Then penambahan ditolak karena siklus monev dibekukan

  @BR-MON-08 @M4 @RESEARCHER-05-03 @boundary
  Scenario: [MON-CYCLE-11] Laporan yang terlanjur terkirim tetap terbaca
    Given laporan "Laporan Monev I" pada "P-001" sudah disubmit
    And submission "P-001" berstatus WITHDRAWN
    When "Budi" membuka laporan "Laporan Monev I" pada "P-001"
    Then detail terbuka

  @BR-MON-08 @M4 @RESEARCHER-05-01 @denial
  Scenario: [MON-CYCLE-12] Siklus yang dibekukan tidak bisa dibuka lagi
    Given submission "P-001" berstatus WITHDRAWN
    When Operator mencoba membatalkan penarikan "P-001"
    Then pembatalan ditolak karena WITHDRAWN bersifat terminal

  @BR-MON-08 @M4 @RESEARCHER-05-02 @denial
  Scenario: [MON-CYCLE-13] Draft laporan yang terlanjur dibuat tidak bisa disubmit setelah penarikan
    Given laporan "Laporan Monev I" pada "P-001" masih draft dengan jawaban lengkap
    And submission "P-001" berstatus WITHDRAWN
    When "Budi" submit laporan "Laporan Monev I" pada "P-001"
    Then submit ditolak karena siklus monevnya dibekukan
    And laporan "Laporan Monev I" tetap draft

  @BR-SM-04 @BR-MON-03 @M4 @RESEARCHER-05-02 @denial
  Scenario: [MON-CYCLE-14] Hanya ketua induk yang mengisi laporan monev
    Given "Ani" terdaftar sebagai co_investigator aktif di "P-001"
    And laporan "Laporan Monev I" pada "P-001" masih draft
    When "Ani" menyimpan laporan "Laporan Monev I" pada "P-001"
    Then penyimpanan ditolak dengan 403
    And "Ani" juga tidak bisa memulai maupun mengirim laporan itu

  @BR-MON-04 @M4 @OPERATOR-05-01 @OPERATOR-05-02 @happy
  Scenario: [MON-REVIEW-01] Reviewer monev ditugaskan manual per siklus
    When Operator menugaskan "Sari" sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    Then penugasan berhasil

  @BR-MON-04 @M4 @OPERATOR-05-02 @boundary
  Scenario: [MON-REVIEW-02] Tidak ada reviewer yang otomatis ikut dari siklus sebelumnya
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    When Operator membuka penugasan siklus "Laporan Monev II" pada "P-001"
    Then belum ada reviewer ditugaskan pada siklus itu

  @BR-MON-06 @M4 @OPERATOR-05-02 @happy
  Scenario: [MON-REVIEW-03] Penugasan siklus membuat SubmissionReviewer baru
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    When Operator menugaskan "Sari" sebagai reviewer siklus "Laporan Monev II" pada "P-001"
    Then SubmissionReviewer baru terbentuk untuk siklus itu

  @BR-MON-06 @M4 @REVIEWER-03-02 @boundary
  Scenario: [MON-REVIEW-04] Record siklus sebelumnya tidak dipakai ulang
    Given "Sari" sudah mengevaluasi siklus "Laporan Monev I" pada "P-001"
    When Operator menugaskan "Sari" sebagai reviewer siklus "Laporan Monev II" pada "P-001"
    Then evaluasi "Sari" pada siklus "Laporan Monev I" tetap terbaca sebagai riwayat

  @BR-MON-07 @M4 @OPERATOR-05-02 @boundary
  Scenario: [MON-REVIEW-05] Reviewer yang masa tugasnya lewat tidak muncul di saran
    Given waktu sekarang "2026-06-10 09:00:00"
    And "Sari" ditugaskan sebagai reviewer "P-001" dengan masa tugas berakhir "2026-05-01"
    When Operator membuka daftar saran reviewer siklus "Laporan Monev I" pada "P-001"
    Then "Sari" tidak muncul di daftar saran reviewer

  @BR-MON-07 @M4 @OPERATOR-05-02 @happy
  Scenario: [MON-REVIEW-06] Reviewer awal yang masih aktif muncul sebagai saran
    Given waktu sekarang "2026-06-10 09:00:00"
    And "Sari" ditugaskan sebagai reviewer "P-001" dengan masa tugas berakhir "2026-12-31"
    When Operator membuka daftar saran reviewer siklus "Laporan Monev I" pada "P-001"
    Then "Sari" muncul di daftar saran reviewer
    And Operator tetap bisa memilih reviewer di luar daftar saran

  @BR-MON-09 @M4 @RESEARCHER-05-03 @happy
  Scenario: [MON-REVIEW-07] Hasil evaluasi monev terlihat begitu reviewer submit
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    And "Sari" sudah submit evaluasi siklus "Laporan Monev I" pada "P-001"
    When "Budi" membuka hasil evaluasi siklus "Laporan Monev I" pada "P-001"
    Then "Budi" melihat skor dan catatan monev

  @BR-MON-09 @M4 @RESEARCHER-05-03 @denial
  Scenario: [MON-REVIEW-08] Hasil evaluasi monev tertutup sebelum reviewer submit
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    And evaluasi "Sari" pada siklus itu belum disubmit
    When "Budi" membuka hasil evaluasi siklus "Laporan Monev I" pada "P-001"
    Then "Budi" tidak melihat skor dan catatan monev

  @BR-MON-09 @M4 @RESEARCHER-02-03 @boundary
  Scenario: [MON-REVIEW-09] Pengecualian monev tidak berlaku untuk pengajuan awal
    Given submission "P-003" milik "Budi" berstatus UNDER_REVIEW
    And "Sari" sudah submit evaluasinya atas "P-003"
    When "Budi" membuka hasil review "P-003"
    Then "Budi" tidak melihat skor reviewer

  @BR-MON-10 @M4 @RESEARCHER-05-01 @happy
  Scenario: [MON-REVIEW-10] Pembukaan siklus memberi tahu peneliti
    When Operator membuka siklus "Laporan Monev I" pada "P-001"
    Then "Budi" menerima notifikasi in-app

  @BR-MON-10 @M4 @REVIEWER-03-01 @happy
  Scenario: [MON-REVIEW-11] Pembukaan siklus memberi tahu reviewer yang ditugaskan
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    When Operator membuka siklus "Laporan Monev I" pada "P-001"
    Then "Sari" menerima notifikasi in-app

  @BR-MON-10 @M4 @RESEARCHER-05-03 @happy
  Scenario: [MON-REVIEW-12] Submit evaluasi monev memberi tahu peneliti
    Given "Sari" ditugaskan sebagai reviewer siklus "Laporan Monev I" pada "P-001"
    When "Sari" submit evaluasi siklus "Laporan Monev I" pada "P-001"
    Then "Budi" menerima notifikasi in-app

  @BR-MON-10 @M4 @RESEARCHER-05-01 @boundary
  Scenario: [MON-REVIEW-13] Siklus yang belum dibuka tidak memberi tahu siapa pun
    Given siklus "Laporan Monev II" pada "P-001" belum dibuka
    When Operator membuka daftar siklus "P-001"
    Then "Budi" tidak menerima notifikasi in-app
