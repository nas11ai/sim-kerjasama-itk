Feature: Akses Form — Permission dan Organisasi
  Sebuah Form terbuka bagi user kalau ada FormAccessControl yang permission-nya
  dimiliki user DAN organization_id-nya merupakan organisasi user sendiri atau salah
  satu leluhurnya. Dibaca sebagai kalimat: Form yang dipasang pada sebuah organisasi
  terlihat oleh organisasi itu dan semua unit di bawahnya (ADR 0013).
  Keduanya harus terpenuhi; salah satu saja tidak cukup.

  Background:
    Given Organization "ITK" membawahi "FTI", dan "FTI" membawahi "Informatika"
    And Form "Pengajuan Penelitian" punya FormAccessControl permission "submissions.create" pada organisasi "FTI"

  @BR-FE-02 @M0 @happy
  Scenario: [FE-ACCESS-01] User dengan permission dan organisasi yang cocok bisa akses
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    When "Budi" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-02 @M0 @happy
  Scenario: [FE-ACCESS-02] Organisasi anak ikut terjangkau dari Form ber-scope induk
    Given "Sari" berorganisasi "Informatika" dan punya permission "submissions.create"
    When "Sari" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-03] Organisasi induk tidak ikut terjangkau
    Given "Rina" berorganisasi "ITK" dan punya permission "submissions.create"
    When "Rina" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-04] Permission cocok tapi organisasi di cabang lain
    Given Organization "FSAINS" berada di bawah "ITK"
    And "Andi" berorganisasi "FSAINS" dan punya permission "submissions.create"
    When "Andi" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-05] Organisasi cocok tapi permission tidak dimiliki
    Given "Maya" berorganisasi "FTI" tanpa permission "submissions.create"
    When "Maya" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @BR-IAM-09 @M0 @happy
  Scenario: [FE-ACCESS-06] Direct permission memberi akses tanpa lewat role
    Given "Hendra" berorganisasi "FTI" tanpa role apa pun
    And "Hendra" diberi direct permission "submissions.create"
    When "Hendra" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-01 @M0 @denial
  Scenario: [FE-ACCESS-07] Submission tidak bisa dibuat saat periode force closed
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    And periode berjalan berstatus is_force_closed
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then pembuatan ditolak karena periode sudah ditutup

  @BR-FE-01 @M0 @happy
  Scenario: [FE-ACCESS-08] Submission bisa dibuat saat periode aktif
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    And periode berjalan aktif dan belum force closed
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1

  @BR-FE-01 @M0 @denial
  Scenario: [FE-ACCESS-09] Pengajuan baru ditolak setelah Batas Submit meski Batas Evaluasi masih di depan
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    And periode berjalan punya "Batas Submit" yang sudah lewat dan "Batas Evaluasi" yang belum lewat
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then pembuatan ditolak karena periode sudah berakhir
