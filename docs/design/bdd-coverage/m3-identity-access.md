# M3 — Identity & Access

**13 BR bertag · 58 skenario · 7 berkas `.feature`**

BC Identity & Access punya 15 business rule perilaku; dua di antaranya (BR-IAM-07, BR-IAM-10) dibuktikan di milestone lain sebagai aturan cermin.

Gerbang masuk sistem: dua jalur registrasi, verifikasi NIDN yang bisa berakhir ditolak,
permission additive, organisasi yang dinonaktifkan alih-alih dihapus, dan deaktivasi yang
tidak boleh meninggalkan submission yatim.

Sumber: [`generic/02_identity_access.md`](../../requirement-analysis/ddd/generic/02_identity_access.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `generic/identity/registration.feature` | `IAM-REG-01..19` | BR-IAM-01, BR-IAM-02, BR-IAM-06, BR-IAM-12, BR-IAM-14 |
| `generic/identity/verification.feature` | `IAM-VERIFY-01..12` | BR-IAM-03, BR-IAM-04, BR-IAM-13, BR-IAM-15 |
| `generic/identity/permission.feature` | `IAM-PERM-01..04` | BR-IAM-09 |
| `generic/identity/deactivation.feature` | `IAM-DEACT-01..04` | BR-IAM-05 |
| `generic/identity/organization.feature` | `IAM-ORG-01..08` | BR-IAM-08, BR-IAM-14 |
| `generic/identity/reviewer_provisioning.feature` | `IAM-REVIEWER-01..06` | BR-IAM-11 |
| `generic/identity/account_events.feature` | `IAM-EVENT-01..05` | BR-IAM-15 |

Semua skenario bertag `@M3`.

Dua BR **tidak** melahirkan skenario di sini:

- **BR-IAM-10** — kanonik untuk transfer ownership; dibuktikan `[SM-OWNER-01..04]` di M1,
  bertag bersama cerminnya BR-SM-09.
- **BR-IAM-07** — bagian dari BR-REV-02; dibuktikan `[REV-ASSIGN-07]` di M2.

Menulis ulang keduanya sebagai aturan tersendiri berarti dua implementasi untuk satu
aturan.

**BR-IAM-09** sudah lunas sejak Wave 1a lewat `[FE-ACCESS-06]`. Ia tetap punya berkas
sendiri karena `IAM-PERM-01..04` memperdalam kasusnya, bukan membuktikannya pertama kali.

`registration.feature` berisi 19 skenario — mendekati ambang ~25 yang memaksa pemecahan.
Garis pisahnya sudah kelihatan kalau nanti dibutuhkan: aturan domain dan NIDN
(`IAM-REG-01/02/04/05/12/15/18/19`) versus token undangan
(`IAM-REG-03/06..11/13/14/16/17`). Rentang ID tidak perlu berurutan dengan nama berkas,
jadi memecahnya tidak menomori ulang apa pun.

---

## 2. Empat BR baru dan lima amandemen, semuanya dari wireframe

Empat BR baru dan lima amandemen di M3 tidak lahir dari membaca DDD sekali lagi. Semuanya
lahir dari membaca DDD **berdampingan dengan wireframe**, dan menemukan tempat di mana
salah satunya tahu sesuatu yang tidak pernah ditulis di aturan.

| BR | Lahir dari | Register |
| -- | ---------- | -------- |
| **BR-IAM-12** *(baru)* | DDD menggambar `Status: active langsung` untuk jalur undangan; BDD menuliskan `pending` | #29 |
| **BR-IAM-13** *(baru)* | `OPERATOR-08-02` punya tombol **Tolak** dan menuntut alasan; tidak ada BR-nya | #30 |
| **BR-IAM-14** *(baru)* | `ADMIN-08-02` menawarkan **Nonaktifkan**, bukan Hapus; agregat `Organization` sudah punya `is_active` | #31 |
| **BR-IAM-15** *(baru)* | Tabel Domain Events mendaftar empat event ke Notification; tidak satu pun pernah diuji | — |
| BR-IAM-02 *(amandemen)* | Jalur undangan tidak pernah meminta NIDN | #32 |
| BR-IAM-06 *(amandemen)* | Tombol **Cabut** di `ADMIN-08-01` | #33 |
| BR-IAM-11 *(amandemen)* | `OPERATOR-08-04` menampilkan satu baris per reviewer dengan *Edit Periode* | #34 |
| BR-IAM-01 *(amandemen)* | Pencocokan domain tidak pernah dinyatakan persis atau berakhiran | — |
| BR-IAM-05 *(amandemen)* | `[IAM-DEACT-02]` menguji "tidak bisa login", yang tidak pernah disebut BR-nya | — |

Dua amandemen terakhir tidak masuk register karena bukan kontradiksi: keduanya aturan
yang **sudah diandaikan skenario yang ada** tapi tidak pernah ditulis. BR-IAM-05 contoh
paling telanjang — `[IAM-DEACT-02]` sudah bertag `@BR-IAM-05` sejak ditulis, padahal
BR-IAM-05 cuma berbunyi "tidak delete data, `is_active = false`" dan tidak pernah
menyatakan apa arti flag itu bagi kemampuan orangnya bertindak.

---

## 3. Registrasi — dua jalur, empat bekal

Hanya ada **dua** cara sebuah akun lahir. DDD memuat "Jalur 3 — Reviewer (Ditunjuk
Operator)", tapi membacanya sampai habis menunjukkan itu **bukan** pembuatan akun:
alurnya berbunyi "Cari user by NIDN atau nama → Pilih user yang **sudah ada**". Jalur 3
mempromosikan akun yang sudah terdaftar, dan itu diatur terpisah di §8.

