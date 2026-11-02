Feature: Verifikasi NIDN dan Kelengkapan Profil
  Tiga gerbang berbeda yang sering dianggap satu: status verifikasi akun
  (`pending` · `aktif` · `rejected`), keaktifan akun (`is_active`), dan
  kelengkapan profil. Verifikasi menggerbang komitmen, bukan persiapan — user
  pending tetap boleh menyiapkan dan menyunting draft.

  Penolakan verifikasi sengaja tidak terminal (BR-IAM-13). Email dan NIDN yang
  terlanjur terpakai membuat pendaftaran ulang mustahil, jadi penolakan permanen
  berarti mematikan akun gara-gara satu digit salah ketik.

  @BR-IAM-04 @M3 @SHARED-01-05 @boundary
  Scenario: [IAM-VERIFY-01] User pending tetap bisa login
    Given "Budi" berstatus pending
    When "Budi" login
    Then "Budi" melihat layar menunggu verifikasi

  @BR-IAM-04 @M3 @RESEARCHER-02-10 @denial
  Scenario: [IAM-VERIFY-02] User pending tidak bisa submit
    Given "Budi" berstatus pending
    And submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then submit ditolak karena akun belum terverifikasi

  @BR-IAM-04 @M3 @OPERATOR-08-02 @happy
  Scenario: [IAM-VERIFY-03] Verifikasi NIDN membuka kemampuan submit
    Given "Budi" berstatus pending
    And submission "P-001" milik "Budi" berstatus DRAFT
    When Operator memverifikasi NIDN "Budi"
    And "Budi" submit "P-001"
    Then submit berhasil

  @BR-IAM-03 @M3 @SHARED-01-06 @denial
  Scenario: [IAM-VERIFY-04] Profil belum lengkap memblokir pembuatan pengajuan
    Given "Budi" sudah terverifikasi
    And profil "Budi" belum lengkap
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then penambahan ditolak karena profil belum lengkap

  @BR-IAM-03 @M3 @SHARED-01-06 @happy
  Scenario: [IAM-VERIFY-05] Profil lengkap membuka pembuatan pengajuan
    Given "Budi" sudah terverifikasi
    And profil "Budi" sudah lengkap
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1

  @BR-IAM-04 @M3 @RESEARCHER-02-02 @happy
  Scenario: [IAM-VERIFY-06] User pending tetap boleh menyiapkan draft
    Given "Budi" berstatus pending
    And profil "Budi" sudah lengkap
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1

  @BR-IAM-13 @BR-IAM-15 @M3 @OPERATOR-08-02 @denial
  Scenario: [IAM-VERIFY-07] Operator menolak verifikasi NIDN dengan alasan tertulis
    Given "Budi" berstatus pending
    When Operator menolak verifikasi NIDN "Budi" dengan alasan tertulis
    Then akun "Budi" berstatus rejected
    And "Budi" menerima notifikasi berisi alasan penolakan

  @BR-IAM-13 @M3 @OPERATOR-08-02 @denial
  Scenario: [IAM-VERIFY-08] Penolakan tanpa alasan ditolak
    Given "Budi" berstatus pending
    When Operator menolak verifikasi NIDN "Budi" tanpa alasan
    Then penolakan ditolak karena alasan wajib diisi
    And akun "Budi" berstatus pending

  @BR-IAM-13 @M3 @RESEARCHER-02-10 @denial
  Scenario: [IAM-VERIFY-09] Akun yang verifikasinya ditolak tidak bisa submit
    Given "Budi" berstatus rejected
    And submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then submit ditolak karena akun belum terverifikasi

  @BR-IAM-13 @M3 @OPERATOR-08-02 @boundary
  Scenario: [IAM-VERIFY-10] Akun yang ditolak masih bisa diverifikasi setelah NIDN dikoreksi
    Given "Budi" berstatus rejected
    And Operator sudah mengoreksi NIDN "Budi"
    When Operator memverifikasi NIDN "Budi"
    Then akun "Budi" berstatus aktif

  @BR-IAM-04 @M3 @RESEARCHER-02-02 @happy
  Scenario: [IAM-VERIFY-11] User pending tetap boleh menyunting draft yang sudah ada
    Given "Budi" berstatus pending
    And submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil

  @BR-IAM-03 @M3 @SHARED-01-06 @denial
  Scenario: [IAM-VERIFY-12] Profil yang terisi sebagian tetap dihitung belum lengkap
    Given "Budi" sudah terverifikasi
    And profil "Budi" terisi nomor telepon tapi tanpa bidang keahlian
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then penambahan ditolak karena profil belum lengkap

  @BR-IAM-13 @BR-IAM-02 @M3 @OPERATOR-08-02 @happy
  Scenario: [IAM-VERIFY-13] Operator mengoreksi NIDN akun yang ditolak
    Given "Budi" berstatus rejected dengan NIDN "0543210987"
    When Operator mengoreksi NIDN "Budi" menjadi "0543210978"
    Then NIDN "Budi" menjadi "0543210978"
    And akun "Budi" tetap berstatus rejected
    And jejak audit memuat NIDN lama dan NIDN baru

  @BR-IAM-02 @M3 @OPERATOR-08-02 @denial
  Scenario: [IAM-VERIFY-14] Koreksi ke NIDN yang sudah dipakai akun lain ditolak
    Given "Budi" berstatus rejected dengan NIDN "0543210987"
    And akun "Sari" memakai NIDN "0654321098"
    When Operator mengoreksi NIDN "Budi" menjadi "0654321098"
    Then koreksi ditolak karena NIDN sudah terdaftar
    And NIDN "Budi" tetap "0543210987"
