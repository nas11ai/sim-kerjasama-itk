Feature: Temporal Field Binding
  Perubahan Form tidak boleh memblokir draft yang sudah berjalan. Sebuah field
  hanya diwajibkan untuk sebuah submission kalau field itu sudah ada DAN sudah
  berstatus wajib sebelum submission tersebut dibuat.

  Baris form_submissions lahir di langkah 1 wizard justru supaya jangkar waktu
  ini benar — lihat ADR 0012.

  Background:
    Given Form "Pengajuan Penelitian" aktif di periode berjalan

  @BR-FE-11 @M0 @denial
  Scenario: [FE-TEMPORAL-01] Field wajib yang lahir sebelum submission tetap diwajibkan
    Given FormField "Abstrak" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Abstrak"
    Then submit ditolak dengan error validasi pada field "Abstrak"

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-02] Field yang lahir setelah submission tidak diwajibkan
    Given submission "P-001" dibuat pada "2026-02-01"
    And FormField "Roadmap" dibuat pada "2026-03-01" dengan is_required true
    When Lead Researcher submit "P-001" tanpa mengisi "Roadmap"
    Then submit berhasil
    And status "P-001" menjadi SUBMITTED

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-03] Field yang baru jadi wajib setelah submission tidak diwajibkan
    Given FormField "Mitra" dibuat pada "2026-01-10" dengan is_required false
    And submission "P-001" dibuat pada "2026-02-01"
    And "Mitra" diubah menjadi wajib pada "2026-03-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Mitra"
    Then submit berhasil

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-04] Field yang jadi wajib tepat sebelum submission diwajibkan
    Given FormField "Mitra" dibuat pada "2026-01-10" dengan is_required false
    And "Mitra" diubah menjadi wajib pada "2026-01-20"
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Mitra"
    Then submit ditolak dengan error validasi pada field "Mitra"

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-05] required_since kosong berarti mengikuti is_required apa adanya
    Given FormField "Judul" dibuat pada "2026-01-10" dengan is_required true dan required_since kosong
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Judul"
    Then submit ditolak dengan error validasi pada field "Judul"

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-06] Field opsional tidak pernah diwajibkan
    Given FormField "Catatan" dibuat pada "2026-01-10" dengan is_required false
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Catatan"
    Then submit berhasil

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-07] Field yang lahir tepat bersamaan dengan submission diwajibkan
    Given FormField "Luaran" dibuat pada "2026-02-01 09:00" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01 09:00"
    When Lead Researcher submit "P-001" tanpa mengisi "Luaran"
    Then submit ditolak dengan error validasi pada field "Luaran"

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-08] Field yang sudah di-soft-delete tidak diwajibkan
    Given FormField "Lampiran Lama" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01"
    And "Lampiran Lama" dihapus pada "2026-02-15"
    When Lead Researcher submit "P-001" tanpa mengisi "Lampiran Lama"
    Then submit berhasil

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-09] Validasi penuh hanya berjalan di langkah tujuh wizard
    Given FormField "Abstrak" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01" dan berada di langkah 3
    When Lead Researcher menyimpan langkah 3 tanpa mengisi "Abstrak"
    Then penyimpanan berhasil
    And status "P-001" tetap DRAFT