Yang membedakan kedua jalur bukan cuma pintunya, melainkan **empat hal** yang dibawa akun
barunya — dan tidak satu pun dari empat itu pernah dinyatakan sebagai aturan sampai
BR-IAM-12 ditulis:

| | Self-register | Undangan |
| - | ------------- | -------- |
| Role | `researcher` otomatis | tidak ada; permission langsung dari token |
| Organisasi | dipilih pendaftar (OrgTreePicker) | dari `token.organization_id` |
| Permission | dari role | dari `token.permissions` |
| Status | `pending` | `active` |

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `IAM-REG-01` | happy | Registrasi mandiri dengan email `@itk.ac.id` berhasil, status `pending` | BR-IAM-01 | SHARED-01-02 |
| `IAM-REG-02` | denial | Email di luar domain `@itk.ac.id` ditolak pada jalur mandiri | BR-IAM-01 | SHARED-01-02 |
| `IAM-REG-03` | boundary | Jalur undangan **tidak** dibatasi domain — mitra eksternal bisa masuk | BR-IAM-01 + BR-IAM-06 | SHARED-01-03 |
| `IAM-REG-04` | denial | NIDN yang sudah dipakai user lain ditolak | BR-IAM-02 | SHARED-01-02 |
| `IAM-REG-05` | happy | NIDN unik tersimpan dan masuk antrian verifikasi | BR-IAM-02 | OPERATOR-08-02 |
| `IAM-REG-06` | denial | Token dengan `used_count >= max_uses` ditolak | BR-IAM-06 | SHARED-01-04 |
| `IAM-REG-07` | denial | Token yang `expires_at`-nya lewat ditolak | BR-IAM-06 | SHARED-01-04 |
| `IAM-REG-08` | happy | Token sah menaikkan `used_count` dan membuat user **aktif** di organisasi token itu | BR-IAM-06 | SHARED-01-03 |
| `IAM-REG-09` | boundary | Satu token dipakai beberapa user sampai `max_uses` tercapai | BR-IAM-06 | ADMIN-08-01 |
| `IAM-REG-10` | boundary | Token belum terpakai **tidak menghalangi** penghapusan organisasi — ia dicabut, dan Operator diberi tahu berapa | BR-IAM-06 + BR-IAM-08 | ADMIN-08-02 |
| `IAM-REG-11` | denial | Token milik organisasi yang sudah **dihapus** ditolak | BR-IAM-06 | SHARED-01-04 |
| `IAM-REG-12` | happy | Self-register memberi role `researcher` dan organisasi pilihan pendaftar | BR-IAM-12 | SHARED-01-02 |
| `IAM-REG-13` | happy | Undangan mewariskan **permission** yang tertera di token | BR-IAM-12 | SHARED-01-03 |
| `IAM-REG-14` | boundary | Pendaftar undangan **tidak** masuk antrian verifikasi NIDN | BR-IAM-12 | OPERATOR-08-02 |
| `IAM-REG-15` | boundary | Dua akun undangan sama-sama tanpa NIDN dan tidak bentrok | BR-IAM-02 | SHARED-01-03 |
| `IAM-REG-16` | denial | Token yang **dicabut Operator** ditolak | BR-IAM-06 | ADMIN-08-01 |
| `IAM-REG-17` | denial | Token milik organisasi **nonaktif** ditolak | BR-IAM-06 + BR-IAM-14 | SHARED-01-04 |
| `IAM-REG-18` | denial | Domain yang hanya *menyerupai* `@itk.ac.id` ditolak — 4 baris `Examples` | BR-IAM-01 | SHARED-01-02 |
| `IAM-REG-19` | boundary | Domain huruf besar tetap diterima | BR-IAM-01 | SHARED-01-02 |

