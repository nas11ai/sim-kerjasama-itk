Feature: Struktur Tahap Berversi, Dipaku per Angkatan
  Tahap (Fase) dipakai banyak periode. Tanpa versi, menyiapkan tahap untuk 2026 bisa
  mengubah angkatan 2025 yang monevnya masih berjalan, karena pengajuan merujuk
  langkahnya langsung dan siklus monev dihitung saat dibaca. Karena itu setiap
  penerbitan membentuk versi struktur yang beku (BR-FE-32), dan sebuah angkatan
  (Periode × Tahap) dipaku ke versi terbaru saat pengajuan pertamanya dibuat.
  Sebelum itu angkatan memakai versi terbaru, jadi perbaikan tahap tetap terbawa.

  @BR-FE-32 @M2 @ADMIN-08-09 @happy
  Scenario: [FE-PHVER-01] Pengajuan pertama angkatan memaku versi struktur terbit terbaru
    Given tahap "Hibah Internal" punya Versi 1 dan ditautkan ke "Periode 2025"
    And angkatan "Periode 2025" × "Hibah Internal" belum punya pengajuan
    When peneliti "P-001" memulai pengajuan di "Periode 2025"
    Then angkatan itu terpaku Versi 1
    And pengajuan "P-001" memakai langkah Pengajuan Versi 1

  @BR-FE-32 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-PHVER-02] Sebelum pengajuan pertama, angkatan memakai versi terbaru — perbaikan fase terbawa
    Given tahap "Hibah Internal" punya Versi 1 dan ditautkan ke "Periode 2025"
    And angkatan "Periode 2025" × "Hibah Internal" belum punya pengajuan
    When Admin menerbitkan Versi 2 yang menambah Monev III
    And peneliti "P-001" memulai pengajuan di "Periode 2025"
    Then angkatan itu terpaku Versi 2
    And siklus monev "P-001" memuat Monev III

  @BR-FE-32 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-PHVER-03] Versi baru tidak mengubah siklus monev angkatan yang sudah terpaku
    Given angkatan "Periode 2025" × "Hibah Internal" terpaku Versi 1 dengan Monev I dan Monev II
    And Admin menerbitkan Versi 2 yang menambah Monev III
    When peneliti "P-001" di "Periode 2025" membuka siklus monevnya
    Then ia melihat Monev I dan Monev II saja
    And Rekap Monev "Periode 2025" hanya menghitung Monev I dan Monev II

  @BR-FE-32 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-PHVER-04] Angkatan lain pada fase yang sama memakai versi terbaru
    Given angkatan "Periode 2025" × "Hibah Internal" terpaku Versi 1
    And "Hibah Internal" juga ditautkan ke "Periode 2026" yang belum punya pengajuan
    When Admin menerbitkan Versi 2 yang menambah Monev III
    And peneliti "P-002" memulai pengajuan di "Periode 2026"
    Then angkatan "Periode 2026" × "Hibah Internal" terpaku Versi 2
    And angkatan "Periode 2025" × "Hibah Internal" tetap terpaku Versi 1

  @BR-FE-32 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHVER-05] Form pengajuan tahap yang sudah dipakai angkatan tidak bisa diganti
    Given angkatan "Periode 2025" × "Hibah Internal" terpaku Versi 1 dengan Form pengajuan "Proposal Hibah"
    When Admin menerbitkan versi yang langkah Pengajuan-nya memakai Form "Proposal Lain"
    Then penerbitan ditolak dengan pesan "Form pengajuan tahap ini sudah dipakai angkatan berjalan, jadi tidak bisa diganti. Buat tahap baru untuk Form lain."
    And tahap itu tetap di Versi 1
