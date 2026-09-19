Feature: Penerbitan dan Pencabutan Undangan
  Undangan adalah satu-satunya jalan masuk mitra eksternal, dan satu-satunya
  kredensial di sistem ini yang bisa berpindah tangan tanpa sepengetahuan
  penerbitnya. Karena itu ia punya dua sisi yang sama pentingnya: wewenang
  menerbitkan, dan wewenang mematikan.

  BR-IAM-06 menyebut pencabutan sebagai sebab keempat sebuah token tidak
  berlaku. Tanpa jalurnya, tautan yang bocor hanya bisa ditunggu kedaluwarsa.

  @BR-IAM-16 @M3 @ADMIN-08-01 @happy
  Scenario: [IAM-INVITE-01] Operator menerbitkan undangan untuk sebuah organisasi
    Given Operator memegang permission "users.manage"
    And organisasi "Mitra Industri" aktif
    When Operator menerbitkan undangan untuk "Mitra Industri" dengan kuota 5 dan berlaku 30 hari
    Then undangan tersimpan dengan organisasi tujuan "Mitra Industri"
    And token undangan itu berlaku

  @BR-IAM-16 @M3 @ADMIN-08-01 @denial
  Scenario: [IAM-INVITE-02] Peneliti biasa tidak bisa menerbitkan undangan
    Given "Budi" hanya memegang role "researcher"
    When "Budi" menerbitkan undangan untuk "Mitra Industri"
    Then penerbitan ditolak karena bukan wewenangnya

  @BR-IAM-16 @M3 @ADMIN-08-01 @happy
  Scenario: [IAM-INVITE-03] Operator mencabut undangan yang kuotanya masih tersisa
    Given undangan untuk "Mitra Industri" berkuota 10 dan baru dipakai 1 kali
    When Operator mencabut undangan itu
    Then undangan itu berhenti berlaku
    And sembilan pemakaian yang tersisa tidak bisa dipakai lagi

  @BR-IAM-16 @BR-IAM-06 @M3 @ADMIN-08-01 @boundary
  Scenario: [IAM-INVITE-04] Pencabutan tidak menyentuh akun yang terlanjur lahir
    Given undangan untuk "Mitra Industri" sudah dipakai "Rina" untuk mendaftar
    When Operator mencabut undangan itu
    Then akun "Rina" tetap aktif
    And organisasi dan permission "Rina" tidak berubah

  @BR-IAM-16 @M3 @ADMIN-08-01 @denial
  Scenario: [IAM-INVITE-05] Peneliti biasa tidak bisa mencabut undangan
    Given undangan untuk "Mitra Industri" masih berlaku
    And "Budi" hanya memegang role "researcher"
    When "Budi" mencabut undangan itu
    Then pencabutan ditolak karena bukan wewenangnya

  @BR-IAM-16 @M3 @ADMIN-08-01 @boundary
  Scenario: [IAM-INVITE-06] Undangan yang dicabut tetap terbaca sebagai riwayat
    Given undangan untuk "Mitra Industri" sudah dicabut Operator
    When Operator membuka daftar undangan
    Then undangan itu tetap muncul dengan penanda dicabut
    And jumlah pemakaiannya tetap terbaca

  @BR-IAM-06 @M3 @SHARED-01-03 @happy
  Scenario: [IAM-INVITE-07] Tautan undangan yang sah menampilkan form registrasi
    Given undangan untuk "Mitra Industri" masih berlaku
    When calon pendaftar membuka tautan undangan itu
    Then form registrasi undangan ditampilkan
    And organisasi tujuan "Mitra Industri" terbaca di form itu

  @BR-IAM-06 @M3 @SHARED-01-04 @denial
  Scenario Outline: [IAM-INVITE-08] Tautan undangan tidak berlaku mendarat di layar penolakan
    Given undangan untuk "Mitra Industri" <keadaan>
    When calon pendaftar membuka tautan undangan itu
    Then layar token tidak berlaku ditampilkan
    And form registrasi tidak ditampilkan

    Examples:
      | keadaan                     |
      | sudah habis kuotanya        |
      | sudah lewat masa berlakunya |
      | sudah dicabut Operator      |
      | organisasinya sudah nonaktif |
