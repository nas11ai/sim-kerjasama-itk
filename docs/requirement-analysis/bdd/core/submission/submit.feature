Feature: Submit Pengajuan
  Satu researcher hanya boleh punya satu pengajuan aktif per periode per skema, dan
  hanya submitted_by yang boleh menekan submit. Draft yang gagal disubmit tidak
  pernah hilang.

  Background:
    Given periode berjalan aktif dan belum force closed
    And skema "DIPA Internal" dan skema "DIPA Eksternal" tersedia

  @BR-SM-01 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-01] Submit pertama ke sebuah skema berhasil
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-01 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-SUBMIT-02] Submit kedua ke skema yang sama ditolak
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then submit ditolak karena sudah ada pengajuan aktif pada skema itu

  @BR-SM-01 @M1 @RESEARCHER-02-01 @boundary
  Scenario: [SM-SUBMIT-03] Dua draft ke skema berbeda boleh hidup bersamaan
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And submission "P-002" milik "Budi" berskema "DIPA Eksternal" berstatus DRAFT
    When "Budi" membuka daftar pengajuannya
    Then "P-001" dan "P-002" sama-sama terbaca
    And tidak ada penolakan karena pengajuan aktif

  @BR-SM-01 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-04] Submit ke skema lain tetap boleh
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Eksternal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then status "P-002" menjadi SUBMITTED

  @BR-SM-01 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-SUBMIT-05] Submit ulang ke skema yang sama boleh setelah ditolak
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus REJECTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then status "P-002" menjadi SUBMITTED

  @BR-SM-01 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-SUBMIT-06] Pengajuan tanpa skema tidak saling menghalangi
    Given submission "P-001" milik "Budi" tanpa skema berstatus SUBMITTED
    And submission "P-002" milik "Budi" tanpa skema berstatus DRAFT
    When "Budi" submit "P-002"
    Then status "P-002" menjadi SUBMITTED

  @BR-SM-02 @M1 @RESEARCHER-02-09 @denial
  Scenario: [SM-SUBMIT-07] Submit tanpa proposal PDF ditolak
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And "Budi" belum mengunggah proposal PDF
    When "Budi" submit "P-001"
    Then submit ditolak karena proposal PDF belum diunggah
    And "Budi" melihat error per field pada "Proposal PDF"

  @BR-SM-02 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-08] Submit dengan proposal PDF lolos
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And "Budi" sudah mengunggah proposal PDF
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-04 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-09] Ketua pengaju submit pengajuannya sendiri
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-04 @M1 @RESEARCHER-02-03 @denial
  Scenario: [SM-SUBMIT-10] Co-investigator tidak bisa submit
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" submit "P-001"
    Then submit ditolak karena bukan submitted_by
    And status "P-001" tetap DRAFT

  @BR-SM-04 @M1 @RESEARCHER-02-03 @denial
  Scenario: [SM-SUBMIT-11] Anggota biasa tidak bisa submit
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And "Andi" terdaftar sebagai member di "P-001"
    When "Andi" submit "P-001"
    Then submit ditolak karena bukan submitted_by
    And status "P-001" tetap DRAFT

  @BR-SM-04 @M1 @RESEARCHER-02-05 @happy
  Scenario: [SM-SUBMIT-12] Ketua pengaju resubmit dari NEEDS_REVISION
    Given submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" resubmit "P-001"
    Then status "P-001" menjadi RESUBMITTED

  @BR-SM-04 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-SUBMIT-13] Super Admin bukan pemilik tidak bisa submit
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And "Siti" berperan Super Admin dan bukan submitted_by "P-001"
    When "Siti" submit "P-001"
    Then submit ditolak karena bukan submitted_by
    And status "P-001" tetap DRAFT

  @BR-SM-04 @M1 @OPERATOR-02-02 @happy
  Scenario: [SM-SUBMIT-14] Super Admin tetap bisa melihat, menyunting, dan menarik
    Given submission "P-001" milik "Budi" berstatus APPROVED
    And "Siti" berperan Super Admin dan bukan submitted_by "P-001"
    When "Siti" membuka detail "P-001"
    Then detail terbuka
    And "Siti" bisa menyunting isi "P-001"
    And "Siti" bisa menarik "P-001"

  @BR-SM-16 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-SUBMIT-15] Penolakan menyebut pengajuan aktif yang sudah ada
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then submit ditolak karena sudah ada pengajuan aktif pada skema itu
    And penolakan menyebut "P-001" beserta tautannya
    And penolakan bukan galat database mentah

  @BR-SM-16 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-SUBMIT-16] Draft yang gagal disubmit tetap bisa disunting
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then submit ditolak karena sudah ada pengajuan aktif pada skema itu
    And status "P-002" tetap DRAFT
    And "Budi" masih bisa menyunting "P-002"

  @BR-SM-16 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-SUBMIT-17] Index database yang menangkap memberi pesan yang sama
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-001" dan "P-002" secara bersamaan
    Then satu submit berhasil
    And submit lainnya ditolak karena sudah ada pengajuan aktif pada skema itu
    And penolakan bukan galat database mentah
