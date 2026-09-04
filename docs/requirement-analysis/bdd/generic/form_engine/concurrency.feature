Feature: Optimistic Locking
  Dua orang yang menyunting submission yang sama tidak boleh saling menimpa
  diam-diam. Penyimpanan membawa updated_at yang dilihat penyunting; kalau
  nilainya sudah bergeser di basis data, penyimpanan ditolak.

  Background:
    Given submission "P-001" terakhir diubah pada "2026-02-01 10:00"

  @BR-FE-14 @BR-SM-12 @M0 @happy
  Scenario: [FE-LOCK-01] Penyimpanan lolos saat tidak ada yang mendahului
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan berhasil

  @BR-FE-14 @BR-SM-12 @M0 @denial
  Scenario: [FE-LOCK-02] Penyimpanan ditolak saat sudah didahului orang lain
    Given Operator mengubah "P-001" pada "2026-02-01 10:05"
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan ditolak dengan 409
    And pesan meminta memuat ulang halaman

  @BR-FE-14 @BR-SM-12 @M0 @denial
  Scenario: [FE-LOCK-03] Penyimpanan tanpa last_updated_at ditolak
    When Lead Researcher menyimpan "P-001" tanpa menyertakan last_updated_at
    Then penyimpanan ditolak dengan error validasi

  @BR-FE-14 @BR-SM-12 @M0 @denial
  Scenario: [FE-LOCK-04] Penolakan tidak mengubah data
    Given Operator mengubah judul "P-001" menjadi "Judul Operator" pada "2026-02-01 10:05"
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan ditolak dengan 409
    And judul "P-001" tetap "Judul Operator"
