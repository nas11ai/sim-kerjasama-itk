Feature: Satu Form Pengajuan per Tahap
  Tahap (Fase) adalah template tanpa tanggal yang dipakai banyak periode. Tiap tahap
  melayani tepat satu Form pengajuan, dan langkah pertamanya adalah langkah Pengajuan
  — jenis "Batas Submit" tanpa siklus (BR-FE-28). Yang dihitung Form-nya, bukan aturan
  aksesnya: satu Form yang dibuka untuk dua fakultas tetap satu Form pengajuan. Tanpa
  aturan ini siklus monev sebuah pengajuan bisa memuat langkah milik Form lain.

  @BR-FE-28 @M2 @ADMIN-08-09 @boundary
  Scenario: [FE-PHASE-01] Satu Form pengajuan lewat dua aturan akses dihitung satu
    Given Form "Pengajuan Penelitian" punya aturan akses untuk "Fakultas A" dan "Fakultas B"
    And Form "Laporan Monev" punya satu aturan akses
    When Admin menyimpan tahap dengan langkah Pengajuan "Pengajuan Penelitian" untuk kedua fakultas lalu langkah "Laporan Monev I"
    Then tahap tersimpan
    And kedua langkah Pengajuan berada di posisi Pengajuan yang sama
    And siklus monev pengajuan "Fakultas A" hanya memuat langkah "Laporan Monev I"

  @BR-FE-28 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHASE-02] Tahap dengan dua Form pengajuan ditolak
    Given Form "Pengajuan Penelitian" dan Form "Pengajuan Pengmas"
    When Admin menyimpan tahap yang dua langkah Pengajuan-nya memakai kedua Form itu
    Then penyimpanan ditolak dengan pesan yang menyebut "Pengajuan Penelitian" dan "Pengajuan Pengmas"
    And tidak ada tahap yang dibuat

  @BR-FE-28 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHASE-03] Tahap yang langkah pertamanya Laporan Monev ditolak
    Given Form "Pengajuan Penelitian" dan Form "Laporan Monev"
    When Admin menyimpan tahap yang langkah pertamanya "Laporan Monev I" lalu langkah Pengajuan
    Then penyimpanan ditolak dengan pesan "Langkah pertama tahap harus langkah Pengajuan."
    And tidak ada tahap yang dibuat

  @BR-FE-28 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHASE-04] Laporan Monev yang memakai Form pengajuan tahap itu ditolak
    Given Form "Pengajuan Penelitian"
    When Admin menyimpan tahap dengan langkah Pengajuan dan langkah "Laporan Monev I" yang sama-sama memakai "Pengajuan Penelitian"
    Then penyimpanan ditolak dengan pesan "Laporan Monev dan Upload Luaran memakai Form selain Form pengajuan."

  @BR-FE-28 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHASE-05] Revisi tahap yang sudah dipakai pengajuan tidak bisa menambah Form pengajuan kedua
    Given tahap "Penelitian DIPA" yang langkah Pengajuan-nya memakai Form "Pengajuan Penelitian"
    And satu pengajuan sudah memakai langkah itu
    When Admin merevisi tahap itu dengan menambah langkah Pengajuan berform "Pengajuan Pengmas"
    Then revisi ditolak
    And langkah tahap dan jejak audit tidak berubah

  @BR-FE-28 @M2 @ADMIN-08-09 @denial
  Scenario: [FE-PHASE-06] Tahap tanpa langkah Pengajuan ditolak
    Given Form "Laporan Monev"
    When Admin menyimpan tahap yang satu-satunya langkahnya "Laporan Monev I"
    Then penyimpanan ditolak dengan pesan "Tahap butuh satu langkah Pengajuan."
