Feature: Penghapusan dan Deaktivasi Organisasi
  Dua operasi dengan ambang berbeda, dan menyamakannya adalah kesalahan yang mahal.
  **Penghapusan** (BR-IAM-08) ditolak selama masih ada UserProfile apa pun di
  subtree — aktif maupun tidak. **Deaktivasi** (BR-IAM-14) adalah operasi
  sehari-harinya: ditolak selama masih ada user **aktif**, dan tidak menghapus
  apa pun.

  Kata "subtree" di BR-IAM-08 bukan hiasan: implementasi yang hanya memeriksa node
  itu sendiri akan menghapus fakultas beserta seluruh prodi yang masih berisi orang.
  Arah penelusuran di sini adalah keturunan — berbeda dari BR-FE-02 yang leluhur
  (ADR 0013). Keduanya bertemu di [IAM-ORG-08]: unit nonaktif memutus rantai leluhur
  yang dipakai access control.

  @BR-IAM-08 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-01] Organisasi berisi user tidak bisa dihapus
    Given organisasi "Teknik Informatika" punya UserProfile di dalamnya
    When admin menghapus organisasi "Teknik Informatika"
    Then penghapusan ditolak karena masih ada user di organisasi itu

  @BR-IAM-08 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-02] User di organisasi turunan ikut menghalangi penghapusan
    Given organisasi "Fakultas Teknik" tidak punya UserProfile langsung
    And organisasi turunannya "Teknik Informatika" punya UserProfile
    When admin menghapus organisasi "Fakultas Teknik"
    Then penghapusan ditolak karena masih ada user di organisasi itu

  @BR-IAM-08 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-03] Organisasi kosong di seluruh subtree bisa dihapus
    Given organisasi "Mitra Industri" tanpa UserProfile di seluruh subtree-nya
    When admin menghapus organisasi "Mitra Industri"
    Then penghapusan berhasil

  @BR-IAM-14 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-04] Unit dengan user aktif tidak bisa dinonaktifkan
    Given organisasi "Teknik Informatika" punya user aktif di dalamnya
    When admin menonaktifkan organisasi "Teknik Informatika"
    Then deaktivasi ditolak karena masih ada user aktif di organisasi itu

  @BR-IAM-14 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-05] User aktif di unit turunan ikut menghalangi deaktivasi
    Given organisasi "Fakultas Teknik" tidak punya user aktif langsung
    And organisasi turunannya "Teknik Informatika" punya user aktif
    When admin menonaktifkan organisasi "Fakultas Teknik"
    Then deaktivasi ditolak karena masih ada user aktif di organisasi itu

  @BR-IAM-14 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-06] Unit yang seluruh user-nya sudah nonaktif bisa dinonaktifkan
    Given organisasi "Teknik Informatika" punya UserProfile yang seluruh user-nya tidak aktif
    When admin menonaktifkan organisasi "Teknik Informatika"
    Then deaktivasi berhasil

  @BR-IAM-14 @M3 @ADMIN-08-02 @boundary
  Scenario: [IAM-ORG-07] Deaktivasi organisasi tidak menghapus apa pun
    Given organisasi "Teknik Informatika" punya UserProfile yang seluruh user-nya tidak aktif
    When admin menonaktifkan organisasi "Teknik Informatika"
    Then deaktivasi berhasil
    And UserProfile di organisasi itu tetap utuh

  @BR-IAM-14 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-08] Organisasi nonaktif memutus rantai leluhur access control
    Given Organization "ITK" membawahi "FTI", dan "FTI" membawahi "Informatika"
    And Form "Pengajuan Penelitian" punya FormAccessControl permission "submissions.create" pada organisasi "ITK"
    And "Sari" berorganisasi "Informatika" dan punya permission "submissions.create"
    And organisasi "FTI" berstatus nonaktif
    When "Sari" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-IAM-17 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-09] Operator membuat unit induk baru
    Given Operator memegang permission "users.manage"
    When Operator membuat unit "Pusat Studi Energi" tanpa induk
    Then unit itu tersimpan sebagai akar pohon organisasi

  @BR-IAM-17 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-10] Operator membuat sub-unit di bawah unit yang ada
    Given unit "Fakultas Teknologi Industri" sudah ada
    When Operator membuat unit "Teknik Mesin" di bawahnya
    Then "Teknik Mesin" terbaca sebagai keturunan "Fakultas Teknologi Industri"

  @BR-IAM-17 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-11] Mengganti nama unit tidak memutus keanggotaan
    Given unit "Teknik Informatika" berisi 14 user
    When Operator mengganti namanya menjadi "Informatika"
    Then keempat belas user itu tetap berada di unit yang sama

  @BR-IAM-17 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-12] Operator memindahkan unit ke induk lain
    Given unit "Teknik Kimia" berada di bawah "Fakultas Sains"
    When Operator memindahkannya ke bawah "Fakultas Teknologi Industri"
    Then rantai leluhur "Teknik Kimia" berubah mengikuti induk barunya

  @BR-IAM-17 @BR-FE-02 @M3 @ADMIN-08-02 @denial
  Scenario: [IAM-ORG-13] Unit tidak bisa dipindah ke bawah keturunannya sendiri
    Given unit "Teknik Mesin" berada di bawah "Fakultas Teknologi Industri"
    When Operator memindahkan "Fakultas Teknologi Industri" ke bawah "Teknik Mesin"
    Then pemindahan ditolak karena akan membuat pohon organisasi bersiklus

  @BR-IAM-18 @M3 @ADMIN-08-02 @happy
  Scenario: [IAM-ORG-14] Operator memindahkan user antar unit
    Given "Budi" terdaftar di unit "Teknik Kimia"
    When Operator memindahkan "Budi" ke unit "Teknik Mesin"
    Then "Budi" terbaca sebagai anggota "Teknik Mesin"
    And pengajuan lama "Budi" tetap terbaca

  @BR-IAM-18 @BR-IAM-14 @M3 @ADMIN-08-02 @boundary
  Scenario: [IAM-ORG-15] Memindahkan user membuka jalan deaktivasi unit
    Given unit "Teknik Kimia" masih berisi 1 user aktif
    And deaktivasi unit itu sebelumnya ditolak
    When Operator memindahkan user itu ke unit lain
    And Operator menonaktifkan "Teknik Kimia"
    Then deaktivasi berhasil
