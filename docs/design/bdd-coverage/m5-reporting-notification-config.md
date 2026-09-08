# M5 — Reporting, Notification & System Configuration

**19 BR bertag · 38 skenario · 5 berkas `.feature`**

Dua BR baru dan satu katalog yang diselaraskan, semuanya dari audit DDD:

| Perubahan | Lahir dari | Register |
| --------- | ---------- | -------- |
| **BR-RPT-11** *(baru)* | Alur *Cetak Dokumen PDF* digambar lengkap di DDD — empat jenis dokumen, ambang sinkron/asinkron — tapi tidak pernah dinomori maupun punya skenario | — |
| **BR-RPT-12** *(baru)* | *Data Retention Policy* menyebut berkas ekspor dihapus otomatis setelah 24 jam; yang sudah diuji baru masa berlaku signed URL-nya (BR-RPT-03), dan keduanya menghasilkan penolakan yang berbeda | — |
| Katalog event *(diselaraskan)* | Tiga baris menyebut satu penerima padahal aturannya menuntut dua; tujuh event dari M3/M4 tidak pernah didaftarkan | #36 |

`document_print.feature` adalah berkas kelima. Ia dipisah dari
`reporting.feature` karena temanya memang berbeda: ekspor tabular menjawab
"berapa banyak", cetak menjawab "siapa boleh membaca isi yang mana" — dan
sumbu kedua itu yang membuat `RPT-PRINT-03` ada.

Lapisan yang menyentuh seluruh sistem tapi tidak memiliki alur bisnisnya sendiri:
laporan dan ekspor, pemberitahuan, jejak audit, dan konfigurasi acuan.

Sumber: [`supporting/05_reporting.md`](../../requirement-analysis/ddd/supporting/05_reporting.md),
[`generic/03_notification.md`](../../requirement-analysis/ddd/generic/03_notification.md),
[`generic/05_system_configuration.md`](../../requirement-analysis/ddd/generic/05_system_configuration.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `supporting/reporting.feature` | `RPT-EXPORT-01..09` | BR-RPT-02, BR-RPT-03, BR-RPT-07, BR-RPT-08, BR-RPT-10 |
| `supporting/audit.feature` | `RPT-AUDIT-01..07` | BR-RPT-04, BR-RPT-05, BR-RPT-09 |
| `generic/notification.feature` | `NOT-INAPP-01..03`, `NOT-CHANNEL-01..04`, `NOT-DELIVERY-01` | BR-NOT-01, BR-NOT-02, BR-NOT-03, BR-NOT-04, BR-NOT-06 |
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
| `RPT-EXPORT-08` | happy | Researcher mengekspor data submission miliknya sendiri | BR-RPT-10 | RESEARCHER-02-01 |
| `RPT-EXPORT-09` | denial | Ekspor Researcher tidak memuat submission milik orang lain — scope sama persis dengan BR-RPT-05 | BR-RPT-10 | RESEARCHER-02-01 |

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
| `RPT-AUDIT-06` | happy | Menjalankan sebuah Action menghasilkan entri audit berisi aktor, waktu, dan nilai sebelum–sesudah | BR-RPT-09 | OPERATOR-07-03 |
| `RPT-AUDIT-07` | boundary | Cakupan audit diturunkan dari arsitektur, bukan daftar manual — Action mana pun yang mengubah state ikut tercatat | BR-RPT-09 | OPERATOR-07-03 |

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
| `NOT-CHANNEL-03` | happy | Channel sebuah event diubah Admin lewat konfigurasi dan langsung berlaku — tanpa deploy | BR-NOT-06 | ADMIN-08-10 |
| `NOT-CHANNEL-04` | denial | Mempublish event yang **belum punya deklarasi channel** ditolak — tidak ada default diam-diam | BR-NOT-06 | ADMIN-08-10 |
| `NOT-DELIVERY-01` | boundary | Kegagalan mengirim notifikasi tidak menggagalkan aksi bisnis yang memicunya | BR-NOT-03 | RESEARCHER-02-02 |

`NOT-CHANNEL-03/04` mengunci BR-NOT-06: daftar event mana yang perlu email adalah
**konfigurasi, bukan konstanta kode**. `NOT-CHANNEL-01/02` menguji dua contoh yang
disebut eksplisit di DDD; `NOT-CHANNEL-03` menguji bahwa isinya bisa berubah tanpa
deploy, dan `NOT-CHANNEL-04` menjaga tidak ada event yang lolos tanpa deklarasi. Dengan
begitu "daftar lengkapnya apa" berhenti jadi pertanyaan yang harus dijawab di muka.

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
| ~~#21~~ | **Terselesaikan 2026-09-04** — channel jadi konfigurasi (**BR-NOT-06**); daftarnya bukan aturan. Dikunci `[NOT-CHANNEL-03/04]` |
| ~~#22~~ | **Terselesaikan 2026-09-04** — setiap Action mencatat audit (**BR-RPT-09**); cakupan diturunkan dari arsitektur. Dikunci `[RPT-AUDIT-06/07]` + penjagaan struktural |
| ~~#23~~ | **Terselesaikan 2026-09-04** — boleh, ter-scope sama persis dengan BR-RPT-05 (**BR-RPT-10**). Dikunci `[RPT-EXPORT-08/09]` |
