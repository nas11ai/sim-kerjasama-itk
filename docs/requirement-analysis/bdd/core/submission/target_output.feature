Feature: Target Luaran Pengajuan
  Target luaran adalah luaran yang dijanjikan — bedakan dari ResearchOutput yang
  terealisasi. Ia boleh berubah setelah disetujui, tapi hanya oleh ketua pengaju,
  dan setiap perubahan pasca-APPROVED tercatat.

  Background:
    Given submission "P-001" milik "Budi" berskema "DIPA Internal"
    And skema "DIPA Internal" mengizinkan output type "Publikasi Ilmiah" dan "HAKI"

  @BR-SM-14 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-TARGET-01] Memilih tipe luaran yang terdaftar
    When "Budi" menambahkan target luaran bertipe "Publikasi Ilmiah" ke "P-001"
    Then target luaran tersimpan di "P-001"

  @BR-SM-14 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-TARGET-02] Tipe di luar daftar definisi ditolak
    When "Budi" menambahkan target luaran bertipe "Podcast" ke "P-001"
    Then penambahan ditolak karena tipe tidak terdaftar

  @BR-SM-14 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-TARGET-03] Tipe di luar izin skema ditolak
    When "Budi" menambahkan target luaran bertipe "Buku" ke "P-001"
    Then penambahan ditolak karena tipe tidak diizinkan skema

  @BR-SM-14 @M1 @RESEARCHER-02-07 @boundary
  Scenario: [SM-TARGET-04] Submission tanpa skema hanya dibatasi daftar definisi
    Given submission "P-002" milik "Budi" tanpa skema
    When "Budi" menambahkan target luaran bertipe "Buku" ke "P-002"
    Then target luaran tersimpan di "P-002"

  @BR-SM-15 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-TARGET-05] Target bisa diubah saat draft
    Given "P-001" berstatus DRAFT
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tersimpan

  @BR-SM-15 @M1 @RESEARCHER-02-11 @happy
  Scenario: [SM-TARGET-06] Target bisa diubah setelah disetujui
    Given "P-001" berstatus APPROVED
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tersimpan

  @BR-SM-15 @M1 @denial
  Scenario Outline: [SM-TARGET-07] Target terkunci pada status mati
    Given "P-001" berstatus <status>
    When "Budi" mengubah target luaran "P-001"
    Then perubahan ditolak dengan 403

    Examples:
      | status    |
      | REJECTED  |
      | WITHDRAWN |

  @BR-SM-15 @M1 @RESEARCHER-02-11 @denial
  Scenario: [SM-TARGET-08] Co-investigator tidak bisa mengubah target
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" mengubah target luaran "P-001"
    Then perubahan ditolak dengan 403

  @BR-SM-15 @M1 @RESEARCHER-02-11 @happy
  Scenario: [SM-TARGET-09] Perubahan pasca-approved tercatat lengkap
    Given "P-001" berstatus APPROVED
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru
