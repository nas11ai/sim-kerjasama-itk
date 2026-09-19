Feature: Anggaran Pengajuan
  Grand total dihitung dari penjumlahan line item dan tidak boleh melebihi batas
  skema. Anggaran terkunci saat pengajuan mencapai status terminal, tapi tetap
  terbuka saat NEEDS_REVISION karena revisi memang menuntutnya.

  Background:
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And skema "DIPA Internal" membatasi max_budget 50000000

  @BR-BUD-01 @BR-SM-06 @M1 @RESEARCHER-02-04 @denial
  Scenario: [BUD-LIMIT-01] Total melebihi batas skema ditolak
    Given "P-001" punya line item bertotal 48000000
    When "Budi" menambahkan line item bertotal 5000000 ke "P-001"
    Then penyimpanan ditolak karena melebihi batas anggaran skema

  @BR-BUD-01 @BR-SM-06 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-LIMIT-02] Total tepat di batas diterima
    Given "P-001" punya line item bertotal 45000000
    When "Budi" menambahkan line item bertotal 5000000 ke "P-001"
    Then penyimpanan berhasil
    And grand total "P-001" menjadi 50000000

  @BR-BUD-01 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-LIMIT-03] Submission tanpa skema tidak punya batas
    Given submission "P-002" milik "Budi" tanpa skema berstatus DRAFT
    When "Budi" menambahkan line item bertotal 90000000 ke "P-002"
    Then penyimpanan berhasil

  @BR-BUD-02 @M1 @RESEARCHER-02-03 @denial
  Scenario Outline: [BUD-LOCK-01] Anggaran terkunci pada status terminal
    Given submission "P-001" berstatus <status>
    When "Budi" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  @BR-BUD-03 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-LOCK-02] Anggaran tetap terbuka saat perlu revisi
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-02 @M1 @OPERATOR-02-02 @denial
  Scenario: [BUD-LOCK-03] Super Admin pun tidak menembus kunci status terminal
    Given submission "P-001" berstatus APPROVED
    And "Siti" berperan Super Admin dan bukan submitted_by "P-001"
    When "Siti" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-BUD-04 @M1 @RESEARCHER-02-04 @denial
  Scenario Outline: [BUD-ITEM-01] Volume dan harga satuan harus lebih dari nol
    When "Budi" menambahkan line item dengan volume <volume> dan harga <harga> ke "P-001"
    Then penyimpanan ditolak dengan error validasi

    Examples:
      | volume | harga   |
      | 0      | 1000000 |
      | -1     | 1000000 |
      | 2      | 0       |
      | 2      | -5000   |

  @BR-BUD-04 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-ITEM-02] Line item sah tersimpan dan menaikkan grand total
    When "Budi" menambahkan line item dengan volume 4 dan harga 1250000 ke "P-001"
    Then penyimpanan berhasil
    And grand total "P-001" menjadi 5000000

  @BR-BUD-05 @M1 @RESEARCHER-02-04 @denial
  Scenario: [BUD-ITEM-03] Komponen non-aktif tidak bisa dipilih untuk item baru
    Given komponen anggaran "Perjalanan Dinas" berstatus tidak aktif
    When "Budi" menambahkan line item berkomponen "Perjalanan Dinas" ke "P-001"
    Then penyimpanan ditolak karena komponen tidak aktif

  @BR-BUD-05 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-ITEM-04] Item lama yang memakai komponen itu tetap utuh
    Given "P-001" punya line item berkomponen "Perjalanan Dinas"
    When komponen anggaran "Perjalanan Dinas" dinonaktifkan
    Then line item "P-001" tetap terbaca

  @BR-BUD-08 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-AUDIT-01] Perubahan saat revisi tercatat nilai sebelum dan sesudah
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" mengubah harga satuan line item "P-001" menjadi 2000000
    Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru

  @BR-BUD-08 @M1 @OPERATOR-07-03 @boundary
  Scenario: [BUD-AUDIT-02] Jejak audit menyimpan pelaku dan waktunya
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" mengubah harga satuan line item "P-001" menjadi 2000000
    Then jejak audit menyebut "Budi" sebagai pelaku
    And jejak audit menyebut waktu perubahan

  @BR-BUD-09 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-WHO-01] Ketua pengaju menyunting anggaran
    When "Budi" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-09 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-WHO-02] Co-investigator ikut menyunting anggaran
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-09 @M1 @RESEARCHER-02-03 @denial
  Scenario: [BUD-WHO-03] Anggota biasa hanya bisa membaca anggaran
    Given "Andi" terdaftar sebagai member di "P-001"
    When "Andi" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-BUD-09 @M1 @OPERATOR-02-02 @denial
  Scenario: [BUD-WHO-04] Super Admin di luar tim tidak menyunting anggaran orang
    Given "Siti" berperan Super Admin dan bukan submitted_by "P-001"
    When "Siti" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-BUD-10 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-ITEM-05] Ketua pengaju menyunting line item
    Given pengajuan "P-001" berstatus DRAFT punya item "Kertas A4" volume 2 harga 50000
    When submitted_by mengubah volume "Kertas A4" menjadi 5
    Then total item itu menjadi 250000
    And grand total pengajuan ikut menyesuaikan

  @BR-BUD-10 @BR-BUD-08 @M1 @OPERATOR-07-03 @boundary
  Scenario: [BUD-ITEM-06] Penyuntingan lewat Action tercatat nilai sebelum dan sesudah
    Given pengajuan "P-001" berstatus NEEDS_REVISION punya item "Kertas A4" volume 2
    When submitted_by mengubah volume "Kertas A4" menjadi 5
    Then jejak audit memuat volume lama 2 dan volume baru 5
    And jejak itu menyebut pelakunya

  @BR-BUD-10 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-ITEM-07] Ketua pengaju menghapus line item
    Given pengajuan "P-001" berstatus DRAFT punya item "Kertas A4"
    When submitted_by menghapus item "Kertas A4"
    Then item itu tidak lagi dihitung dalam grand total
    And barisnya tetap tersimpan sebagai jejak

  @BR-BUD-10 @BR-BUD-01 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-ITEM-08] Menghapus item menurunkan total kembali ke bawah batas skema
    Given skema "Penelitian Dasar" membatasi anggaran 10000000
    And grand total pengajuan "P-001" sudah 12000000
    When submitted_by menghapus item senilai 3000000
    Then grand total menjadi 9000000
    And pengajuan itu bisa disubmit

  @BR-BUD-10 @BR-BUD-02 @M1 @RESEARCHER-02-03 @denial
  Scenario Outline: [BUD-ITEM-09] Line item terkunci pada status terminal
    Given pengajuan "P-001" berstatus <status> punya item "Kertas A4"
    When submitted_by <aksi> item "Kertas A4"
    Then aksi ditolak karena anggaran terkunci

    Examples:
      | status    | aksi      |
      | APPROVED  | menyunting |
      | APPROVED  | menghapus  |
      | REJECTED  | menyunting |
      | REJECTED  | menghapus  |
      | WITHDRAWN | menyunting |
      | WITHDRAWN | menghapus  |

  @BR-BUD-10 @BR-BUD-09 @M1 @RESEARCHER-02-03 @denial
  Scenario: [BUD-ITEM-10] Anggota biasa tidak bisa menyunting atau menghapus line item
    Given pengajuan "P-001" berstatus DRAFT punya item "Kertas A4"
    And "Sari" adalah member biasa "P-001"
    When "Sari" menghapus item "Kertas A4"
    Then penghapusan ditolak karena member hanya bisa membaca anggaran
