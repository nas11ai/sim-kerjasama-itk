Feature: Perubahan Form dengan Draft Berjalan
  FormField tidak pernah dihapus permanen, dan operator diberi tahu berapa draft
  yang terdampak sebelum perubahan disebarkan.

  @BR-FE-12 @M0 @ADMIN-08-06 @happy
  Scenario: [FE-CHANGE-01] Menghapus field menyisakan jawaban lama
    Given FormField "Lampiran Lama" punya jawaban di submission "P-001"
    When Admin menghapus FormField "Lampiran Lama"
    Then "Lampiran Lama" ditandai terhapus tanpa dihilangkan dari basis data
    And jawaban "P-001" untuk field itu tetap terbaca di tampilan read-only

  @BR-FE-13 @M0 @ADMIN-08-06 @happy
  Scenario: [FE-CHANGE-02] Admin diberi tahu jumlah draft terdampak
    Given Form "Pengajuan Penelitian" punya 12 submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then Admin melihat peringatan bahwa 12 draft terdampak
    And Admin ditawari mengirim notifikasi ke peneliti terdampak

  @BR-FE-13 @M0 @ADMIN-08-06 @happy
  Scenario: [FE-CHANGE-03] Admin memilih mengirim notifikasi
    Given Admin mengubah FormField "Mitra" menjadi wajib pada Form dengan 12 draft
    When Admin memilih mengirim notifikasi
    Then 12 peneliti pemilik draft menerima notifikasi perubahan form

  @BR-FE-13 @M0 @ADMIN-08-06 @boundary
  Scenario: [FE-CHANGE-04] Tanpa draft berjalan, tidak ada peringatan
    Given Form "Pengajuan Penelitian" tidak punya submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then perubahan tersimpan tanpa peringatan draft terdampak

  @BR-FE-18 @M2 @ADMIN-08-06 @happy
  Scenario: [FE-CHANGE-05] Field yang baru diwajibkan berlaku sejak saat itu
    Given waktu sekarang "2026-04-10 09:00:00"
    And FormField "Mitra" dibuat pada "2026-01-01 09:00:00" dengan is_required false
    When admin mengubah "Mitra" menjadi is_required true
    Then required_since "Mitra" menjadi "2026-04-10 09:00:00"

  @BR-FE-18 @BR-FE-11 @M2 @ADMIN-08-06 @boundary
  Scenario: [FE-CHANGE-06] Draft berjalan tidak ikut terkena field wajib baru
    Given waktu sekarang "2026-04-10 09:00:00"
    And submission "P-001" dibuat pada "2026-02-01 09:00:00"
    And FormField "Mitra" dibuat pada "2026-01-01 09:00:00" dengan is_required false
    When admin mengubah "Mitra" menjadi is_required true
    And "Budi" submit "P-001"
    Then submit berhasil
