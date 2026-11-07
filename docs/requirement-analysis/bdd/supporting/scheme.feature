Feature: Skema Penelitian
  Skema membawa aturan yang mengikat pengajuan: batas anggaran, batas anggota,
  dan rule opsional di kolom JSONB. Skema sepenuhnya opsional per Form.

  @BR-SCH-03 @M0 @ADMIN-08-04 @denial
  Scenario: [SCH-RULE-01] Kode skema harus unik
    Given sudah ada skema dengan code "DIPA-2026"
    When Admin membuat skema baru dengan code "DIPA-2026"
    Then pembuatan ditolak dengan error validasi pada field code

  @BR-SCH-02 @M0 @ADMIN-08-04 @denial
  Scenario Outline: [SCH-RULE-02] max_budget dan max_members harus lebih dari nol
    When Admin membuat skema dengan max_budget <budget> dan max_members <members>
    Then pembuatan ditolak dengan error validasi

    Examples:
      | budget | members |
      | 0      | 5       |
      | -1     | 5       |
      | 100000 | 0       |
      | 100000 | -2      |

  @BR-SCH-02 @M0 @ADMIN-08-04 @happy
  Scenario: [SCH-RULE-03] Skema dengan nilai sah tersimpan
    When Admin membuat skema dengan max_budget 50000000 dan max_members 5
    Then skema tersimpan dan aktif

  @BR-SCH-01 @M0 @ADMIN-08-03 @denial
  Scenario: [SCH-RULE-04] Skema yang sedang dipakai tidak bisa dinonaktifkan
    Given skema "DIPA Internal" dipakai submission "P-001" yang masih aktif
    When Admin menonaktifkan skema "DIPA Internal"
    Then penonaktifan ditolak karena masih ada pengajuan aktif

  @BR-SCH-01 @M0 @ADMIN-08-03 @happy
  Scenario: [SCH-RULE-05] Skema tanpa pengajuan aktif bisa dinonaktifkan
    Given skema "DIPA Lama" tidak dipakai submission aktif mana pun
    When Admin menonaktifkan skema "DIPA Lama"
    Then skema "DIPA Lama" menjadi tidak aktif

  @BR-SCH-01 @M0 @ADMIN-08-03 @boundary
  Scenario: [SCH-RULE-06] Pengajuan yang sudah mati tidak menghalangi penonaktifan
    Given skema "DIPA Lama" hanya dipakai submission berstatus REJECTED dan WITHDRAWN
    When Admin menonaktifkan skema "DIPA Lama"
    Then skema "DIPA Lama" menjadi tidak aktif

  @BR-SCH-04 @M0 @RESEARCHER-02-07 @happy
  Scenario: [SCH-RULE-07] Form tanpa scheme_selector berjalan tanpa skema
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    When Lead Researcher membuat pengajuan pada Form itu
    Then submission terbentuk dengan scheme_id kosong

  @BR-SCH-04 @M0 @RESEARCHER-02-07 @happy
  Scenario: [SCH-RULE-08] Form dengan scheme_selector menulis ke kolom scheme_id
    Given Form "Pengajuan Penelitian" punya field bertipe scheme_selector
    When Lead Researcher memilih skema "DIPA Internal" di langkah 3
    Then scheme_id submission terisi skema "DIPA Internal"

  @BR-SCH-05 @M0 @RESEARCHER-02-07 @denial
  Scenario: [SCH-RULE-09] trl_selector tanpa scheme_selector ditolak
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    When Admin menambahkan field bertipe trl_selector ke Form itu
    Then penambahan ditolak karena trl_selector butuh scheme_selector di Form yang sama

  @BR-SCH-05 @M0 @RESEARCHER-02-07 @happy
  Scenario: [SCH-RULE-10] Pilihan TRL tersaring oleh skema terpilih
    Given skema "DIPA Internal" mengizinkan TRL 3, 4, dan 5
    And Lead Researcher sudah memilih skema "DIPA Internal"
    When Lead Researcher membuka pilihan TRL
    Then hanya TRL 3, 4, dan 5 yang tersedia

  @BR-SCH-08 @M0 @OPERATOR-09-01 @happy
  Scenario: [SCH-RULE-11] Perpanjangan deadline memberi tahu pemilik draft
    Given 7 peneliti punya submission DRAFT di periode berjalan
    When Operator memperpanjang SubmissionDate "Batas Submit" ke "2026-04-01"
    Then 7 peneliti tersebut menerima notifikasi perpanjangan

  @BR-SCH-08 @M0 @OPERATOR-09-01 @happy
  Scenario: [SCH-RULE-12] Deadline baru langsung berlaku sebagai gerbang akses
    Given deadline "Batas Submit" semula "2026-03-01 23:59"
    And waktu sekarang "2026-03-05 08:00"
    When Operator memperpanjang "Batas Submit" ke "2026-04-01 23:59"
    Then Lead Researcher bisa kembali mengisi fase yang terkait

  @BR-SCH-08 @M0 @OPERATOR-09-01 @boundary
  Scenario: [SCH-RULE-13] Tanpa draft berjalan, tidak ada notifikasi terkirim
    Given tidak ada submission DRAFT di periode berjalan
    When Operator memperpanjang SubmissionDate "Batas Submit" ke "2026-04-01"
    Then tidak ada notifikasi perpanjangan yang terkirim

  @BR-SCH-08 @M2 @OPERATOR-09-01 @boundary
  Scenario: [SCH-RULE-16] Menggeser Batas Evaluasi tidak memberi tahu pemilik draft
    Given 3 peneliti punya submission DRAFT di periode berjalan
    When Operator memundurkan SubmissionDate "Batas Evaluasi" ke "2026-04-15"
    Then tidak ada notifikasi perpanjangan yang terkirim

  @BR-SCH-10 @M0 @RESEARCHER-02-07 @boundary
  Scenario: [SCH-RULE-14] Skema tidak aktif tidak bisa dipilih pengajuan baru
    Given skema "DIPA Lama" berstatus tidak aktif
    When Lead Researcher membuka pilihan skema di langkah 3
    Then "DIPA Lama" tidak muncul sebagai pilihan

  @BR-SCH-01 @M0 @ADMIN-08-03 @denial
  Scenario: [SCH-RULE-15] Pengajuan yang disetujui masih menghalangi penonaktifan
    Given skema "DIPA Internal" hanya dipakai submission "P-001" berstatus APPROVED
    When Admin menonaktifkan skema "DIPA Internal"
    Then penonaktifan ditolak karena masih ada pengajuan aktif
    And skema "DIPA Internal" tetap aktif

  @BR-SCH-06 @M0 @ADMIN-08-04 @boundary
  Scenario: [SCH-RULE-17] Menyunting skema tanpa mengirim aturan tambahan tidak menghapus aturannya
    Given skema "DIPA Internal" punya aturan "min_reviewer_count" bernilai 3
    When Admin mengganti nama skema "DIPA Internal" tanpa mengubah aturan tambahannya
    Then aturan "min_reviewer_count" skema itu tetap 3
