Feature: Versi Form Evaluasi yang Dipaku per Angkatan
  Form Evaluasi adalah pustaka berversi: setiap Terbitkan membentuk versi baru yang
  tidak pernah berubah lagi. Satu angkatan × langkah × Form Evaluasi dinilai dengan satu
  versi — versi itu dipaku saat draf penilaian pertama di sana tersimpan (BR-REV-18).
  Tanpa pemakuan, dua reviewer pada pengajuan yang setara bisa menilai dengan lembar yang
  berbeda hanya karena satu di antaranya mulai sesudah Admin menerbitkan versi baru.

  Background:
    Given tahap "Penelitian DIPA" ditautkan ke periode "2025" dan "2026"
    And langkah Pengajuan tahap itu memasang "Lembar Proposal" yang sudah terbit Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-VER-01] Sebelum penilaian pertama, reviewer melihat versi terbit terbaru
    Given Admin menerbitkan "Lembar Proposal" Versi 2
    And belum ada reviewer yang menyimpan draf "Lembar Proposal" di angkatan "2025"
    When "Sari" membuka lembar penilaian "P-001" di angkatan "2025"
    Then "Sari" melihat field "Lembar Proposal" Versi 2
    And belum ada versi yang dipaku untuk angkatan itu

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-02] Reviewer yang ditugaskan belakangan memakai versi pakuan
    Given "Sari" menyimpan draf penilaian "P-001" pada "Lembar Proposal" Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Dodi" ditugaskan ke "P-002" di angkatan yang sama dan membuka lembarnya
    Then "Dodi" mengisi "Lembar Proposal" Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-03] Pemakuan berlaku per langkah
    Given langkah "Laporan Monev I" tahap itu juga memasang "Lembar Proposal"
    And "Sari" menyimpan draf penilaian proposal "P-001" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Sari" membuka lembar penilaian Laporan Monev I "P-001"
    Then "Sari" melihat field "Lembar Proposal" Versi 2
    And lembar proposal angkatan itu tetap Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-04] Angkatan lain pada fase yang sama memakai versi terbaru
    Given "Sari" menyimpan draf penilaian "P-001" di angkatan "2025" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Dodi" menyimpan draf penilaian "P-101" di angkatan "2026"
    Then angkatan "2026" memakai Versi 2
    And angkatan "2025" tetap memakai Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-VER-05] Versi baru tidak mengubah field versi lama
    Given "Sari" mengirim penilaian "P-001" dengan field "Kebaruan" Versi 1
    When Admin menerbitkan Versi 2 yang mengganti label field itu menjadi "Orisinalitas"
    Then lembar "Sari" tetap terbaca dengan label "Kebaruan"
    And field Versi 1 tidak bisa diubah
