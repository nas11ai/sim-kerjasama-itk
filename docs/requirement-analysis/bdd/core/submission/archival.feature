Feature: Arsip Pengajuan
  isArchived() adalah computed property, bukan kolom: is_submitted = false DAN
  period sudah tutup. Draft tidak pernah dihapus saat period tutup.

  @BR-SM-10 @M1 @RESEARCHER-02-01 @happy
  Scenario: [SM-ARCHIVE-01] Draft berubah read-only saat period tutup
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When periode berjalan ditutup
    Then "P-001" tetap ada
    And "P-001" ditandai diarsipkan

  @BR-SM-10 @M1 @SHARED-02-01 @happy
  Scenario: [SM-ARCHIVE-02] Pemilik draft diberi tahu saat period tutup
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When periode berjalan ditutup
    Then "Budi" menerima notifikasi periode ditutup

  @BR-SM-10 @M1 @RESEARCHER-02-04 @denial
  Scenario: [SM-ARCHIVE-03] Draft yang diarsipkan tidak bisa disunting
    Given submission "P-001" milik "Budi" ditandai diarsipkan
    When "Budi" menyunting isi "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-04] Belum disubmit dan period tutup berarti diarsipkan
    Given submission "P-001" belum disubmit
    And periode berjalan sudah tutup
    Then "P-001" ditandai diarsipkan

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-05] Sudah disubmit tidak ikut diarsipkan
    Given submission "P-001" sudah disubmit
    And periode berjalan sudah tutup
    Then "P-001" tidak ditandai diarsipkan

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-06] Force closed memicu arsip meski tanggal belum lewat
    Given submission "P-001" belum disubmit
    And periode berjalan berstatus is_force_closed
    Then "P-001" ditandai diarsipkan
