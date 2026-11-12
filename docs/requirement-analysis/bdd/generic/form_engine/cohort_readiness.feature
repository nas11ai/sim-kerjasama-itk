Feature: Cek Kesiapan Angkatan
  Tanggal milik periode (BR-FE-26) dan struktur tahap berversi (BR-FE-32), jadi setup sebuah
  angkatan (Periode × Tahap) bisa cacat walau tahapnya lolos saat terbit. Setiap angkatan
  diperiksa terhadap versi strukturnya (BR-FE-36): isu yang dibutuhkan sekarang — langkah
  Pengajuan dan urutan tanggal — menolak pengajuan baru; isu langkah monev dan luaran hanya
  peringatan, karena saat dipakai langkah bertanggal kosong tetap tertutup. Angkatan yang
  sudah punya pengajuan tidak pernah diblokir.

  @BR-FE-36 @M2 @RESEARCHER-02-02 @denial
  Scenario: [FE-READY-01] Batas Evaluasi langkah Pengajuan yang belum diatur menolak pengajuan baru
    Given "Periode 2026" menautkan "Hibah Internal" yang langkah Pengajuannya direview
    And kalender "Periode 2026" belum punya Batas Evaluasi
    When peneliti "P-001" memulai pengajuan "Hibah Internal" di "Periode 2026"
    Then ia ditolak dengan "Tahap ini belum siap menerima pengajuan di periode ini. Hubungi Operator LPPM."
    And "Hibah Internal" tidak ditawarkan di langkah 1 wizard

  @BR-FE-36 @M2 @RESEARCHER-02-02 @boundary
  Scenario: [FE-READY-02] Angkatan lain di periode yang sama tetap menerima pengajuan
    Given "Periode 2026" menautkan "Hibah Internal" yang belum siap dan "Hibah Mandiri" yang siap
    When peneliti "P-001" memulai pengajuan "Hibah Mandiri" di "Periode 2026"
    Then draf pengajuannya dibuat
    And "Hibah Mandiri" tetap ditawarkan di langkah 1 wizard

  @BR-FE-36 @M2 @OPERATOR-09-01 @boundary
  Scenario: [FE-READY-03] Angkatan yang sudah punya pengajuan tidak diblokir; cacatnya tampil sebagai "Perlu perbaikan"
    Given angkatan "Hibah Internal" di "Periode 2026" sudah punya pengajuan
    And kalender "Periode 2026" belum punya Batas Evaluasi yang dibaca langkah Pengajuannya
    When peneliti "P-002" memulai pengajuan "Hibah Internal" di "Periode 2026"
    Then draf pengajuannya dibuat
    When Operator membuka Kesiapan Angkatan "Periode 2026"
    Then "Hibah Internal" berlencana "Perlu perbaikan" dengan keterangan "Angkatan ini sudah punya pengajuan, jadi tidak diblokir."

  @BR-FE-36 @M2 @OPERATOR-09-01 @denial
  Scenario: [FE-READY-04] Batas Evaluasi sebelum Batas Submit menggagalkan cek urutan
    Given kalender "Periode 2026" punya Batas Submit 30 Juni dan Batas Evaluasi 20 Juni
    And "Periode 2026" menautkan "Hibah Internal" yang langkah Pengajuannya direview
    When Operator membuka Kesiapan Angkatan "Periode 2026"
    Then cek "Urutan tanggal masuk akal" gagal dengan "Batas Evaluasi tidak boleh lebih awal dari Batas Submit."
    And "Hibah Internal" berlencana "Belum siap"

  @BR-FE-36 @M2 @OPERATOR-09-01 @denial
  Scenario: [FE-READY-05] Langkah Pengajuan direview tanpa Form Evaluasi wajib aktif menggagalkan cek
    Given langkah 1 "Hibah Internal" direview dan Form Evaluasinya terpasang tidak wajib
    And "Periode 2026" menautkan "Hibah Internal" dengan semua tanggalnya diatur
    When Operator membuka Kesiapan Angkatan "Periode 2026"
    Then cek "Langkah yang direview punya Form Evaluasi wajib" gagal dengan "Langkah 1 (Pengajuan) direview, tapi belum punya Form Evaluasi wajib yang aktif."
    And peneliti "P-001" ditolak saat memulai pengajuan "Hibah Internal"

  @BR-FE-36 @M2 @OPERATOR-09-01 @happy
  Scenario: [FE-READY-06] Operator melihat daftar cek per angkatan; tabel periode menghitung angkatan yang belum siap
    Given "Periode 2026" menautkan "Hibah Internal" yang belum siap dan "Hibah Mandiri" yang siap
    When Operator membuka Kesiapan Angkatan "Periode 2026"
    Then ia melihat dua kartu angkatan, masing-masing dengan cek "Tanggal setiap langkah sudah diatur", "Urutan tanggal masuk akal", dan "Langkah yang direview punya Form Evaluasi wajib"
    And "Hibah Mandiri" berlencana "Siap"
    When Operator membuka daftar periode
    Then baris "Periode 2026" berlencana "1 angkatan belum siap"

  @BR-FE-36 @M2 @OPERATOR-09-01 @boundary
  Scenario: [FE-READY-07] Tanggal dan Form Evaluasi langkah monev yang belum diatur tidak menahan pengajuan baru
    Given "Periode 2026" menautkan "Hibah Internal" dengan langkah Monev I yang direview tanpa Form Evaluasi wajib
    And kalender "Periode 2026" belum punya Batas Laporan Monev I
    When Operator membuka Kesiapan Angkatan "Periode 2026"
    Then "Hibah Internal" berlencana "Siap — ada yang menyusul"
    And cek "Tanggal setiap langkah sudah diatur" memperingatkan "Batas Laporan Monev I belum diatur — langkah Laporan Monev I tertutup sampai tanggalnya diatur."
    And peneliti "P-001" bisa memulai pengajuan "Hibah Internal"
    And laporan Monev I ditolak dengan "Jadwal Laporan Monev I belum diatur"
