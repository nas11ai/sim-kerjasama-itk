Feature: Deaktivasi User
  Deaktivasi menyetel `is_active = false` dan tidak menghapus apa pun — data user
  tetap utuh supaya jejak pengajuan lama tetap terbaca. Yang berubah adalah
  kemampuannya bertindak: akun nonaktif tidak bisa login dan tidak menerima
  penugasan baru, sementara penugasan yang sudah berjalan tidak ikut hilang.

  Penjagaan kepemilikan submission saat lead researcher dinonaktifkan ada di
  [SM-OWNER-03/04] (M1); penggantian reviewer yang terlanjur nonaktif ada di
  [REV-REASSIGN-01] (M2).

  @BR-IAM-05 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-DEACT-01] Deaktivasi menyisakan data user utuh
    Given "Sari" berstatus aktif
    When Operator menonaktifkan "Sari"
    Then "Sari" berstatus tidak aktif
    And data profil "Sari" tetap utuh

  @BR-IAM-05 @M3 @SHARED-01-01 @denial
  Scenario: [IAM-DEACT-02] User non-aktif tidak bisa login
    Given "Sari" berstatus tidak aktif
    When "Sari" login
    Then login ditolak karena akun tidak aktif

  @BR-IAM-05 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-DEACT-03] Pengajuan milik user nonaktif tetap terbaca
    Given submission "P-001" milik "Sari" berstatus APPROVED
    And "Sari" berstatus tidak aktif
    When Operator membuka detail "P-001"
    Then detail terbuka

  @BR-IAM-05 @M3 @OPERATOR-08-04 @denial
  Scenario: [IAM-DEACT-04] Reviewer nonaktif tidak menerima penugasan baru
    Given "Sari" berstatus reviewer_internal
    And "Sari" berstatus tidak aktif
    When Operator menugaskan "Sari" sebagai reviewer "P-001"
    Then penugasan ditolak karena reviewer tidak aktif

  @BR-IAM-19 @M3 @OPERATOR-08-03 @denial
  Scenario: [IAM-DEACT-05] User tidak bisa dihapus, hanya dinonaktifkan
    Given "Budi" adalah user aktif
    When Operator menghapus akun "Budi"
    Then penghapusan ditolak karena user hanya bisa dinonaktifkan
    And akun "Budi" tetap ada

  @BR-IAM-19 @BR-IAM-10 @M3 @OPERATOR-08-03 @denial
  Scenario: [IAM-DEACT-06] Jalur hapus tidak boleh menembus penjagaan lead researcher
    Given "Budi" adalah lead researcher pengajuan aktif "P-001"
    When Operator menghapus akun "Budi"
    Then penghapusan ditolak
    And "P-001" tetap punya pemilik

  @BR-IAM-19 @M3 @OPERATOR-08-01 @boundary
  Scenario: [IAM-DEACT-07] Tidak ada jalur hapus user di antarmuka mana pun
    Given Operator membuka detail user "Budi"
    Then tidak ada aksi menghapus akun yang ditawarkan
    And aksi yang tersedia adalah menonaktifkan

  @BR-IAM-19 @BR-IAM-05 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-DEACT-08] Deaktivasi adalah satu-satunya cara mencabut akses akun
    Given "Budi" adalah user aktif tanpa pengajuan berjalan
    When Operator menonaktifkan "Budi"
    Then "Budi" tidak bisa login
    And seluruh data "Budi" tetap utuh

  @BR-IAM-05 @M3 @OPERATOR-08-03 @denial
  Scenario: [IAM-DEACT-09] Sesi yang sedang login berakhir saat akun dinonaktifkan
    Given "Sari" sedang login
    When Operator menonaktifkan "Sari"
    Then permintaan berikutnya dari "Sari" ditolak dan ia keluar dari aplikasi
    And cookie "ingat saya" milik "Sari" tidak lagi membuatnya masuk

  @BR-IAM-05 @BR-REV-10 @M3 @OPERATOR-03-02 @denial
  Scenario: [IAM-DEACT-10] Reviewer yang akunnya nonaktif tidak bisa menjadi pengganti
    Given "Andi" adalah reviewer dalam masa tugas yang akunnya nonaktif
    When Operator mengganti reviewer "Sari" dengan "Andi" pada "P-001"
    Then penggantian ditolak karena akun reviewer nonaktif
    And penugasan "Sari" pada "P-001" tetap aktif

  @BR-IAM-05 @BR-REV-10 @M3 @OPERATOR-08-04 @boundary
  Scenario: [IAM-DEACT-11] Penugasan berjalan milik reviewer yang dinonaktifkan tetap ada sampai diganti
    Given "Sari" ditugaskan sebagai reviewer "P-001" dan sudah menyimpan draf evaluasi
    When Operator menonaktifkan "Sari"
    Then penugasan "Sari" pada "P-001" tetap aktif dan ditandai perlu diganti
    And draf evaluasinya tetap terbaca Operator

  @BR-IAM-05 @M3 @RESEARCHER-02-08 @denial
  Scenario: [IAM-DEACT-12] User nonaktif tidak bisa ditambahkan sebagai anggota penelitian
    Given "Joko" berstatus tidak aktif
    When peneliti menambahkan "Joko" sebagai anggota "P-001"
    Then penambahan ditolak karena akun "Joko" nonaktif

  @BR-IAM-20 @BR-IAM-15 @M3 @OPERATOR-08-03 @happy
  Scenario: [IAM-REACT-01] Akun yang diaktifkan kembali bisa login lagi dengan data utuh
    Given "Sari" berperan Operator dan akunnya nonaktif
    When Admin mengaktifkan kembali "Sari"
    Then "Sari" bisa login
    And peran dan profil "Sari" tidak berubah
    And "Sari" menerima notifikasi bahwa akunnya aktif kembali

  @BR-IAM-20 @BR-SM-09 @BR-REV-10 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-REACT-02] Pengaktifan tidak mengembalikan apa yang sudah diserahkan
    Given kepemilikan "P-001" sudah dialihkan dari "Budi" ke "Sari" sebelum "Budi" dinonaktifkan
    And penugasan "Budi" sebagai reviewer "P-002" sudah berstatus replaced
    When Admin mengaktifkan kembali "Budi"
    Then pemilik "P-001" tetap "Sari"
    And penugasan "Budi" pada "P-002" tetap berstatus replaced

  @BR-IAM-20 @BR-REV-09 @M3 @OPERATOR-08-04 @boundary
  Scenario: [IAM-REACT-03] Reviewer yang diaktifkan tetap terikat masa tugasnya
    Given masa tugas reviewer "Andi" sudah lewat dan akunnya nonaktif
    When Admin mengaktifkan kembali "Andi"
    And Operator menugaskan "Andi" sebagai reviewer "P-001"
    Then penugasan ditolak karena di luar masa tugas

  @BR-IAM-20 @M3 @OPERATOR-08-03 @denial
  Scenario: [IAM-REACT-04] Operator tanpa users.manage tidak bisa mengaktifkan kembali
    Given "Sari" berstatus tidak aktif
    When Operator pemegang users.verify saja mengaktifkan kembali "Sari"
    Then pengaktifan ditolak
    And akun "Sari" tetap nonaktif

  @BR-IAM-20 @M3 @OPERATOR-08-03 @boundary
  Scenario: [IAM-REACT-05] Mengaktifkan akun yang sudah aktif tidak mengubah apa pun
    Given "Budi" adalah user aktif
    When Admin mengaktifkan kembali "Budi"
    Then tidak ada yang berubah
    And tidak ada entri jejak audit maupun notifikasi baru
