Feature: Anggota Penelitian
  Satu orang tidak boleh menilai penelitian yang ia ikuti. Larangan ini berlaku dua
  arah; sisi reviewer dijaga [REV-ASSIGN-07] di M2.

  Background:
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And skema "DIPA Internal" membatasi max_members 4

  @BR-SM-08 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-01] Reviewer submission ini tidak bisa jadi anggotanya
    Given "Rina" ditugaskan sebagai reviewer "P-001"
    When "Budi" menambahkan "Rina" sebagai anggota "P-001"
    Then penambahan ditolak karena konflik kepentingan

  @BR-SM-08 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-MEMBER-02] Anggota biasa bisa ditambahkan
    When "Budi" menambahkan "Sari" sebagai co_investigator "P-001"
    Then "Sari" terdaftar sebagai co_investigator di "P-001"

  @BR-SM-08 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-MEMBER-03] Jumlah anggota dibatasi skema
    Given "P-001" sudah punya 4 anggota
    When "Budi" menambahkan satu anggota lagi ke "P-001"
    Then penambahan ditolak karena melebihi batas skema

  @BR-SM-18 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-MEMBER-04] Ketua pengaju mengeluarkan anggota
    Given pengajuan "P-001" berstatus DRAFT dengan anggota "Sari"
    When submitted_by mengeluarkan "Sari" dari "P-001"
    Then "Sari" tidak lagi terdaftar sebagai anggota "P-001"

  @BR-SM-18 @BR-SCH-03 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-MEMBER-05] Mengeluarkan anggota membuka kembali kuota skema
    Given skema "Penelitian Dasar" membatasi anggota maksimal 3
    And pengajuan "P-001" sudah punya 3 anggota
    When submitted_by mengeluarkan satu anggota dari "P-001"
    And submitted_by menambahkan "Tono" sebagai anggota
    Then "Tono" tersimpan sebagai anggota "P-001"

  @BR-SM-18 @BR-SM-07 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-06] Ketua pengaju tidak bisa mengeluarkan dirinya sendiri
    Given pengajuan "P-001" diajukan "Budi"
    When "Budi" mengeluarkan dirinya sendiri dari "P-001"
    Then pengeluaran ditolak karena submitted_by tidak bisa keluar dari pengajuannya

  @BR-SM-18 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-07] Anggota biasa tidak bisa mengeluarkan anggota lain
    Given pengajuan "P-001" punya anggota "Sari" dan "Tono"
    When "Sari" mengeluarkan "Tono" dari "P-001"
    Then pengeluaran ditolak karena bukan wewenangnya

  @BR-SM-18 @M1 @RESEARCHER-02-08 @denial
  Scenario Outline: [SM-MEMBER-08] Anggota tidak bisa dikeluarkan pada status terminal
    Given pengajuan "P-001" berstatus <status> dengan anggota "Sari"
    When submitted_by mengeluarkan "Sari" dari "P-001"
    Then pengeluaran ditolak karena pengajuan sudah terminal

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  # BR-SM-20 — anggota mahasiswa: data, bukan akun. Mahasiswa tidak mendaftar
  # ke SIMPAS, jadi barisnya tidak menunjuk UserProfile seperti anggota
  # bergelar, dan cek konflik kepentingan tidak berlaku baginya.

  @BR-SM-20 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-STUDENT-01] Ketua pengaju mencatat anggota mahasiswa
    Given pengajuan "P-001" berstatus DRAFT
    When submitted_by mencatat mahasiswa "Andi" dengan NIM "10211001"
    Then "Andi" terdaftar sebagai anggota mahasiswa "P-001"

  @BR-SM-20 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-STUDENT-02] Jumlah mahasiswa dibatasi skema
    Given skema "Penelitian Dasar" membatasi mahasiswa maksimal 2
    And pengajuan "P-001" sudah punya 2 anggota mahasiswa
    When submitted_by mencatat mahasiswa ketiga
    Then pencatatan ditolak karena batas skema sudah tercapai

  @BR-SM-20 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-STUDENT-03] Skema tanpa max_student_members tidak membatasi
    Given skema "Penelitian Dasar" tidak menyatakan max_student_members
    And pengajuan "P-001" sudah punya 5 anggota mahasiswa
    When submitted_by mencatat mahasiswa keenam
    Then pencatatan berhasil

  @BR-SM-20 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-STUDENT-04] NIM yang sama tidak dicatat dua kali di satu pengajuan
    Given pengajuan "P-001" sudah mencatat mahasiswa dengan NIM "10211001"
    When submitted_by mencatat NIM "10211001" sekali lagi
    Then pencatatan ditolak karena NIM itu sudah terdaftar di pengajuan ini

  @BR-SM-20 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-STUDENT-05] Mahasiswa yang sama boleh terlibat di pengajuan lain
    Given mahasiswa dengan NIM "10211001" tercatat di pengajuan "P-001"
    When submitted_by pengajuan "P-002" mencatat NIM "10211001"
    Then pencatatan berhasil

  @BR-SM-20 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-STUDENT-06] Mengeluarkan mahasiswa membuka kembali kuota skema
    Given skema "Penelitian Dasar" membatasi mahasiswa maksimal 1
    And pengajuan "P-001" sudah punya 1 anggota mahasiswa
    When submitted_by mengeluarkan mahasiswa itu
    And submitted_by mencatat mahasiswa lain
    Then pencatatan berhasil

  # BR-SM-21 — mitra eksternal. Dicatat sebagai baris, bukan teks bebas:
  # `output_type = 'pks'` sudah menyimpan involved_parties di metadata JSON-nya,
  # tapi itu kemitraan yang sudah terwujud dan dalam bentuk yang tidak bisa
  # ditanyai. Dua momen, dua entitas.

  @BR-SM-21 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-PARTNER-01] Ketua pengaju mencatat mitra eksternal
    Given pengajuan "P-001" berstatus DRAFT
    When submitted_by mencatat mitra "PT XYZ" dengan kontak "Ibu Rina"
    Then "PT XYZ" terdaftar sebagai mitra "P-001"

  @BR-SM-21 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-PARTNER-02] Institusi yang sama tidak dicatat dua kali
    Given pengajuan "P-001" sudah mencatat mitra "PT XYZ"
    When submitted_by mencatat "pt xyz" sekali lagi
    Then pencatatan ditolak karena institusi itu sudah tercatat

  @BR-SM-21 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-PARTNER-03] Institusi yang sama boleh jadi mitra pengajuan lain
    Given "PT XYZ" tercatat sebagai mitra pengajuan "P-001"
    When submitted_by pengajuan "P-002" mencatat "PT XYZ"
    Then pencatatan berhasil

  @BR-SM-21 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-PARTNER-04] Cakupan kemitraan bisa ditanyai per institusi
    Given "PT XYZ" tercatat sebagai mitra tiga pengajuan berbeda
    When LPPM menghitung pengajuan yang melibatkan "PT XYZ"
    Then jawabannya tiga

  @BR-SM-21 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-PARTNER-05] Ketua pengaju mencabut mitra
    Given pengajuan "P-001" berstatus DRAFT punya mitra "PT XYZ"
    When submitted_by mencabut mitra itu
    Then "PT XYZ" tidak lagi terdaftar sebagai mitra "P-001"

  @BR-SM-21 @M1 @RESEARCHER-02-08 @denial
  Scenario Outline: [SM-PARTNER-06] Mitra terkunci pada status terminal
    Given pengajuan "P-001" berstatus <status> punya mitra "PT XYZ"
    When submitted_by mencabut mitra itu
    Then pencabutan ditolak karena pengajuan sudah terminal

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |
