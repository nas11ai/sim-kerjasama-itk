Feature: Penghapusan Struktur Form
  Form, tahapnya, periodenya, dan form evaluasinya adalah kerangka yang
  memayungi setiap pengajuan. Menghapusnya bukan merapikan daftar — ia
  mencabut tempat bergantung data yang sudah ada.

  Prinsipnya sama dengan yang berlaku di organisasi (BR-IAM-14) dan data acuan
  (BR-SC-01): nonaktifkan, dan tolak penghapusan selama masih ada yang
  merujuk. Penolakan wajib menyebut berapa banyak penghalangnya, karena
  Operator yang tidak tahu penghalangnya akan menebak.

  @BR-FE-19 @M0 @ADMIN-08-05 @denial
  Scenario: [FE-DELETE-01] Form yang punya pengajuan tidak bisa dihapus
    Given Form "Pengajuan Penelitian" punya 12 pengajuan
    When Admin menghapus Form "Pengajuan Penelitian"
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya
    And kedua belas pengajuan itu tetap utuh

  @BR-FE-19 @M0 @ADMIN-08-05 @boundary
  Scenario: [FE-DELETE-02] Draft pun menghalangi penghapusan Form
    Given Form "Pengajuan Penelitian" hanya punya 1 pengajuan berstatus DRAFT
    When Admin menghapus Form "Pengajuan Penelitian"
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya

  @BR-FE-19 @M0 @ADMIN-08-05 @boundary
  Scenario Outline: [FE-DELETE-03] Pengajuan berstatus terminal tetap menghalangi
    Given Form "Pengajuan Penelitian" hanya punya 1 pengajuan berstatus <status>
    When Admin menghapus Form "Pengajuan Penelitian"
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  @BR-FE-19 @M0 @ADMIN-08-05 @happy
  Scenario: [FE-DELETE-04] Form tanpa satu pun pengajuan bisa dihapus
    Given Form "Uji Coba" tidak punya pengajuan
    When Admin menghapus Form "Uji Coba"
    Then penghapusan berhasil

  @BR-FE-19 @M0 @ADMIN-08-05 @happy
  Scenario: [FE-DELETE-05] Form yang masih dipakai dinonaktifkan, bukan dihapus
    Given Form "Pengajuan Penelitian" punya 12 pengajuan
    When Admin menonaktifkan Form "Pengajuan Penelitian"
    Then Form itu berhenti menerima pengajuan baru
    And kedua belas pengajuan lama tetap terbaca

  @BR-FE-20 @M0 @ADMIN-08-08 @denial
  Scenario: [FE-DELETE-06] FormPhase yang dirujuk pengajuan tidak bisa dihapus
    Given FormPhase "Pengajuan" punya pengajuan yang merujuk salah satu detailnya
    When Admin menghapus FormPhase "Pengajuan"
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya

  @BR-FE-20 @M0 @ADMIN-08-09 @denial
  Scenario: [FE-DELETE-07] FormPhaseDetail yang dirujuk pengajuan tidak bisa dihapus
    Given FormPhaseDetail "Proposal Awal" dirujuk 3 pengajuan
    When Admin menghapus FormPhaseDetail "Proposal Awal"
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya

  @BR-FE-20 @M0 @ADMIN-08-09 @boundary
  Scenario: [FE-DELETE-08] Penolakan menyebut jumlah pengajuan yang menghalangi
    Given FormPhaseDetail "Proposal Awal" dirujuk 3 pengajuan
    When Admin menghapus FormPhaseDetail "Proposal Awal"
    Then pesan penolakan menyebut angka 3
    And pesan itu bukan galat basis data

  @BR-FE-20 @M0 @ADMIN-08-08 @happy
  Scenario: [FE-DELETE-09] FormPhase tanpa pengajuan bisa dihapus
    Given FormPhase "Uji Coba" tidak dirujuk pengajuan mana pun
    When Admin menghapus FormPhase "Uji Coba"
    Then penghapusan berhasil

  @BR-FE-20 @M0 @ADMIN-08-08 @denial
  Scenario: [FE-DELETE-15] FormPhase yang masih dipakai dinonaktifkan, bukan dihapus
    Given FormPhase "Pengajuan" punya 2 pengajuan
    When Admin menonaktifkan FormPhase "Pengajuan"
    Then FormPhase itu berhenti menerima pengajuan baru
    And kedua pengajuan lama tetap terbaca

  @BR-FE-21 @M0 @OPERATOR-09-01 @denial
  Scenario: [FE-DELETE-10] SubmissionPeriod yang punya pengajuan tidak bisa dihapus
    Given SubmissionPeriod "Gelombang I 2026" punya 8 pengajuan
    When Operator menghapus periode itu
    Then penghapusan ditolak karena masih ada pengajuan yang merujuknya

  @BR-FE-21 @BR-FE-07 @M0 @OPERATOR-09-01 @denial
  Scenario: [FE-DELETE-11] SubmissionDate yang jadi deadline sebuah detail tidak ikut terhapus
    Given SubmissionDate "Batas Proposal" dipakai FormPhaseDetail "Proposal Awal"
    When Operator menghapus periode pemilik tanggal itu
    Then penghapusan ditolak
    And "Proposal Awal" tetap punya deadline

  @BR-FE-21 @M0 @OPERATOR-09-01 @happy
  Scenario: [FE-DELETE-12] Periode tanpa pengajuan bisa dihapus
    Given SubmissionPeriod "Gelombang Uji" tidak punya pengajuan
    When Operator menghapus periode itu
    Then penghapusan berhasil

  @BR-FE-22 @M2 @denial
  Scenario: [FE-DELETE-13] Form evaluasi yang pernah ditugaskan tidak bisa dihapus
    Given ReviewEvaluationForm "Substansi" pernah ditugaskan ke seorang reviewer
    When Admin menghapus form evaluasi itu
    Then penghapusan ditolak karena masih ada penugasan yang merujuknya

  @BR-FE-22 @M2 @happy
  Scenario: [FE-DELETE-14] Form evaluasi yang belum pernah dipakai bisa dihapus
    Given ReviewEvaluationForm "Draf Lama" belum pernah ditugaskan
    When Admin menghapus form evaluasi itu
    Then penghapusan berhasil