`IAM-REG-03` menutup celah yang mudah terlewat: kalau pengecekan domain dipasang di
lapisan yang salah, jalur undangan ikut terblokir dan mitra eksternal tidak pernah bisa
masuk. Domain hanya berlaku untuk **self-register**.

`IAM-REG-18` dan `IAM-REG-19` adalah pasangan yang menjaga pengecekan itu tidak
diimplementasikan sebagai `str_contains` atau `str_ends_with`. `budi@itk.ac.id.example.com`
lolos keduanya; `Budi@ITK.AC.ID` gagal kalau perbandingannya case-sensitive. Empat baris
`Examples` menyerap empat cara keliru sekaligus tanpa empat ID baru.

`IAM-REG-14` menjaga akibat yang paling mudah lolos dari koreksi register #29:
implementasi yang cuma mengganti nilai status awal akan **tetap** memasukkan pendaftar
undangan ke antrian verifikasi, kalau antriannya dibangun dari "user yang belum
diverifikasi" alih-alih "user berstatus `pending`".

---

## 4. Verifikasi, penolakan, dan kelengkapan profil

Tiga gerbang berbeda yang sering dianggap satu — dan satu user bisa lolos yang satu
sambil tertahan yang lain:

| Sumbu | Menggerbang | Aturannya |
| ----- | ----------- | --------- |
| `VerificationStatus` (`pending`/`active`/`rejected`) | **submit** | BR-IAM-04, BR-IAM-13 |
| `User.is_active` | **login** | BR-IAM-05 |
| `UserProfile::isComplete()` | **pembuatan submission** | BR-IAM-03 |

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `IAM-VERIFY-01` | boundary | User `pending` **bisa login** | BR-IAM-04 | SHARED-01-05 |
| `IAM-VERIFY-02` | denial | User `pending` tidak bisa submit | BR-IAM-04 | RESEARCHER-02-10 |
| `IAM-VERIFY-03` | happy | Operator memverifikasi NIDN; user bisa submit | BR-IAM-04 | OPERATOR-08-02 |
| `IAM-VERIFY-04` | denial | Profil belum lengkap — pembuatan submission ditolak walau akun terverifikasi | BR-IAM-03 | SHARED-01-06 |
| `IAM-VERIFY-05` | happy | Profil lengkap membuka pembuatan submission | BR-IAM-03 | SHARED-01-06 |
| `IAM-VERIFY-06` | happy | User `pending` boleh **membuat** DRAFT | BR-IAM-04 | RESEARCHER-02-02 |
| `IAM-VERIFY-07` | denial | Penolakan dengan alasan tertulis memindahkan akun ke `rejected`, alasannya terkirim | BR-IAM-13 + BR-IAM-15 | OPERATOR-08-02 |
| `IAM-VERIFY-08` | denial | Penolakan **tanpa alasan** ditolak; status tidak bergerak | BR-IAM-13 | OPERATOR-08-02 |
| `IAM-VERIFY-09` | denial | Akun `rejected` tidak bisa submit | BR-IAM-13 | RESEARCHER-02-10 |
| `IAM-VERIFY-10` | boundary | Akun `rejected` **masih bisa** diverifikasi setelah NIDN dikoreksi | BR-IAM-13 | OPERATOR-08-02 |
| `IAM-VERIFY-11` | happy | User `pending` boleh **menyunting** DRAFT yang sudah ada | BR-IAM-04 | RESEARCHER-02-02 |
| `IAM-VERIFY-12` | denial | Profil yang terisi sebagian tetap dihitung belum lengkap | BR-IAM-03 | SHARED-01-06 |

