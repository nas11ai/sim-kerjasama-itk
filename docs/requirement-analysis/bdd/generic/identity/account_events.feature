Feature: Pemberitahuan Perubahan Keadaan Akun
  Keputusan yang mengubah keadaan akun seseorang memberi tahu pihak yang harus
  bertindak atasnya (BR-IAM-15). Pendaftaran baru memberi tahu Operator, karena
  dialah yang harus memverifikasi; verifikasi, penolakan, deaktivasi, dan
  penunjukan reviewer memberi tahu user yang bersangkutan, karena kemampuannya
  berubah tanpa ia melakukan apa-apa.

  Yang user lakukan sendiri tidak memberitahunya kembali — [IAM-EVENT-05] menjaga
  batas itu, sebab notifikasi yang berisik lebih cepat diabaikan daripada dibaca.

  Penolakan verifikasi tidak diulang di sini: alasannya bagian dari aturannya
  sendiri, dan dibuktikan [IAM-VERIFY-07]. Channel tiap event adalah konfigurasi
  (BR-NOT-06), dibuktikan [NOT-CHANNEL-03] di M5.

  @BR-IAM-15 @M3 @OPERATOR-08-02 @happy
  Scenario: [IAM-EVENT-01] Pendaftaran baru memberi tahu Operator
    When "Budi" mendaftar mandiri dengan email "budi@itk.ac.id"
    Then Operator menerima notifikasi in-app

  @BR-IAM-15 @M3 @SHARED-02-05 @happy
  Scenario: [IAM-EVENT-02] Verifikasi memberi tahu pendaftar
    Given "Budi" berstatus pending
    When Operator memverifikasi NIDN "Budi"
    Then "Budi" menerima notifikasi in-app

  @BR-IAM-15 @M3 @SHARED-02-05 @happy
  Scenario: [IAM-EVENT-03] Deaktivasi memberi tahu user yang dinonaktifkan
    Given "Sari" berstatus aktif
    When Operator menonaktifkan "Sari"
    Then "Sari" menerima notifikasi in-app

  @BR-IAM-15 @M3 @SHARED-02-05 @happy
  Scenario: [IAM-EVENT-04] Penunjukan reviewer memberi tahu yang ditunjuk
    Given "Sari" sudah terdaftar dan berorganisasi "Teknik Informatika"
    When Operator menunjuk "Sari" sebagai reviewer internal
    Then "Sari" menerima notifikasi in-app

  @BR-IAM-15 @M3 @SHARED-01-06 @boundary
  Scenario: [IAM-EVENT-05] Perubahan yang dilakukan user sendiri tidak memberitahunya
    Given "Budi" sudah terverifikasi
    And profil "Budi" belum lengkap
    When "Budi" melengkapi profilnya
    Then "Budi" tidak menerima notifikasi in-app
