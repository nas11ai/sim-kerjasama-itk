Feature: Deadline dan Override
  Lewat deadline, akses ke FormPhaseDetail ditutup keras kecuali ada
  FormSubmissionOverride yang masih berlaku. Fase yang sudah lewat tetap
  terlihat sebagai read-only supaya peneliti bisa membaca apa yang ia kirim.

  Background:
    Given FormPhaseDetail "Pengajuan" punya deadline "2026-03-01 23:59"
    And submission "P-001" milik "Budi" berada di fase tersebut

  @BR-FE-08 @M0 @happy
  Scenario: [FE-OVERRIDE-01] Akses terbuka sebelum deadline
    Given waktu sekarang "2026-02-20 10:00"
    When "Budi" membuka fase "Pengajuan"
    Then fase bisa diisi

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-02] Akses ditutup setelah deadline tanpa override
    Given waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403
    And pesan menyebut cara menghubungi Operator

  @BR-FE-08 @BR-FE-10 @M0 @happy
  Scenario: [FE-OVERRIDE-03] Override aktif membuka kembali akses
    Given waktu sekarang "2026-03-02 00:01"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    When "Budi" membuka fase "Pengajuan"
    Then fase bisa diisi

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-04] Override yang sudah kedaluwarsa tidak membuka akses
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-05"
    And waktu sekarang "2026-03-06 08:00"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-05] Override yang dicabut tidak membuka akses
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    And Operator mencabut override tersebut
    And waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-09 @M0 @denial
  Scenario: [FE-OVERRIDE-06] Override tanpa alasan ditolak
    When Operator membuat override untuk "P-001" pada fase "Pengajuan" dengan alasan kosong
    Then pembuatan ditolak dengan error validasi pada field alasan

  @BR-FE-10 @M0 @denial
  Scenario: [FE-OVERRIDE-07] Override tidak berlaku untuk fase lain
    Given FormPhaseDetail "Laporan Monev I" punya deadline "2026-06-01 23:59"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-07-01"
    And waktu sekarang "2026-06-02 08:00"
    When "Budi" membuka fase "Laporan Monev I"
    Then akses ditolak dengan 403

  @BR-FE-10 @M0 @denial
  Scenario: [FE-OVERRIDE-08] Override tidak berlaku untuk submission lain
    Given submission "P-002" milik "Sari" berada di fase "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    And waktu sekarang "2026-03-02 00:01"
    When "Sari" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-16 @M0 @denial
  Scenario: [FE-OVERRIDE-09] Researcher tidak bisa melihat alasan override
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" dengan alasan "Kendala jaringan kampus"
    When "Budi" membuka detail "P-001"
    Then "Budi" melihat status override "Aktif"
    And "Budi" tidak melihat teks "Kendala jaringan kampus"

  @BR-FE-15 @M0 @happy
  Scenario: [FE-OVERRIDE-10] Fase yang lewat deadline tetap terlihat read-only
    Given waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka detail "P-001"
    Then fase "Pengajuan" terlihat dengan jawaban yang sudah dikirim
    And fase "Pengajuan" tidak bisa diubah

  @BR-SCH-07 @M0 @denial
  Scenario: [FE-OVERRIDE-11] Periode force closed menutup semua fase meski deadline belum lewat
    Given waktu sekarang "2026-02-20 10:00"
    And periode berjalan berstatus is_force_closed
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-27 @BR-FE-08 @M2 @OPERATOR-02-03 @REVIEWER-03-03 @happy
  Scenario: [FE-OVERRIDE-12] Override membuka evaluasi reviewer yang lewat Batas Evaluasi
    Given waktu sekarang "2026-03-25 09:00:00"
    And detail "Pengajuan" punya "Batas Evaluasi" pada "2026-03-20"
    And "Sari" ditugaskan sebagai reviewer "P-001"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Sari" membuka evaluasinya
    Then evaluasi bisa diisi

  @BR-FE-27 @BR-FE-08 @M2 @OPERATOR-02-03 @RESEARCHER-02-05 @happy
  Scenario: [FE-OVERRIDE-13] Override membuka revisi yang lewat Batas Revisi
    Given waktu sekarang "2026-03-20 09:00:00"
    And detail "Pengajuan" punya "Batas Revisi" pada "2026-03-15"
    And submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-31 23:59:59"
    When "Budi" menyunting isi "P-001"
    Then penyuntingan berhasil

  @BR-FE-27 @M2 @OPERATOR-02-03 @denial
  Scenario Outline: [FE-OVERRIDE-14] Hanya pemegang submissions.view-all yang bisa membuat override
    Given "<aktor>" tidak punya permission "submissions.view-all"
    When "<aktor>" membuat override untuk "P-001" pada fase "Pengajuan"
    Then permintaan ditolak dengan 403

    Examples:
      | aktor                         |
      | peneliti pemilik P-001        |
      | reviewer P-001                |
      | pemegang periods.manage saja  |
