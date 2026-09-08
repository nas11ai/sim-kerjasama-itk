Feature: Luaran Penelitian Terealisasi
  Bedakan dari Target Luaran (BR-SM-14/15) yang merupakan luaran yang dijanjikan —
  taksonominya sama, momennya berbeda. Kecocokan tipe antara target dan realisasi
  sengaja tidak ditegakkan: penelitian memang menyimpang, dan memaksanya cocok
  mendorong peneliti berbohong di target atau menyembunyikan luaran nyata.

  Background:
    Given submission "P-001" milik "Budi" berstatus APPROVED

  @BR-RO-01 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-01] Luaran ditolak pada submission yang belum APPROVED
    Given submission "P-009" milik "Budi" berstatus UNDER_REVIEW
    When "Budi" menambahkan luaran bertipe "publication" ke "P-009"
    Then penambahan ditolak karena submission belum APPROVED

  @BR-RO-01 @M4 @RESEARCHER-06-02 @happy
  Scenario: [RO-OUTPUT-02] Submission APPROVED bisa menerima luaran
    When "Budi" menambahkan luaran bertipe "publication" ke "P-001"
    Then penambahan berhasil

  @BR-RO-02 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-03] Tipe pks ditolak di luar CommunityService
    Given submission "P-001" bertipe Research
    When "Budi" menambahkan luaran bertipe "pks" ke "P-001"
    Then penambahan ditolak karena tipe luaran tidak sesuai jenis pengajuan

  @BR-RO-02 @M4 @RESEARCHER-06-02 @happy
  Scenario: [RO-OUTPUT-04] Tipe pks diterima pada CommunityService
    Given submission "P-001" bertipe CommunityService
    When "Budi" menambahkan luaran bertipe "pks" ke "P-001"
    Then penambahan berhasil

  @BR-RO-03 @M4 @RESEARCHER-06-03 @boundary
  Scenario: [RO-OUTPUT-05] PKS yang masih berjalan boleh tanpa end_date
    Given submission "P-001" bertipe CommunityService
    When "Budi" menambahkan luaran bertipe "pks" ke "P-001" tanpa metadata.end_date
    Then penambahan berhasil

  @BR-RO-04 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-06] Tipe ip tanpa berkas ditolak
    When "Budi" menambahkan luaran bertipe "ip" ke "P-001" tanpa berkas
    Then penambahan ditolak karena luaran wajib punya minimal satu berkas

  @BR-RO-04 @M4 @RESEARCHER-06-02 @happy
  Scenario: [RO-OUTPUT-07] Tipe ip dengan satu berkas tersimpan
    When "Budi" menambahkan luaran bertipe "ip" ke "P-001" dengan satu berkas
    Then penambahan berhasil

  @BR-RO-05 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-08] Tipe di luar output_type_definitions ditolak
    When "Budi" menambahkan luaran bertipe "sertifikat-halal" ke "P-001"
    Then penambahan ditolak karena tipe luaran tidak terdaftar

  @BR-RO-05 @M4 @ADMIN-08-10 @happy
  Scenario: [RO-OUTPUT-09] Tipe baru yang terdaftar langsung bisa dipakai
    Given admin mendaftarkan "sertifikat-halal" di output_type_definitions
    When "Budi" menambahkan luaran bertipe "sertifikat-halal" ke "P-001"
    Then penambahan berhasil

  @BR-RO-06 @M4 @RESEARCHER-06-01 @boundary
  Scenario: [RO-OUTPUT-10] Luaran tetap ada sebagai data historis setelah penarikan
    Given "P-001" punya luaran bertipe "publication"
    And submission "P-001" berstatus WITHDRAWN
    When "Budi" membuka daftar luaran "P-001"
    Then luaran bertipe "publication" masih terbaca

  @BR-RO-07 @M4 @RESEARCHER-06-03 @happy
  Scenario: [RO-OUTPUT-11] submitted_by menambah dan menyunting luaran
    When "Budi" menyunting luaran "P-001"
    Then penyuntingan berhasil

  @BR-RO-07 @M4 @RESEARCHER-06-03 @happy
  Scenario: [RO-OUTPUT-12] Co-Investigator juga boleh menyunting luaran
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" menyunting luaran "P-001"
    Then penyuntingan berhasil

  @BR-RO-07 @M4 @RESEARCHER-06-01 @denial
  Scenario: [RO-OUTPUT-13] Member biasa hanya bisa membaca luaran
    Given "Andi" terdaftar sebagai member di "P-001"
    When "Andi" menyunting luaran "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-RO-08 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-14] Lewat batas Upload Luaran, penyuntingan ditolak
    Given waktu sekarang "2026-12-10 09:00:00"
    And detail "Upload Luaran" berbatas "Batas Akhir" pada "2026-12-01"
    And tidak ada FormSubmissionOverride berlaku untuk "P-001"
    When "Budi" menyunting luaran "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-RO-08 @M4 @RESEARCHER-06-01 @boundary
  Scenario: [RO-OUTPUT-15] Lewat batas, luaran lama tetap terbaca tanpa override
    Given waktu sekarang "2026-12-10 09:00:00"
    And detail "Upload Luaran" berbatas "Batas Akhir" pada "2026-12-01"
    And "P-001" punya luaran bertipe "publication"
    When "Budi" membuka daftar luaran "P-001"
    Then luaran bertipe "publication" masih terbaca

  @BR-RO-08 @M4 @OPERATOR-02-03 @happy
  Scenario: [RO-OUTPUT-16] Override yang berlaku membuka kembali penyuntingan luaran
    Given waktu sekarang "2026-12-10 09:00:00"
    And detail "Upload Luaran" berbatas "Batas Akhir" pada "2026-12-01"
    And Operator membuat override untuk "P-001" pada fase "Upload Luaran" yang berlaku sampai "2026-12-20 23:59:59"
    When "Budi" menyunting luaran "P-001"
    Then penyuntingan berhasil

  @BR-RO-04 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-17] Tipe prototype tanpa berkas ditolak
    When "Budi" menambahkan luaran bertipe "prototype" ke "P-001" tanpa berkas
    Then penambahan ditolak karena luaran wajib punya minimal satu berkas

  @BR-RO-04 @M4 @RESEARCHER-06-02 @happy
  Scenario: [RO-OUTPUT-18] Tipe prototype dengan satu berkas tersimpan
    When "Budi" menambahkan luaran bertipe "prototype" ke "P-001" dengan satu berkas
    Then penambahan berhasil

  @BR-RO-07 @M4 @RESEARCHER-06-01 @denial
  Scenario: [RO-OUTPUT-19] Reviewer hanya bisa membaca luaran
    Given "Sari" ditugaskan sebagai reviewer "P-001"
    When "Sari" menyunting luaran "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-RO-07 @M4 @OPERATOR-02-03 @boundary
  Scenario: [RO-OUTPUT-20] Operator pemegang outputs.manage boleh menyunting luaran
    Given "Rina" punya permission "outputs.manage"
    When "Rina" menyunting luaran "P-001"
    Then penyuntingan berhasil

  @BR-RO-09 @M4 @RESEARCHER-06-02 @denial
  Scenario: [RO-OUTPUT-21] Metadata wajib yang kosong ditolak
    When "Budi" menambahkan luaran bertipe "article" ke "P-001" tanpa mengisi "title"
    Then penambahan ditolak karena metadata "title" wajib diisi

  @BR-RO-09 @BR-RO-03 @M4 @RESEARCHER-06-03 @boundary
  Scenario: [RO-OUTPUT-22] Metadata yang tidak wajib boleh kosong
    Given submission "P-001" bertipe CommunityService
    When "Budi" menambahkan luaran bertipe "pks" ke "P-001" tanpa mengisi "end_date"
    Then penambahan berhasil
