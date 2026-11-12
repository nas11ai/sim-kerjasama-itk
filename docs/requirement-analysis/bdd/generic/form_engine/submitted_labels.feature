Feature: Rekaman Teks Pertanyaan dan Opsi saat Submit
  Field Form tidak berversi: Terbitkan memperbarui field yang sama (BR-FE-34), jadi
  label yang diubah, opsi yang diganti, atau field yang dihapus sesudah submit membuat
  pengajuan lama terbaca dengan pertanyaan yang tidak pernah dilihat pengisinya. Karena
  itu teks pertanyaan dan label opsi direkam saat submit dan kirim ulang (BR-FE-35);
  nilai jawabannya tetap dibaca dari jawaban yang tersimpan.

  @BR-FE-35 @M2 @RESEARCHER-02-03 @happy
  Scenario: [FE-SNAP-01] Label field dan opsi yang diubah sesudah submit tidak mengubah yang terbaca
    Given peneliti "P-001" mensubmit pengajuan dengan field "Anggaran" dijawab opsi "Mandiri"
    And Admin menerbitkan Form dengan label "Total Anggaran" dan opsi "Swadana"
    When Operator membuka pengajuan "P-001"
    Then ia membaca "Anggaran" dengan jawaban "Mandiri"

  @BR-FE-35 @M2 @RESEARCHER-02-03 @boundary
  Scenario: [FE-SNAP-02] Draf memakai teks terkini
    Given peneliti "P-001" punya draf dengan field "Anggaran" dijawab opsi "Mandiri"
    And Admin menerbitkan Form dengan label "Total Anggaran" dan opsi "Swadana"
    When peneliti "P-001" membuka drafnya
    Then ia membaca "Total Anggaran" dengan jawaban "Swadana"

  @BR-FE-35 @M2 @RESEARCHER-02-03 @boundary
  Scenario: [FE-SNAP-03] Field yang dihapus sesudah submit tetap terbaca; field yang ditambah sesudahnya tidak muncul
    Given peneliti "P-001" mensubmit pengajuan dengan field "Lokasi" dijawab "Balikpapan"
    And Admin menerbitkan Form yang menghapus field "Lokasi" dan menambah field "Mitra"
    When Operator membuka pengajuan "P-001"
    Then ia membaca "Lokasi" dengan jawaban "Balikpapan"
    And ia tidak melihat field "Mitra"

  @BR-FE-35 @M2 @RESEARCHER-02-03 @boundary
  Scenario: [FE-SNAP-04] Selama revisi teks terkini; kirim ulang merekam teks saat itu
    Given pengajuan "P-001" disubmit dengan field "Anggaran" lalu dikembalikan untuk revisi
    And Admin menerbitkan Form dengan label "Total Anggaran"
    When peneliti "P-001" membuka pengajuannya
    Then ia membaca "Total Anggaran"
    When peneliti "P-001" mengirim ulang pengajuannya
    And Admin menerbitkan Form dengan label "Anggaran Usulan"
    Then Operator membaca "Total Anggaran" di pengajuan "P-001"

  @BR-FE-35 @M2 @RESEARCHER-02-03 @boundary
  Scenario: [FE-SNAP-05] Pengajuan yang disubmit sebelum fitur ini memakai teks terkini
    Given pengajuan "P-001" disubmit sebelum rekaman teks ada
    And Admin menerbitkan Form dengan label "Total Anggaran"
    When Operator membuka pengajuan "P-001"
    Then ia membaca "Total Anggaran"
