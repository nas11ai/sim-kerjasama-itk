Feature: Pustaka Form Evaluasi
  Form Evaluasi adalah pustaka: satu lembar penilaian dipasang ke banyak langkah yang
  direview, di banyak tahap (BR-FE-30). Isinya disunting sebagai draf dan baru berlaku bagi
  reviewer sesudah diterbitkan menjadi versi bernomor (BR-FE-31). Tanpa draf, setiap ketikan
  Admin langsung mengubah lembar yang sedang diisi reviewer; tanpa syarat terbit, sebuah
  langkah bisa memasang lembar yang belum punya satu field pun.

  Background:
    Given tahap "Penelitian DIPA" dan tahap "Pengmas DIPA" masing-masing punya langkah Pengajuan yang direview

  @BR-FE-30 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-EVLIB-01] Form Evaluasi yang belum pernah terbit tidak bisa dipasang
    Given Form Evaluasi "Lembar Proposal" baru dibuat dan belum pernah diterbitkan
    When Admin memasang "Lembar Proposal" di langkah Pengajuan "Penelitian DIPA"
    Then pemasangan ditolak dengan pesan "Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang."
    And langkah itu tidak memasang Form Evaluasi apa pun

  @BR-FE-30 @M2 @ADMIN-08-09 @happy
  Scenario: [FE-EVLIB-02] Satu Form Evaluasi dipasang di dua tahap
    Given Form Evaluasi "Lembar Proposal" sudah terbit Versi 1
    When Admin memasang "Lembar Proposal" di langkah Pengajuan "Penelitian DIPA" sebagai wajib
    And Admin memasang "Lembar Proposal" di langkah Pengajuan "Pengmas DIPA" sebagai tidak wajib
    Then kedua langkah memasang "Lembar Proposal" Versi 1
    And sifat wajibnya berbeda per langkah
    And memasang "Lembar Proposal" sekali lagi di langkah "Penelitian DIPA" ditolak dengan pesan "Form evaluasi ini sudah terpasang di langkah ini."

  @BR-FE-31 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-EVLIB-03] Draf tidak berlaku sebelum terbit
    Given "Lembar Proposal" Versi 1 terpasang di langkah Pengajuan "Penelitian DIPA"
    And "Sari" ditugaskan menilai "P-001" di langkah itu
    When Admin menyimpan draf "Lembar Proposal" yang menambah field "Kebaruan"
    Then "Sari" tetap melihat field "Lembar Proposal" Versi 1 tanpa "Kebaruan"
    And pustaka menandai "Lembar Proposal" punya draf yang belum diterbitkan

  @BR-FE-31 @M2 @ADMIN-08-09 @happy
  Scenario: [FE-EVLIB-04] Terbitkan membentuk versi berikutnya dan menghapus draf
    Given "Lembar Proposal" Versi 1 punya draf yang menambah field "Kebaruan"
    When Admin menerbitkan draf itu
    Then "Lembar Proposal" punya Versi 2 yang memuat "Kebaruan"
    And Versi 1 tidak berubah
    And draf "Lembar Proposal" tidak ada lagi
    And menerbitkan sekali lagi tanpa draf ditolak dengan pesan "Tidak ada draf untuk diterbitkan."

  @BR-FE-30 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-EVLIB-05] Melepas Form Evaluasi yang sudah ditugaskan hanya berlaku untuk versi struktur berikutnya
    Given "Lembar Proposal" terpasang di langkah Pengajuan "Penelitian DIPA" dan "Pengmas DIPA"
    And "Sari" ditugaskan menilai "P-001" dengan "Lembar Proposal" di langkah "Penelitian DIPA"
    When Admin melepas "Lembar Proposal" dari langkah "Penelitian DIPA"
    Then versi struktur berikutnya "Penelitian DIPA" tidak memasang "Lembar Proposal"
    And penugasan "Sari" atas "P-001" tetap ada di versi lama
    And menonaktifkan "Lembar Proposal" di "Pengmas DIPA" hanya berlaku di versi berikutnya tahap itu