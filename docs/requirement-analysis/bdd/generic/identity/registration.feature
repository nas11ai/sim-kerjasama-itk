Feature: Registrasi Akun
  Hanya ada dua cara akun lahir: self-register untuk dosen ITK, dan undangan untuk
  pihak eksternal. Pembatasan domain @itk.ac.id berlaku pada jalur mandiri saja —
  memasangnya di lapis yang salah membuat mitra eksternal tidak pernah bisa masuk.

  @BR-IAM-01 @M3 @SHARED-01-02 @happy
  Scenario: [IAM-REG-01] Dosen ITK mendaftar mandiri
    When "Budi" mendaftar mandiri dengan email "budi@itk.ac.id"
    Then akun "Budi" terbentuk berstatus pending

  @BR-IAM-01 @M3 @SHARED-01-02 @denial
  Scenario: [IAM-REG-02] Email di luar domain ITK ditolak pada jalur mandiri
    When "Budi" mendaftar mandiri dengan email "budi@gmail.com"
    Then pendaftaran ditolak karena domain email tidak diizinkan

  @BR-IAM-01 @BR-IAM-06 @M3 @SHARED-01-03 @boundary
  Scenario: [IAM-REG-03] Jalur undangan tidak dibatasi domain
    Given token undangan sah milik organisasi "Mitra Industri"
    When "Rina" mendaftar lewat token itu dengan email "rina@perusahaan.co.id"
    Then akun "Rina" terbentuk berstatus pending

  @BR-IAM-02 @M3 @SHARED-01-02 @denial
  Scenario: [IAM-REG-04] NIDN yang sudah dipakai ditolak
    Given "Sari" terdaftar dengan NIDN "0011223344"
    When "Budi" mendaftar mandiri dengan NIDN "0011223344"
    Then pendaftaran ditolak karena NIDN sudah dipakai

  @BR-IAM-02 @M3 @OPERATOR-08-02 @happy
  Scenario: [IAM-REG-05] NIDN unik masuk antrian verifikasi
    When "Budi" mendaftar mandiri dengan NIDN "0055667788"
    Then akun "Budi" terbentuk berstatus pending
    And "Budi" muncul di antrian verifikasi NIDN

  @BR-IAM-06 @M3 @SHARED-01-04 @denial
  Scenario: [IAM-REG-06] Token yang sudah habis kuotanya ditolak
    Given token undangan dengan max_uses 3 dan used_count 3
    When "Rina" mendaftar lewat token itu
    Then pendaftaran ditolak karena token tidak berlaku

  @BR-IAM-06 @M3 @SHARED-01-04 @denial
  Scenario: [IAM-REG-07] Token kedaluwarsa ditolak
    Given waktu sekarang "2026-03-10 09:00:00"
    And token undangan yang expires_at "2026-03-01 00:00:00"
    When "Rina" mendaftar lewat token itu
    Then pendaftaran ditolak karena token tidak berlaku

  @BR-IAM-06 @M3 @SHARED-01-03 @happy
  Scenario: [IAM-REG-08] Token sah membuat user di organisasi token itu
    Given token undangan sah milik organisasi "Mitra Industri" dengan used_count 0
    When "Rina" mendaftar lewat token itu
    Then akun "Rina" terbentuk berstatus pending
    And "Rina" berorganisasi "Mitra Industri"
    And used_count token itu menjadi 1

  @BR-IAM-06 @M3 @ADMIN-08-01 @boundary
  Scenario: [IAM-REG-09] Satu token bisa dipakai beberapa user sampai kuotanya habis
    Given token undangan dengan max_uses 3 dan used_count 0
    When tiga orang mendaftar lewat token itu
    Then ketiganya terbentuk
    And used_count token itu menjadi 3

  @BR-IAM-06 @BR-IAM-08 @M3 @ADMIN-08-02 @boundary
  Scenario: [IAM-REG-10] Token belum terpakai tidak menghalangi penghapusan organisasi
    Given organisasi "Mitra Industri" tanpa UserProfile di seluruh subtree-nya
    And ada 2 token undangan belum terpakai milik "Mitra Industri"
    When admin menghapus organisasi "Mitra Industri"
    Then penghapusan berhasil
    And admin diberi tahu 2 token undangan ikut tercabut

  @BR-IAM-06 @M3 @SHARED-01-04 @denial
  Scenario: [IAM-REG-11] Token milik organisasi yang sudah dihapus ditolak
    Given token undangan milik organisasi yang sudah dihapus
    When "Rina" mendaftar lewat token itu
    Then pendaftaran ditolak karena token tidak berlaku
