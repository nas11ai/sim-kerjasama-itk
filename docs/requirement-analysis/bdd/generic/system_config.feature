Feature: Konfigurasi Sistem
  Entity acuan boleh berubah, tapi tidak boleh menghapus jejak. Mengganti nama
  tidak menulis ulang data historis — laporan tahun lalu harus tetap menampilkan
  nama yang berlaku saat itu (BR-SC-03).

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
  Scenario: [SC-CONFIG-04] Mengganti nama tidak mengubah data historis
    Given SchemeType "Penelitian Dasar" dipakai submission "P-001" tahun lalu
    When admin mengganti nama SchemeType itu menjadi "Riset Dasar"
    Then laporan "P-001" tetap menampilkan "Penelitian Dasar"