`IAM-VERIFY-01` sengaja `@boundary` dan bukan `@denial`: aturannya justru **membolehkan**
login. Kalau `pending` diperlakukan sebagai tidak bisa login, user tidak akan pernah
sampai ke layar yang memberitahunya bahwa ia sedang menunggu verifikasi
(`SHARED-01-05`) — dan itu jalan buntu produk.

`IAM-VERIFY-02` dan `IAM-VERIFY-04` memblokir aksi yang sama dari dua sebab berbeda.
Keduanya perlu ada, karena implementasi yang hanya mengecek salah satunya akan lolos
setengah kasus.

`IAM-VERIFY-06` dan `IAM-VERIFY-11` juga sepasang, dan pasangannya bukan basa-basi:
BR-IAM-04 berbunyi "membuat **dan menyunting** DRAFT". Gerbang yang dipasang di
`update()` alih-alih di `submit()` akan lolos `IAM-VERIFY-06` dan gagal `IAM-VERIFY-11`.

`IAM-VERIFY-10` adalah yang paling mudah salah diimplementasikan sebagai status terminal.
Ia bukan — dan alasannya ada di aturannya sendiri: email dan NIDN yang terlanjur terpakai
membuat pendaftaran ulang mustahil (BR-IAM-02), jadi penolakan permanen berarti mematikan
akun gara-gara satu digit salah ketik.

`IAM-VERIFY-12` menjaga `isComplete()` yang cuma memeriksa satu field. Wireframe
`SHARED-01-06` menuntut tiga: nomor telepon, bidang keahlian, dan foto.

---

## 5. Permission additive

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-PERM-01` | happy | Direct permission menambah, **tidak menggantikan**, permission dari role | OPERATOR-08-03 |
| `IAM-PERM-02` | boundary | Mencabut role tidak mencabut direct permission | OPERATOR-08-03 |
| `IAM-PERM-03` | boundary | User dengan dua role mendapat gabungan permission keduanya | OPERATOR-08-03 |
| `IAM-PERM-04` | boundary | Mencabut direct permission tidak mencabut yang datang dari role | OPERATOR-08-03 |

`IAM-PERM-02` dan `IAM-PERM-04` adalah cermin satu sama lain, dan pasangannya sengaja:
"additive" berarti kedua sumber berdiri sendiri **di kedua arah**. Kasus paling menjebak
adalah satu permission yang datang dari dua sumber sekaligus — mencabut satu sumber tidak
boleh menghapus yang lain, dan implementasi Spatie yang benar memang begitu, tapi
pembungkus buatan sendiri yang menyimpan permission efektif sebagai daftar datar tidak.

Kasus "direct permission tanpa role sama sekali" sudah dibuktikan `[FE-ACCESS-06]` di M0.

---

## 6. Deaktivasi user

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-DEACT-01` | happy | Deaktivasi menyetel `is_active = false`; data user tetap utuh | OPERATOR-08-03 |
| `IAM-DEACT-02` | denial | User non-aktif tidak bisa login | SHARED-01-01 |
| `IAM-DEACT-03` | boundary | Pengajuan milik user nonaktif tetap terbaca | OPERATOR-08-03 |
| `IAM-DEACT-04` | denial | Reviewer nonaktif tidak menerima penugasan baru | OPERATOR-08-04 |

