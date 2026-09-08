Feature: Ekspor Data
  Ambang 100 baris memisahkan ekspor langsung dari background job — angka itu
  ambang keputusan di BR-RPT-02, bukan detail implementasi. Ekspor Researcher
  memakai scope yang sama persis dengan BR-RPT-05, bukan konsep scoping kedua.

  @BR-RPT-02 @M5 @OPERATOR-07-02 @happy
  Scenario: [RPT-EXPORT-01] Ekspor kecil selesai langsung
    Given hasil ekspor berjumlah 100 baris
    When Operator meminta ekspor data submission
    Then ekspor selesai langsung
    And berkas hasil ekspor bisa diunduh

  @BR-RPT-02 @M5 @OPERATOR-07-02 @boundary
  Scenario: [RPT-EXPORT-02] Ekspor besar masuk background job
    Given hasil ekspor berjumlah 101 baris
    When Operator meminta ekspor data submission
    Then ekspor dijadwalkan sebagai background job
    And Operator tidak menunggu di layar

  @BR-RPT-03 @M5 @OPERATOR-07-02 @happy
  Scenario: [RPT-EXPORT-03] Berkas ekspor diakses lewat signed URL 24 jam
    Given ekspor "E-001" sudah selesai
    When Operator membuka berkas hasil ekspor "E-001"
    Then berkas diakses lewat signed URL yang berlaku 24 jam

  @BR-RPT-03 @M5 @OPERATOR-07-02 @denial
  Scenario: [RPT-EXPORT-04] Signed URL kedaluwarsa ditolak
    Given signed URL ekspor "E-001" diterbitkan 25 jam lalu
    When Operator membuka berkas hasil ekspor "E-001"
    Then akses ditolak dengan 403

  @BR-RPT-07 @M5 @OPERATOR-07-02 @happy
  Scenario: [RPT-EXPORT-05] Notifikasi ekspor hanya ke Operator peminta
    Given Operator "Dewi" meminta ekspor data submission
    When ekspor itu selesai
    Then "Dewi" menerima notifikasi ExportCompleted
    And Operator lain tidak menerima notifikasi ExportCompleted

  @BR-RPT-08 @M5 @OPERATOR-07-02 @boundary
  Scenario: [RPT-EXPORT-06] Job gagal diulang maksimal tiga kali
    Given job ekspor "E-002" gagal pada percobaan pertama
    When sistem menjalankan ulang job itu
    Then job diulang maksimal 3 kali dengan jeda menaik

  @BR-RPT-08 @M5 @OPERATOR-07-02 @happy
  Scenario: [RPT-EXPORT-07] Kegagalan final memberi tahu Operator peminta
    Given Operator "Dewi" meminta ekspor data submission
    And job ekspor itu gagal 3 kali
    When percobaan terakhir habis
    Then "Dewi" menerima notifikasi ExportFailed

  @BR-RPT-10 @M5 @RESEARCHER-02-01 @happy
  Scenario: [RPT-EXPORT-08] Researcher mengekspor data miliknya sendiri
    Given submission "P-001" milik "Budi" berstatus APPROVED
    When "Budi" meminta ekspor data submission miliknya
    Then ekspor selesai langsung
    And hasil ekspor memuat "P-001"

  @BR-RPT-10 @M5 @RESEARCHER-02-01 @denial
  Scenario: [RPT-EXPORT-09] Ekspor Researcher tidak memuat milik orang lain
    Given submission "P-001" milik "Budi" berstatus APPROVED
    And submission "P-007" milik "Sari" berstatus APPROVED
    When "Budi" meminta ekspor data submission miliknya
    Then hasil ekspor memuat "P-001"
    And hasil ekspor tidak memuat "P-007"

  @BR-RPT-12 @M5 @OPERATOR-07-02 @denial
  Scenario: [RPT-EXPORT-10] Berkas ekspor terhapus setelah 24 jam
    Given ekspor "E-001" selesai 25 jam lalu
    When Operator membuka berkas hasil ekspor "E-001"
    Then akses ditolak karena berkas ekspor sudah dihapus

  @BR-RPT-12 @M5 @OPERATOR-07-02 @boundary
  Scenario: [RPT-EXPORT-11] Berkas yang belum 24 jam masih bisa diunduh
    Given ekspor "E-001" selesai 23 jam lalu
    When Operator membuka berkas hasil ekspor "E-001"
    Then berkas hasil ekspor bisa diunduh
