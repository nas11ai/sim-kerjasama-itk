Feature: Versi Form Evaluasi yang Dipaku per Angkatan
  Form Evaluasi adalah pustaka berversi: setiap Terbitkan membentuk versi baru yang
  tidak pernah berubah lagi. Satu angkatan × langkah × Form Evaluasi dinilai dengan satu
  versi — versi itu dipaku saat draf penilaian pertama di sana tersimpan (BR-REV-18).
  Tanpa pemakuan, dua reviewer pada pengajuan yang setara bisa menilai dengan lembar yang
  berbeda hanya karena satu di antaranya mulai sesudah Admin menerbitkan versi baru.

  Background:
    Given tahap "Penelitian DIPA" ditautkan ke periode "2025" dan "2026"
    And langkah Pengajuan tahap itu memasang "Lembar Proposal" yang sudah terbit Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-VER-01] Sebelum penilaian pertama, reviewer melihat versi terbit terbaru
    Given Admin menerbitkan "Lembar Proposal" Versi 2
    And belum ada reviewer yang menyimpan draf "Lembar Proposal" di angkatan "2025"
    When "Sari" membuka lembar penilaian "P-001" di angkatan "2025"
    Then "Sari" melihat field "Lembar Proposal" Versi 2
    And belum ada versi yang dipaku untuk angkatan itu

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-02] Reviewer yang ditugaskan belakangan memakai versi pakuan
    Given "Sari" menyimpan draf penilaian "P-001" pada "Lembar Proposal" Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Dodi" ditugaskan ke "P-002" di angkatan yang sama dan membuka lembarnya
    Then "Dodi" mengisi "Lembar Proposal" Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-03] Pemakuan berlaku per langkah
    Given langkah "Laporan Monev I" tahap itu juga memasang "Lembar Proposal"
    And "Sari" menyimpan draf penilaian proposal "P-001" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Sari" membuka lembar penilaian Laporan Monev I "P-001"
    Then "Sari" melihat field "Lembar Proposal" Versi 2
    And lembar proposal angkatan itu tetap Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @boundary
  Scenario: [REV-VER-04] Angkatan lain pada fase yang sama memakai versi terbaru
    Given "Sari" menyimpan draf penilaian "P-001" di angkatan "2025" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When "Dodi" menyimpan draf penilaian "P-101" di angkatan "2026"
    Then angkatan "2026" memakai Versi 2
    And angkatan "2025" tetap memakai Versi 1

  @BR-REV-18 @M2 @REVIEWER-03-03 @happy
  Scenario: [REV-VER-05] Versi baru tidak mengubah field versi lama
    Given "Sari" mengirim penilaian "P-001" dengan field "Kebaruan" Versi 1
    When Admin menerbitkan Versi 2 yang mengganti label field itu menjadi "Orisinalitas"
    Then lembar "Sari" tetap terbaca dengan label "Kebaruan"
    And field Versi 1 tidak bisa diubah

  # BR-REV-19 — Operator menerapkan versi terbaru ke satu angkatan × langkah secara sadar.
  # Pengecualian atas BR-REV-08: jawaban lama tidak diedit, melainkan menjadi riwayat.

  @BR-REV-19 @M2 @OPERATOR-09-01 @happy
  Scenario: [REV-VER-06] Terapkan versi baru membuka ulang penilaian yang sudah disubmit
    Given "Sari" mengirim penilaian "P-001" di angkatan "2025" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    And belum ada pengajuan di langkah Pengajuan angkatan "2025" yang diputuskan
    When Operator menerapkan Versi 2 ke angkatan "2025"
    Then penilaian "Sari" atas "P-001" dibuka ulang pada Versi 2
    And jawaban Versi 1 "Sari" tetap terbaca Operator sebagai "Riwayat · Versi 1"

  @BR-REV-19 @M2 @OPERATOR-09-01 @denial
  Scenario: [REV-VER-07] Terapkan ditolak bila ada pengajuan di langkah itu yang sudah diputuskan
    Given "Sari" mengirim penilaian "P-001" di angkatan "2025" pada Versi 1
    And "P-002" di angkatan "2025" berstatus NEEDS_REVISION
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When Operator menerapkan Versi 2 ke angkatan "2025"
    Then Operator melihat galat "Tidak bisa diterapkan: 1 pengajuan di langkah ini sudah diputuskan."
    And angkatan "2025" tetap memakai Versi 1
    And penilaian "Sari" tetap terkirim

  @BR-REV-19 @M2 @OPERATOR-09-01 @happy
  Scenario: [REV-VER-08] Reviewer yang penilaiannya dibuka ulang diberi tahu
    Given "Sari" mengirim penilaian "P-001" dan "Dodi" menyimpan draf penilaian "P-002" di angkatan "2025"
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When Operator menerapkan Versi 2 ke angkatan "2025"
    Then "Sari" dan "Dodi" masing-masing menerima notifikasi "Penilaian perlu diisi ulang"

  @BR-REV-19 @M2 @OPERATOR-09-01 @boundary
  Scenario: [REV-VER-09] Terapkan di satu angkatan tidak menyentuh angkatan lain
    Given "Sari" mengirim penilaian "P-001" di angkatan "2025" pada Versi 1
    And "Dodi" mengirim penilaian "P-101" di angkatan "2026" pada Versi 1
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When Operator menerapkan Versi 2 ke angkatan "2025"
    Then angkatan "2026" tetap memakai Versi 1
    And penilaian "Dodi" tetap terkirim

  # K-3 (b), pemilik 2026-11-12 — Terapkan tidak menggeser Batas Evaluasi; Operator diperingatkan
  # dan memberi perpanjangan per pengajuan (BR-FE-27).
  @BR-REV-19 @M2 @OPERATOR-09-01 @boundary
  Scenario: [REV-VER-10] Pengajuan yang Batas Evaluasinya lewat disebut sebelum dan sesudah Terapkan
    Given "Sari" mengirim penilaian "P-001" di angkatan "2025" pada Versi 1
    And Batas Evaluasi penugasan "Sari" atas "P-001" sudah lewat
    And Admin menerbitkan "Lembar Proposal" Versi 2
    When Operator membuka konfirmasi Terapkan Versi 2 ke angkatan "2025"
    Then Operator melihat peringatan "1 pengajuan sudah lewat Batas Evaluasi" bertaut ke "P-001"
    When Operator menerapkan Versi 2 ke angkatan "2025"
    Then Batas Evaluasi penugasan "Sari" tidak berubah dan ia belum bisa mengisi ulang
    And "P-001" tetap disebut sampai Operator memberi perpanjangan
