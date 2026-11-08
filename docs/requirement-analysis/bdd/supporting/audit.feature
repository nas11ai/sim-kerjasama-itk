Feature: Jejak Audit
  BR-RPT-04 berbunyi append-only, dan itu punya dua cara dilanggar: dihapus dan
  disunting. Keduanya ditutup terpisah karena implementasi yang hanya melarang
  DELETE tetap membiarkan UPDATE menulis ulang sejarah. Cakupan audit diturunkan
  dari arsitektur — setiap Action yang mengubah state ikut tercatat (BR-RPT-09).

  @BR-RPT-04 @M5 @OPERATOR-07-03 @happy
  Scenario: [RPT-AUDIT-01] Aksi yang mengubah state menambah entri audit
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then entri audit baru tercatat untuk "P-001"

  @BR-RPT-04 @M5 @OPERATOR-07-03 @denial
  Scenario: [RPT-AUDIT-02] Entri audit tidak bisa dihapus
    Given ada entri audit untuk "P-001"
    When Operator menghapus entri audit itu
    Then penghapusan ditolak karena audit log bersifat append-only

  @BR-RPT-04 @M5 @OPERATOR-07-03 @denial
  Scenario: [RPT-AUDIT-03] Entri audit tidak bisa disunting
    Given ada entri audit untuk "P-001"
    When Operator menyunting entri audit itu
    Then penyuntingan ditolak karena audit log bersifat append-only

  @BR-RPT-05 @M5 @OPERATOR-07-03 @happy
  Scenario: [RPT-AUDIT-04] Operator melihat audit log seluruh sistem
    Given ada entri audit untuk "P-001" dan "P-007"
    When Operator membuka audit log
    Then "P-001" muncul di daftar
    And "P-007" muncul di daftar

  @BR-RPT-05 @M5 @RESEARCHER-07-01 @denial
  Scenario: [RPT-AUDIT-05] Researcher hanya melihat audit submission miliknya
    Given submission "P-001" milik "Budi" berstatus APPROVED
    And submission "P-007" milik "Sari" berstatus APPROVED
    When "Budi" membuka audit log
    Then "P-001" muncul di daftar
    And "P-007" tidak muncul di daftar

  @BR-RPT-09 @M5 @OPERATOR-07-03 @happy
  Scenario: [RPT-AUDIT-06] Entri audit memuat aktor, waktu, dan nilai sebelum-sesudah
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" menyunting isi "P-001"
    Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru
    And entri audit itu menyebut aktor dan waktunya

  @BR-RPT-09 @M5 @OPERATOR-07-03 @boundary
  Scenario: [RPT-AUDIT-07] Cakupan audit diturunkan dari arsitektur
    When Action mana pun yang mengubah state dijalankan
    Then entri audit tercatat tanpa perlu didaftarkan manual

  @BR-RPT-09 @M5 @OPERATOR-07-03 @happy
  Scenario: [RPT-AUDIT-08] Entri audit mencatat alamat IP pelaku
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001" dari alamat 10.10.2.14
    Then entri audit "Pengajuan disubmit" untuk "P-001" mencatat IP 10.10.2.14