`IAM-DEACT-03` dan `IAM-DEACT-04` bersama-sama menyatakan apa arti `is_active = false`:
**data bertahan, kemampuan bertindak berhenti**. Tanpa keduanya, BR-IAM-05 cuma
menjanjikan bahwa deaktivasi tidak menghapus baris — janji yang dipenuhi juga oleh
implementasi yang tidak melakukan apa-apa sama sekali.

Penjagaan kepemilikan submission saat lead researcher dinonaktifkan ada di
`[SM-OWNER-03]` dan `[SM-OWNER-04]` (M1), bertag `@BR-IAM-10`. Penggantian reviewer yang
terlanjur nonaktif ada di `[REV-REASSIGN-01]` (M2).

---

## 7. Organisasi — dua operasi, dua ambang

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `IAM-ORG-01` | denial | Tidak bisa **dihapus** selama masih ada `UserProfile` di dalamnya | BR-IAM-08 | ADMIN-08-02 |
| `IAM-ORG-02` | denial | Penghapusan juga ditolak kalau `UserProfile`-nya ada di **organisasi turunan** | BR-IAM-08 | ADMIN-08-02 |
| `IAM-ORG-03` | happy | Organisasi tanpa user di seluruh subtree-nya bisa dihapus | BR-IAM-08 | ADMIN-08-02 |
| `IAM-ORG-04` | denial | Tidak bisa **dinonaktifkan** selama masih ada user **aktif** | BR-IAM-14 | ADMIN-08-02 |
| `IAM-ORG-05` | denial | User aktif di unit turunan ikut menghalangi deaktivasi | BR-IAM-14 | ADMIN-08-02 |
| `IAM-ORG-06` | happy | Unit yang seluruh user-nya sudah nonaktif bisa dinonaktifkan | BR-IAM-14 | ADMIN-08-02 |
| `IAM-ORG-07` | boundary | Deaktivasi tidak menghapus apa pun | BR-IAM-14 | ADMIN-08-02 |
| `IAM-ORG-08` | denial | Unit nonaktif memutus rantai leluhur access control | BR-IAM-14 | ADMIN-08-02 |

`IAM-ORG-02` adalah inti BR-IAM-08 — kata "subtree" di aturannya bukan hiasan.
Implementasi yang hanya mengecek node itu sendiri akan menghapus fakultas beserta
seluruh prodi yang masih berisi orang.

`IAM-ORG-06` mengunci celah antara dua ambang: unit yang seluruh user-nya sudah nonaktif
**boleh dinonaktifkan tapi belum boleh dihapus**. Itu bukan kelalaian melainkan justru
inti register #31 — hapus melihat `UserProfile` apa pun, nonaktif melihat user aktif.

`IAM-ORG-08` menyambungkan BR-IAM-14 ke BR-FE-02. Ia sengaja **tidak** bertag ganda:
yang dibuktikan skenario ini adalah akibat deaktivasi, bukan aturan penelusurannya —
BR-FE-02 sudah lunas oleh `[FE-ACCESS-01..06]`. Yang baru di sini adalah unit nonaktif
di **tengah** rantai, yang memutus penelusuran sebelum sampai ke leluhur tempat Form
dipasang.

