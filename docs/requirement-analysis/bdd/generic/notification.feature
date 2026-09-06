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
