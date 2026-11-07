Feature: Notifikasi
  Notifikasi in-app tersimpan permanen dan hanya hilang kalau user menghapusnya
  sendiri. Channel per tipe event adalah konfigurasi, bukan konstanta kode
  (BR-NOT-06) — dan pengiriman bersifat fire-and-forget: kegagalannya tidak boleh
  menyandera proses bisnis.

  @BR-NOT-01 @M5 @SHARED-02-05 @happy
  Scenario: [NOT-INAPP-01] Notifikasi in-app tersimpan permanen
    Given "Budi" punya notifikasi in-app yang belum dibaca
    When "Budi" membaca notifikasi itu
    Then notifikasi itu tetap ada di daftar

  @BR-NOT-01 @M5 @SHARED-02-05 @happy
  Scenario: [NOT-INAPP-02] User menghapus notifikasinya sendiri
    Given "Budi" punya notifikasi in-app
    When "Budi" menghapus notifikasi itu
    Then notifikasi itu hilang dari daftar

  @BR-NOT-04 @M5 @SHARED-02-05 @boundary
  Scenario: [NOT-INAPP-03] read_at terisi saat notifikasi diklik
    Given "Budi" punya notifikasi in-app yang belum dibaca
    When "Budi" membuka halaman daftar notifikasi
    Then read_at notifikasi itu masih kosong
    And penghitung belum-dibaca "Budi" tetap 1

  @BR-NOT-04 @M5 @SHARED-02-05 @happy
  Scenario: [NOT-INAPP-04] User menandai semua notifikasinya dibaca
    Given "Budi" punya dua notifikasi in-app yang belum dibaca
    And "Dewi" punya satu notifikasi in-app yang belum dibaca
    When "Budi" menandai semua notifikasinya dibaca
    Then penghitung belum-dibaca "Budi" menjadi 0
    And kedua notifikasi "Budi" tetap ada di daftar
    And penghitung belum-dibaca "Dewi" tetap 1

  @BR-NOT-04 @M5 @SHARED-02-05 @happy
  Scenario: [NOT-INAPP-05] Membuka notifikasi membawa ke halaman yang relevan
    Given reviewer "Rina" ditugaskan mengevaluasi "P-001"
    And "Rina" punya notifikasi ReviewerAssigned untuk "P-001" yang belum dibaca
    When "Rina" membuka notifikasi itu
    Then read_at notifikasi itu terisi
    And "Rina" dibawa ke form evaluasi "P-001"

  @BR-NOT-04 @M5 @SHARED-02-05 @denial
  Scenario: [NOT-INAPP-06] Notifikasi yang pengajuannya tak lagi bisa diakses tidak membuka datanya
    Given reviewer "Rina" punya notifikasi ReviewerAssigned untuk "P-001" yang belum dibaca
    And penugasan "Rina" pada "P-001" sudah diganti
    When "Rina" membuka notifikasi itu
    Then read_at notifikasi itu terisi
    And "Rina" tetap di daftar notifikasi
    And "Rina" tidak dibawa ke data "P-001"

  @BR-NOT-02 @M5 @SHARED-02-01 @happy
  Scenario: [NOT-CHANNEL-01] ProposalApproved terkirim in-app dan email
    When event ProposalApproved dipublish untuk "Budi"
    Then "Budi" menerima notifikasi in-app
    And "Budi" menerima notifikasi email

  @BR-NOT-02 @M5 @SHARED-02-01 @boundary
  Scenario: [NOT-CHANNEL-02] RevisionResolved cukup in-app tanpa email
    When event RevisionResolved dipublish untuk "Budi"
    Then "Budi" menerima notifikasi in-app
    And "Budi" tidak menerima notifikasi email

  @BR-NOT-06 @M5 @ADMIN-08-10 @happy
  Scenario: [NOT-CHANNEL-03] Channel event diubah lewat konfigurasi tanpa deploy
    Given event RevisionResolved dideklarasikan hanya in-app
    When admin mengubah channel RevisionResolved menjadi in-app dan email
    And event RevisionResolved dipublish untuk "Budi"
    Then "Budi" menerima notifikasi email

  @BR-NOT-06 @M5 @ADMIN-08-10 @denial
  Scenario: [NOT-CHANNEL-04] Event tanpa deklarasi channel ditolak saat dipublish
    Given event ProposalArchived belum punya deklarasi channel
    When event ProposalArchived dipublish untuk "Budi"
    Then publikasi ditolak karena event belum punya deklarasi channel

  @BR-NOT-03 @M5 @RESEARCHER-02-02 @boundary
  Scenario: [NOT-DELIVERY-01] Kegagalan notifikasi tidak menggagalkan aksi bisnis
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And kanal email sedang tidak bisa dihubungi
    When "Budi" submit "P-001"
    Then submit berhasil
    And status "P-001" menjadi SUBMITTED

  @BR-NOT-02 @M5 @SHARED-02-05 @boundary
  Scenario: [NOT-CHANNEL-05] Satu event bisa punya lebih dari satu penerima
    Given event MonevStageOpened dideklarasikan in-app dan email
    When event MonevStageOpened dipublish untuk "P-001"
    Then "Budi" menerima notifikasi in-app
    And reviewer siklus itu menerima notifikasi in-app

  @BR-NOT-02 @M5 @SHARED-02-05 @denial
  Scenario: [NOT-CHANNEL-06] Penerima yang tidak berkepentingan tidak dikirimi
    Given Operator "Dewi" meminta ekspor data submission
    When ekspor itu selesai
    Then "Dewi" menerima notifikasi in-app
    And "Budi" tidak menerima notifikasi in-app

  @BR-NOT-06 @M5 @ADMIN-08-10 @denial
  Scenario: [NOT-CHANNEL-07] Deklarasi channel tidak boleh kosong
    Given event ProposalSubmitted dideklarasikan in-app dan email
    When admin mengosongkan seluruh channel ProposalSubmitted
    Then penyimpanan ditolak
    And deklarasi in-app dan email tetap berlaku
