Feature: Wizard Pengajuan Tujuh Langkah
  Baris form_submissions lahir di langkah 1, bukan di akhir — jangkar temporal
  BR-FE-11 harus ada saat researcher mulai mengisi. Navigasi antar langkah bebas;
  yang menggerbang adalah prasyarat data, bukan nomor langkah (BR-SM-17).

  Background:
    Given Form "Pengajuan Penelitian" punya field bertipe scheme_selector
    And periode berjalan aktif dan belum force closed

  @BR-FE-11 @M1 @RESEARCHER-02-02 @happy
  Scenario: [SM-WIZARD-01] Langkah 1 membuat draft beserta jangkar temporalnya
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1
    And created_at submission menjadi jangkar Temporal Field Binding

  @BR-SM-17 @M1 @RESEARCHER-02-06 @happy
  Scenario: [SM-WIZARD-02] Isian langkah sebelumnya bertahan saat berpindah
    Given "Budi" sudah mengisi Informasi Dasar di langkah 2
    When "Budi" berpindah ke langkah 3 lalu kembali ke langkah 2
    Then isian Informasi Dasar masih utuh

  @BR-FE-11 @M1 @RESEARCHER-02-06 @boundary
  Scenario: [SM-WIZARD-03] Validasi langkah bersifat parsial
    Given "Budi" berada di langkah 2
    When "Budi" menyimpan langkah 2 dengan Rencana Anggaran masih kosong
    Then penyimpanan berhasil
    And tidak ada error tentang Rencana Anggaran

  @BR-SM-17 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-WIZARD-04] Anggaran terkunci selama skema belum dipilih
    Given "Budi" belum memilih skema
    When "Budi" membuka langkah Rencana Anggaran
    Then akses ditolak karena skema belum dipilih

  @BR-SCH-09 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-WIZARD-05] Langkah 3 menulis ke kolom, bukan ke jawaban form
    When "Budi" memilih skema "DIPA Internal" dan TRL 4 di langkah 3
    Then scheme_id submission terisi skema "DIPA Internal"
    And trl_id submission terisi TRL 4

  @BR-FE-11 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-WIZARD-06] Validasi penuh baru berjalan di langkah 7
    Given "Budi" belum mengisi field wajib "Mitra"
    When "Budi" membuka langkah Review & Submit
    Then halaman terbuka
    And "Budi" melihat error per field pada "Mitra"

  @BR-SM-17 @M1 @RESEARCHER-02-01 @happy
  Scenario: [SM-WIZARD-07] Draft dilanjutkan dari langkah terakhir
    Given "Budi" menutup wizard pada langkah 4
    When "Budi" membuka kembali draft itu
    Then wizard terbuka pada langkah 4

  @BR-SM-17 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-WIZARD-08] Submit ditolak selama prasyarat belum terpenuhi
    Given "Budi" belum mengunggah proposal PDF
    When "Budi" menekan Submit di langkah Review & Submit
    Then submit ditolak karena prasyarat belum terpenuhi
    And halaman Review & Submit tetap terbuka

  @BR-SCH-04 @M1 @RESEARCHER-02-07 @boundary
  Scenario: [SM-WIZARD-09] Form tanpa scheme_selector melewati pembatas skema
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    And "Budi" memulai pengajuan baru pada "Pengajuan Sederhana"
    When "Budi" membuka langkah Rencana Anggaran
    Then langkah bisa diisi
    And tidak ada batas anggaran yang berlaku

  @BR-SM-17 @M1 @RESEARCHER-02-06 @happy
  Scenario: [SM-WIZARD-10] Melompat mundur beberapa langkah sekaligus
    Given "Budi" berada di langkah 6
    When "Budi" melompat langsung ke langkah 2
    Then langkah 2 terbuka
    And isian langkah 6 tetap tersimpan

  @BR-SM-17 @M1 @RESEARCHER-02-09 @boundary
  Scenario: [SM-WIZARD-11] Langkah tanpa prasyarat terbuka kapan saja
    Given "Budi" baru menyelesaikan langkah 1
    When "Budi" membuka langkah Upload Berkas
    Then langkah bisa diisi

  @BR-SM-01 @M1 @RESEARCHER-02-02 @happy
  Scenario: [SM-WIZARD-12] Pengajuan yang berakhir terminal tidak memblokir draft baru
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus REJECTED
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1
    And "P-001" tetap REJECTED
