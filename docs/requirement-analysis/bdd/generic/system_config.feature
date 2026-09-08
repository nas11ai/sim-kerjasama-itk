Feature: Konfigurasi Sistem
  Entity acuan boleh berubah, tapi tidak boleh menghapus jejak. Mengganti nama
  tidak menulis ulang baris historis: tautannya tetap menunjuk entity yang sama
  (BR-SC-03). Yang tidak dijaminkan adalah nama lamanya tetap tampil — tidak ada
  snapshot nama di mana pun. Kalau kategorinya memang berubah makna, jalannya
  membuat entity baru lalu menonaktifkan yang lama, bukan mengganti nama.

  @BR-SC-01 @M5 @ADMIN-08-10 @denial
  Scenario: [SC-CONFIG-01] Entity acuan yang masih dipakai tidak bisa dihapus
    Given SchemeType "Penelitian Dasar" masih dipakai skema aktif
    When admin menghapus SchemeType "Penelitian Dasar"
    Then penghapusan ditolak karena entity acuan masih dipakai

  @BR-SC-01 @M5 @ADMIN-08-10 @happy
  Scenario: [SC-CONFIG-02] Entity acuan tanpa relasi aktif bisa dihapus
    Given SchemeType "Penelitian Terapan" tidak dipakai skema mana pun
    When admin menghapus SchemeType "Penelitian Terapan"
    Then penghapusan berhasil

  @BR-SC-02 @M5 @ADMIN-08-10 @denial
  Scenario: [SC-CONFIG-03] SubmissionType tidak bisa diubah lewat antarmuka
    When admin menambah SubmissionType baru lewat antarmuka
    Then penambahan ditolak karena SubmissionType hanya diubah lewat seeder

  @BR-SC-03 @M5 @ADMIN-08-10 @boundary
  Scenario: [SC-CONFIG-04] Mengganti nama tidak memutus tautan historis
    Given SchemeType "Penelitian Dasar" dipakai submission "P-001" tahun lalu
    When admin mengganti nama SchemeType itu menjadi "Riset Dasar"
    Then "P-001" tetap menunjuk SchemeType yang sama
    And laporan "P-001" menampilkan "Riset Dasar"

  @BR-SC-03 @BR-SC-01 @M5 @ADMIN-08-10 @boundary
  Scenario: [SC-CONFIG-05] Kategori yang berubah makna dibuat baru, bukan diganti nama
    Given SchemeType "Penelitian Dasar" dipakai submission "P-001" tahun lalu
    When admin membuat SchemeType "Riset Terapan" lalu menonaktifkan "Penelitian Dasar"
    Then laporan "P-001" tetap menampilkan "Penelitian Dasar"
    And "Penelitian Dasar" tidak lagi ditawarkan pada skema baru
