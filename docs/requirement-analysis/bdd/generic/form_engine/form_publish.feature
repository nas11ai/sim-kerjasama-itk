Feature: Draf dan Terbit untuk Field Form
  Field Form langsung dibaca wizard peneliti, jadi menyunting field di tempat membuat
  setengah perubahan terlihat pengisi dan membuat `created_at`/`required_since` jatuh pada
  saat Admin mengetik, bukan saat perubahan berlaku. Karena itu field disunting sebagai
  draf, dan Terbitkan menulisnya ke field hidup sekaligus (BR-FE-34). Form tidak berversi:
  field yang sama diperbarui, jadi jawaban lama tetap menempel pada field-nya.

  @BR-FE-34 @M2 @ADMIN-08-06 @boundary
  Scenario: [FE-FORMPUB-01] Draf field tidak terlihat peneliti sebelum terbit
    Given Form "Pengajuan Penelitian" sudah terbit dengan field "Judul"
    And Admin menyimpan draf yang menambah field "Mitra"
    When peneliti "P-001" membuka isian Form itu
    Then peneliti hanya melihat field "Judul"

  @BR-FE-34 @BR-FE-13 @M2 @ADMIN-08-06 @happy
  Scenario: [FE-FORMPUB-02] Ringkasan dampak menyebut field wajib baru, draf berjalan, dan label yang diubah
    Given Form "Pengajuan Penelitian" punya 2 draf berjalan dan field "Judul" yang sudah dijawab 3 pengajuan
    And Admin menyimpan draf yang menambah field wajib "Mitra", mengganti label "Judul" menjadi "Judul Penelitian", dan menghapus field "Lokasi"
    When Admin membuka konfirmasi Terbitkan
    Then ringkasannya menyebut "1 field baru wajib — berlaku untuk pengajuan yang dibuat sesudah ini."
    And ringkasannya menyebut "2 draf berjalan tidak terpengaruh."
    And ringkasannya menyebut "Label "Judul" diubah pada field yang sudah dijawab 3 pengajuan."
    And ringkasannya menyebut "1 field dihapus — jawaban lamanya tetap tersimpan."

  @BR-FE-34 @BR-FE-11 @BR-FE-12 @M2 @ADMIN-08-06 @boundary
  Scenario: [FE-FORMPUB-03] Field wajib yang terbit berlaku sejak terbit, bukan sejak diketik
    Given peneliti "P-001" punya draf berjalan yang sudah menjawab field "Lokasi"
    And Admin menyimpan draf yang menambah field wajib "Mitra" dan menghapus field "Lokasi"
    When Admin menerbitkan draf itu sehari kemudian tanpa mencentang notifikasi
    Then draf "P-001" tetap bisa disubmit tanpa mengisi "Mitra"
    And jawaban "P-001" untuk "Lokasi" tetap terbaca
    And tidak ada notifikasi yang terkirim

  @BR-FE-34 @M2 @ADMIN-08-06 @happy
  Scenario: [FE-FORMPUB-04] Mengubah label field yang sudah dijawab memunculkan peringatan, bukan blokir
    Given field "Judul" sudah dijawab 3 pengajuan
    When Admin mengubah labelnya menjadi "Judul Penelitian" di dialog field
    Then dialog menampilkan "Hanya untuk memperbaiki ketikan. Kalau pertanyaannya berbeda, tambahkan field baru — jawaban lama tetap tercatat di field lama."
    And field tetap bisa disimpan ke draf
    When Admin mengembalikan labelnya menjadi "Judul"
    Then peringatan itu hilang
