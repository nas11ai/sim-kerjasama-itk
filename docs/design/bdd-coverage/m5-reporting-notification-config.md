# M5 — Reporting, Notification & System Configuration

**13 BR perilaku · 22 skenario · 4 berkas `.feature`**

Lapisan yang menyentuh seluruh sistem tapi tidak memiliki alur bisnisnya sendiri:
laporan dan ekspor, pemberitahuan, jejak audit, dan konfigurasi acuan.

Sumber: [`supporting/05_reporting.md`](../../requirement-analysis/ddd/supporting/05_reporting.md),
[`generic/03_notification.md`](../../requirement-analysis/ddd/generic/03_notification.md),
[`generic/05_system_configuration.md`](../../requirement-analysis/ddd/generic/05_system_configuration.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `supporting/reporting.feature` | `RPT-EXPORT-01..07` | BR-RPT-02, BR-RPT-03, BR-RPT-07, BR-RPT-08 |
| `supporting/audit.feature` | `RPT-AUDIT-01..05` | BR-RPT-04, BR-RPT-05 |
| `generic/notification.feature` | `NOT-INAPP-01..03`, `NOT-CHANNEL-01..02`, `NOT-DELIVERY-01` | BR-NOT-01, BR-NOT-02, BR-NOT-03, BR-NOT-04 |
| `generic/system_config.feature` | `SC-CONFIG-01..04` | BR-SC-01, BR-SC-02, BR-SC-03 |

Semua skenario bertag `@M5`.

---

## 2. Ekspor

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `RPT-EXPORT-01` | happy | Ekspor ≤ 100 baris selesai langsung dan berkasnya bisa diunduh | BR-RPT-02 | OPERATOR-07-02 |
| `RPT-EXPORT-02` | boundary | Ekspor > 100 baris masuk background job; Operator tidak menunggu di layar | BR-RPT-02 | OPERATOR-07-02 |
| `RPT-EXPORT-03` | happy | Berkas hasil ekspor diakses lewat signed URL yang berlaku 24 jam | BR-RPT-03 | OPERATOR-07-02 |
| `RPT-EXPORT-04` | denial | Signed URL yang sudah lewat 24 jam ditolak | BR-RPT-03 | OPERATOR-07-02 |
| `RPT-EXPORT-05` | happy | `ExportCompleted` memicu notifikasi in-app ke Operator yang meminta ekspor itu — bukan ke semua Operator | BR-RPT-07 | OPERATOR-07-02 |
| `RPT-EXPORT-06` | boundary | Job yang gagal diulang maksimal tiga kali dengan jeda menaik | BR-RPT-08 | OPERATOR-07-02 |
| `RPT-EXPORT-07` | happy | Setelah percobaan ketiga habis, Operator peminta menerima notifikasi kegagalan | BR-RPT-08 | OPERATOR-07-02 |

`RPT-EXPORT-02` memakai ambang 100 baris apa adanya dari BR-RPT-02. Angka itu **ambang
keputusan**, bukan detail implementasi — kalau berubah, BR-nya yang diubah, bukan
skenarionya ditulis kabur.

`RPT-EXPORT-05` menegaskan penerimanya spesifik. Notifikasi ekspor yang disiarkan ke
semua Operator adalah kebocoran kecil yang mengganggu setiap hari.

---

## 3. Audit log

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `RPT-AUDIT-01` | happy | Aksi yang mengubah state menambah entri audit log | BR-RPT-04 | OPERATOR-07-03 |
| `RPT-AUDIT-02` | denial | Menghapus entri audit log ditolak — log hanya bisa bertambah | BR-RPT-04 | OPERATOR-07-03 |
| `RPT-AUDIT-03` | denial | Menyunting entri audit log yang sudah ada ditolak | BR-RPT-04 | OPERATOR-07-03 |
| `RPT-AUDIT-04` | happy | Operator melihat audit log seluruh sistem | BR-RPT-05 | OPERATOR-07-03 |
| `RPT-AUDIT-05` | denial | Researcher hanya melihat audit log submission miliknya, bukan milik orang lain | BR-RPT-05 | RESEARCHER-07-01 |

BR-RPT-04 berbunyi "append-only", dan itu punya **dua** cara dilanggar: dihapus dan
disunting. `RPT-AUDIT-02` dan `RPT-AUDIT-03` menutup keduanya — implementasi yang hanya
melarang `DELETE` tetap membiarkan `UPDATE` menulis ulang sejarah.

---

## 4. Notifikasi

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `NOT-INAPP-01` | happy | Notifikasi in-app tersimpan permanen; tidak hilang sendiri setelah dibaca | BR-NOT-01 | SHARED-02-05 |
| `NOT-INAPP-02` | happy | User menghapus notifikasinya sendiri secara manual | BR-NOT-01 | SHARED-02-05 |
| `NOT-INAPP-03` | boundary | `read_at` terisi saat notifikasi **diklik**, bukan saat halaman daftar dibuka | BR-NOT-04 | SHARED-02-05 |
| `NOT-CHANNEL-01` | happy | `ProposalApproved` terkirim in-app **dan** email | BR-NOT-02 | SHARED-02-01 |
| `NOT-CHANNEL-02` | boundary | `RevisionResolved` dan `ProposalWithdrawn` cukup in-app, tanpa email | BR-NOT-02 | SHARED-02-01 |
| `NOT-DELIVERY-01` | boundary | Kegagalan mengirim notifikasi tidak menggagalkan aksi bisnis yang memicunya | BR-NOT-03 | RESEARCHER-02-02 |

`NOT-INAPP-03` terlihat sepele tapi menentukan angka yang dilihat semua orang setiap
hari: kalau `read_at` diisi saat halaman dibuka, penghitung belum-dibaca akan selalu
nol dan notifikasi kehilangan gunanya.

`NOT-DELIVERY-01` adalah skenario integritas yang paling mudah dilupakan. Bentuknya:
kanal email sedang mati, researcher submit proposal, submission **tetap** berpindah ke
SUBMITTED. Notifikasi bersifat fire-and-forget — ia tidak boleh memegang sandera proses
bisnis.

---

## 5. Konfigurasi sistem

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `SC-CONFIG-01` | denial | Entity acuan tidak bisa dihapus selama masih dipakai relasi aktif | BR-SC-01 | ADMIN-08-10 |
| `SC-CONFIG-02` | happy | Entity acuan tanpa relasi aktif bisa dihapus | BR-SC-01 | ADMIN-08-10 |
| `SC-CONFIG-03` | denial | `SubmissionType` tidak bisa ditambah atau diubah lewat antarmuka — hanya lewat seeder | BR-SC-02 | ADMIN-08-10 |
| `SC-CONFIG-04` | boundary | Mengganti nama entity acuan tidak mengubah data historis yang sudah menunjuk ke sana | BR-SC-03 | ADMIN-08-10 |

`SC-CONFIG-04` menjaga hal yang baru terasa setelah bertahun-tahun: laporan tahun lalu
harus tetap menampilkan nama yang berlaku saat itu, bukan nama hasil penggantian
kemarin. Kalau ternyata LPPM justru menghendaki nama selalu ikut berubah, itu keputusan
yang harus diambil sadar — bukan efek samping cara data disimpan.

---

## 6. Statistik dan dashboard

BR-RPT-01 (reporting read-only) dan BR-RPT-06 (statistik live query, tanpa cache) ada
di [`non-behavioral.md`](../../requirement-analysis/bdd/non-behavioral.md) — keduanya
keputusan arsitektur tanpa aksi yang bisa diamati.

Frame OPERATOR-07-01 (Dashboard Statistik) karenanya **tidak** punya skenario sendiri.
Ia tercakup tidak langsung: angka yang ditampilkannya berasal dari submission yang
sudah dijaga skenario M1 dan M2. Kalau nanti muncul aturan bisnis khusus dashboard —
misalnya "Operator hanya melihat statistik organisasinya" — aturan itu ditambahkan ke
DDD lebih dulu.

---

## 7. Frame yang tercakup

OPERATOR-07-02 · OPERATOR-07-03 · RESEARCHER-07-01 · ADMIN-08-10 · SHARED-02-01 ·
SHARED-02-02 · SHARED-02-03 · SHARED-02-04 · SHARED-02-05

Empat frame dashboard (SHARED-02-01..04) tercakup lewat skenario notifikasi dan
lewat skenario akses di M1–M3. Tidak ada aturan bisnis yang khusus milik dashboard.

---

## 8. Yang menunggu keputusan

| # | Perkara | Dampak |
| - | ------- | ------ |
| [#21](../../requirement-analysis/bdd/open-questions.md) | Daftar lengkap event "butuh perhatian segera" versus cukup in-app | `NOT-CHANNEL-01/02` baru menguji empat event yang disebut eksplisit |
| [#22](../../requirement-analysis/bdd/open-questions.md) | Aksi apa saja yang wajib masuk audit log | BR-RPT-04 mengatur sifat log, bukan cakupannya — "append-only" menjaga sesuatu yang batasnya belum jelas |
| [#23](../../requirement-analysis/bdd/open-questions.md) | Researcher boleh mengekspor data miliknya sendiri? | BR-RPT-02/03 menyebut Operator; wireframe hanya punya OPERATOR-07-02 |
