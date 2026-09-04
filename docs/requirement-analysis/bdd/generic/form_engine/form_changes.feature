Feature: Perubahan Form dengan Draft Berjalan
  FormField tidak pernah dihapus permanen, dan operator diberi tahu berapa draft
  yang terdampak sebelum perubahan disebarkan.

  @BR-FE-12 @M0 @happy
  Scenario: [FE-CHANGE-01] Menghapus field menyisakan jawaban lama
    Given FormField "Lampiran Lama" punya jawaban di submission "P-001"
    When Admin menghapus FormField "Lampiran Lama"
    Then "Lampiran Lama" ditandai terhapus tanpa dihilangkan dari basis data
    And jawaban "P-001" untuk field itu tetap terbaca di tampilan read-only

  @BR-FE-13 @M0 @happy
  Scenario: [FE-CHANGE-02] Admin diberi tahu jumlah draft terdampak
    Given Form "Pengajuan Penelitian" punya 12 submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then Admin melihat peringatan bahwa 12 draft terdampak
    And Admin ditawari mengirim notifikasi ke peneliti terdampak

  @BR-FE-13 @M0 @happy
  Scenario: [FE-CHANGE-03] Admin memilih mengirim notifikasi
    Given Admin mengubah FormField "Mitra" menjadi wajib pada Form dengan 12 draft
    When Admin memilih mengirim notifikasi
    Then 12 peneliti pemilik draft menerima notifikasi perubahan form

  @BR-FE-13 @M0 @boundary
  Scenario: [FE-CHANGE-04] Tanpa draft berjalan, tidak ada peringatan
    Given Form "Pengajuan Penelitian" tidak punya submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then perubahan tersimpan tanpa peringatan draft terdampak
