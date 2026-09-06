Feature: Penghapusan Organisasi
  Kata "subtree" di BR-IAM-08 bukan hiasan: implementasi yang hanya memeriksa node
  itu sendiri akan menghapus fakultas beserta seluruh prodi yang masih berisi orang.
  Arah penelusuran di sini adalah keturunan — berbeda dari BR-FE-02 yang leluhur
  (ADR 0013).

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
