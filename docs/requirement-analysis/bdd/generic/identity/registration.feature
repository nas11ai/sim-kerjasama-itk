Feature: Registrasi Akun
  Hanya ada dua cara akun lahir: self-register untuk dosen ITK, dan undangan untuk
  pihak eksternal. Keduanya berbeda bukan cuma di pintu masuknya, melainkan di
  bekal yang dibawa akun barunya — role, organisasi, permission, dan status awal
  (BR-IAM-12).

  Pembatasan domain @itk.ac.id berlaku pada jalur mandiri saja: memasangnya di
  lapis yang salah membuat mitra eksternal tidak pernah bisa masuk. Sebaliknya,
  jalur undangan tidak pernah meminta NIDN — itulah sebabnya keunikan NIDN hanya
  berlaku atas nilai yang terisi.

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
    Then akun "Rina" terbentuk berstatus aktif

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
    Then akun "Rina" terbentuk berstatus aktif
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

  @BR-IAM-12 @M3 @SHARED-01-02 @happy
  Scenario: [IAM-REG-12] Self-register membekali akun dengan role researcher dan organisasi pilihannya
    Given "Budi" memilih organisasi "Teknik Mesin" di OrgTreePicker
    When "Budi" mendaftar mandiri dengan email "budi@itk.ac.id"
    Then akun "Budi" terbentuk berstatus pending
    And "Budi" berperan "researcher"
    And "Budi" berorganisasi "Teknik Mesin"

  @BR-IAM-12 @M3 @SHARED-01-03 @happy
  Scenario: [IAM-REG-13] Undangan membekali akun dengan permission yang tertera di token
    Given token undangan sah milik organisasi "Mitra Industri"
    And token undangan itu memberi permission "submissions.create"
    When "Rina" mendaftar lewat token itu
    Then akun "Rina" terbentuk berstatus aktif
    And "Rina" punya permission "submissions.create"

  @BR-IAM-12 @M3 @OPERATOR-08-02 @boundary
  Scenario: [IAM-REG-14] Pendaftar lewat undangan tidak masuk antrian verifikasi NIDN
    Given token undangan sah milik organisasi "Mitra Industri"
    When "Rina" mendaftar lewat token itu
    Then akun "Rina" terbentuk berstatus aktif
    And "Rina" tidak muncul di antrian verifikasi NIDN

  @BR-IAM-02 @M3 @SHARED-01-03 @boundary
  Scenario: [IAM-REG-15] Dua akun undangan sama-sama tanpa NIDN dan tidak bentrok
    Given token undangan dengan max_uses 3 dan used_count 0
    And "Rina" mendaftar lewat token itu
    When "Doni" mendaftar lewat token itu
    Then akun "Doni" terbentuk berstatus aktif
    And keduanya terdaftar tanpa NIDN

  @BR-IAM-06 @M3 @ADMIN-08-01 @denial
  Scenario: [IAM-REG-16] Token yang dicabut Operator ditolak
    Given token undangan sah milik organisasi "Mitra Industri" dengan used_count 0
    And token undangan itu sudah dicabut Operator
    When "Rina" mendaftar lewat token itu
    Then pendaftaran ditolak karena token tidak berlaku

  @BR-IAM-06 @BR-IAM-14 @M3 @SHARED-01-04 @denial
  Scenario: [IAM-REG-17] Token milik organisasi nonaktif ditolak
    Given organisasi "Mitra Industri" berstatus nonaktif
    And token undangan sah milik organisasi "Mitra Industri"
    When "Rina" mendaftar lewat token itu
    Then pendaftaran ditolak karena token tidak berlaku

  @BR-IAM-01 @M3 @SHARED-01-02 @denial
  Scenario Outline: [IAM-REG-18] Domain yang hanya menyerupai @itk.ac.id ditolak
    When "Budi" mendaftar mandiri dengan email "<email>"
    Then pendaftaran ditolak karena domain email tidak diizinkan

    Examples:
      | email                      |
      | budi@itk.ac.id.example.com |
      | budi@student.itk.ac.id     |
      | budi@notitk.ac.id          |
      | budi@itk.ac.idx            |

  @BR-IAM-01 @M3 @SHARED-01-02 @boundary
  Scenario: [IAM-REG-19] Domain yang ditulis huruf besar tetap diterima
    When "Budi" mendaftar mandiri dengan email "Budi@ITK.AC.ID"
    Then akun "Budi" terbentuk berstatus pending