> Perhatikan arahnya. "Subtree" di BR-IAM-08 dan BR-IAM-14 berarti **keturunan** — itu
> arah yang benar untuk "siapa yang ada di bawah unit ini". Berbeda dari BR-FE-02 yang
> arahnya **leluhur** ([ADR 0013](../../adr/0013-form-access-control-matches-ancestor-organizations.md)).
> Dua pertanyaan berbeda, dua arah berbeda — `Organization::subtreeIds()` untuk yang
> ini, `ancestorIds()` untuk access control. `IAM-ORG-08` adalah satu-satunya skenario
> M3 yang menyentuh arah kedua.

---

## 8. Penunjukan reviewer — BR-IAM-11

Konsekuensi yang mudah terlewat: reviewer eksternal **harus punya akun lebih dulu** lewat
jalur undangan, baru bisa ditunjuk. Tidak ada jalur registrasi mandiri untuk reviewer.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-REVIEWER-01` | happy | Penunjukan internal memberi role `reviewer_internal` dan record `reviewers` | OPERATOR-08-04 |
| `IAM-REVIEWER-02` | happy | Penunjukan eksternal memberi role `reviewer_external` | OPERATOR-08-04 |
| `IAM-REVIEWER-03` | denial | Menunjuk seseorang yang belum punya akun ditolak | OPERATOR-08-04 |
| `IAM-REVIEWER-04` | boundary | Menunjuk ulang tidak membuat record kedua | OPERATOR-08-04 |
| `IAM-REVIEWER-05` | boundary | Mengubah tipe **mencabut role tipe lama** | OPERATOR-08-04 |
| `IAM-REVIEWER-06` | happy | Penunjukan menetapkan masa tugas yang kemudian ditegakkan BR-REV-09 | OPERATOR-08-04 |

`IAM-REVIEWER-02` dan `IAM-REVIEWER-05` menautkan dua milestone. `reviewer_type` yang
disetel di sini adalah yang dipakai BR-REV-11 di M2 untuk memutuskan siapa boleh melihat
skor reviewer lain. Kalau penunjukan tidak menyetel role Spatie yang benar — atau, lebih
halus, **menambah** role baru tanpa mencabut yang lama — aturan keterlihatan di M2 ikut
salah tanpa ada yang gagal di M3. `IAM-REVIEWER-05` karena itu memeriksa permission
`reviewers.view-scores-others` secara eksplisit, bukan cuma nama role-nya.

`IAM-REVIEWER-04` adalah register #34: dua record untuk satu orang membuat penghitungan
beban (BR-REV-04) membaca dari himpunan yang ambigu. Ia tidak akan gagal mencolok — ia
cuma akan menghitung dua kali.

---

## 9. Pemberitahuan perubahan keadaan akun — BR-IAM-15

Tabel Domain Events di DDD mendaftar empat event IAM yang consumer-nya Notification.
Sampai M3 ditulis, tidak satu pun pernah diuji di mana pun — dan event yang tidak pernah
diterbitkan tidak menggagalkan apa-apa, ia cuma diam.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-EVENT-01` | happy | Pendaftaran baru memberi tahu **Operator** | OPERATOR-08-02 |
| `IAM-EVENT-02` | happy | Verifikasi memberi tahu pendaftar | SHARED-02-05 |
| `IAM-EVENT-03` | happy | Deaktivasi memberi tahu user yang dinonaktifkan | SHARED-02-05 |
| `IAM-EVENT-04` | happy | Penunjukan reviewer memberi tahu yang ditunjuk | SHARED-02-05 |
| `IAM-EVENT-05` | boundary | Perubahan yang user lakukan sendiri **tidak** memberitahunya | SHARED-01-06 |

