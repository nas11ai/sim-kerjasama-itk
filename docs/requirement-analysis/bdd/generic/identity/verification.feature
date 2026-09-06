Feature: Verifikasi NIDN dan Kelengkapan Profil
  Dua gerbang berbeda yang sering dianggap satu: status akun (pending vs
  terverifikasi) dan kelengkapan profil. Verifikasi menggerbang komitmen, bukan
  persiapan — user pending tetap boleh menyiapkan draft.

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