Arah pemberitahuannya bukan seragam, dan itu justru isi aturannya: `IAM-EVENT-01`
memberi tahu **Operator** karena dialah yang harus bertindak, sementara tiga sisanya
memberi tahu **user** karena kemampuannya berubah tanpa ia melakukan apa-apa.

`IAM-EVENT-05` menjaga batas atasnya. Tanpa batas, "beri tahu setiap perubahan" berujung
notifikasi yang lebih cepat diabaikan daripada dibaca — dan yang paling sering dikirim
keliru adalah gema atas aksi user sendiri.

Penolakan verifikasi tidak diulang di berkas ini: alasannya bagian dari aturannya
sendiri (BR-IAM-13), dan `[IAM-VERIFY-07]` sudah menjaga bahwa **alasannya** ikut
terkirim — bukan sekadar fakta penolakannya. Channel tiap event adalah konfigurasi
(BR-NOT-06), dibuktikan `[NOT-CHANNEL-03]` di M5.

---

## 10. Frame yang tercakup

SHARED-01-01 · SHARED-01-02 · SHARED-01-03 · SHARED-01-04 · SHARED-01-05 ·
SHARED-01-06 · SHARED-02-05 · OPERATOR-08-02 · OPERATOR-08-03 · OPERATOR-08-04 ·
ADMIN-08-01 · ADMIN-08-02

Dua belas frame, naik dari sebelas: `SHARED-02-05` (Notifikasi) ikut masuk lewat
`IAM-EVENT-02..04`.

Frame yang **tidak** punya aturan bisnis di baliknya dan karenanya tidak melahirkan
skenario: `SHARED-01-07` (Profil Saya), `SHARED-01-08` (Lupa Password), `SHARED-01-09`
(Reset Password), `OPERATOR-08-01` (Daftar User). Ketiga layar password memakai mekanisme
bawaan Laravel dan tidak diatur BR mana pun — kalau nanti ada aturan khusus (mis. masa
berlaku tautan reset), aturannya ditambahkan ke DDD lebih dulu, baru skenarionya.

---

## 11. Yang menunggu keputusan

Kosong. Enam perkara yang muncul saat M3 ditulis semuanya sudah diputuskan dan tercatat
sebagai #29–#34 di [`open-questions.md`](../../requirement-analysis/bdd/open-questions.md).

| # | Perkara | Keputusan |
| - | ------- | --------- |
| ~~#17~~ | User `pending` boleh menyimpan DRAFT? | **Terselesaikan 2026-09-04** — boleh; BR-IAM-04 diperjelas, dikunci `[IAM-VERIFY-06/11]` |
| ~~#18~~ | Nasib undangan saat organisasinya dihapus | **Terselesaikan 2026-09-04** — token dicabut, bukan memblokir; dikunci `[IAM-REG-10/11]` |
| ~~#29~~ | Status akun jalur undangan | **Terselesaikan 2026-09-07** — `active` langsung; **BR-IAM-12** baru, dikunci `[IAM-REG-12..14]` |
| ~~#30~~ | Penolakan verifikasi NIDN tanpa aturan | **Terselesaikan 2026-09-07** — **BR-IAM-13** baru, tidak terminal, dikunci `[IAM-VERIFY-07..10]` |
| ~~#31~~ | Hapus versus nonaktifkan organisasi | **Terselesaikan 2026-09-07** — keduanya ada, ambang berbeda; **BR-IAM-14** baru, dikunci `[IAM-ORG-04..08]` |
| ~~#32~~ | NIDN unik versus akun tanpa NIDN | **Terselesaikan 2026-09-07** — unik atas nilai terisi; dikunci `[IAM-REG-15]` |
| ~~#33~~ | Pencabutan undangan | **Terselesaikan 2026-09-07** — sebab keempat di BR-IAM-06; dikunci `[IAM-REG-16]` |
| ~~#34~~ | Penunjukan reviewer berulang | **Terselesaikan 2026-09-07** — idempoten per orang; dikunci `[IAM-REVIEWER-04..06]` |
