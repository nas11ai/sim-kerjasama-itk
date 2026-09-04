# M3 — Identity & Access

**10 BR perilaku · 25 skenario · 6 berkas `.feature`**

Gerbang masuk sistem: tiga jalur registrasi, verifikasi NIDN, permission additive, dan
deaktivasi yang tidak boleh meninggalkan submission yatim.

Sumber: [`generic/02_identity_access.md`](../../requirement-analysis/ddd/generic/02_identity_access.md).

---

## 1. Berkas dan rentang ID

| Berkas | Rentang ID | BR |
| ------ | ---------- | -- |
| `generic/identity/registration.feature` | `IAM-REG-01..09` | BR-IAM-01, BR-IAM-02, BR-IAM-06 |
| `generic/identity/verification.feature` | `IAM-VERIFY-01..05` | BR-IAM-03, BR-IAM-04 |
| `generic/identity/permission.feature` | `IAM-PERM-01..03` | BR-IAM-09 |
| `generic/identity/deactivation.feature` | `IAM-DEACT-01..02` | BR-IAM-05 |
| `generic/identity/organization.feature` | `IAM-ORG-01..03` | BR-IAM-08 |
| `generic/identity/reviewer_provisioning.feature` | `IAM-REVIEWER-01..03` | BR-IAM-11 |

Semua skenario bertag `@M3`.

Tiga BR **tidak** melahirkan berkas sendiri:

- **BR-IAM-10** — kanonik untuk transfer ownership; dibuktikan `[SM-OWNER-01..04]` di M1,
  bertag bersama cerminnya BR-SM-09.
- **BR-IAM-07** — bagian dari BR-REV-02; dibuktikan `[REV-ASSIGN-07]` di M2.
- **BR-IAM-09** — sudah lunas sejak Wave 1a lewat `[FE-ACCESS-06]`. Ia tetap muncul di
  tabel berkas di bawah karena `IAM-PERM-01..03` memperdalam kasusnya, bukan
  membuktikannya pertama kali.

Menulis ulang ketiganya sebagai aturan tersendiri berarti dua implementasi untuk satu
aturan.

---

## 2. Registrasi — dua jalur akun

Hanya ada **dua** cara sebuah akun lahir: self-register untuk dosen ITK, dan undangan
untuk pihak eksternal. DDD memuat "Jalur 3 — Reviewer (Ditunjuk Operator)", tapi
membacanya sampai habis menunjukkan itu **bukan** pembuatan akun: alurnya berbunyi
"Cari user by NIDN atau nama → Pilih user yang **sudah ada**". Jalur 3 mempromosikan
akun yang sudah terdaftar menjadi reviewer, dan itu diatur terpisah di §7.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `IAM-REG-01` | happy | Registrasi mandiri dengan email `@itk.ac.id` berhasil, status `pending` | BR-IAM-01 | SHARED-01-02 |
| `IAM-REG-02` | denial | Email di luar domain `@itk.ac.id` ditolak pada jalur mandiri | BR-IAM-01 | SHARED-01-02 |
| `IAM-REG-03` | boundary | Jalur undangan **tidak** dibatasi domain — mitra eksternal bisa masuk | BR-IAM-01 + BR-IAM-06 | SHARED-01-03 |
| `IAM-REG-04` | denial | NIDN yang sudah dipakai user lain ditolak | BR-IAM-02 | SHARED-01-02 |
| `IAM-REG-05` | happy | NIDN unik tersimpan dan masuk antrian verifikasi | BR-IAM-02 | OPERATOR-08-02 |
| `IAM-REG-06` | denial | Token dengan `used_count >= max_uses` ditolak | BR-IAM-06 | SHARED-01-04 |
| `IAM-REG-07` | denial | Token yang `expires_at`-nya lewat ditolak | BR-IAM-06 | SHARED-01-04 |
| `IAM-REG-08` | happy | Token sah menaikkan `used_count` dan membuat user di organisasi token itu | BR-IAM-06 | SHARED-01-03 |
| `IAM-REG-09` | boundary | Satu token dipakai beberapa user sampai `max_uses` tercapai | BR-IAM-06 | ADMIN-08-01 |

`IAM-REG-03` menutup celah yang mudah terlewat: kalau pengecekan domain dipasang di
lapisan yang salah, jalur undangan ikut terblokir dan mitra eksternal tidak pernah
bisa masuk. Domain hanya berlaku untuk **self-register**.

`IAM-REG-09` menjaga sifat token yang memang multi-pakai — bukan sekali pakai.

---

## 3. Verifikasi dan kelengkapan profil

Dua gerbang berbeda yang sering dianggap satu: **status akun** (`pending` vs terverifikasi)
dan **kelengkapan profil**.

| ID | Kelas | Yang dibuktikan | BR | Frame |
| -- | ----- | --------------- | -- | ----- |
| `IAM-VERIFY-01` | boundary | User `pending` **bisa login** dan melihat dashboard | BR-IAM-04 | SHARED-01-05 |
| `IAM-VERIFY-02` | denial | User `pending` tidak bisa submit proposal | BR-IAM-04 | RESEARCHER-02-02 |
| `IAM-VERIFY-03` | happy | Operator memverifikasi NIDN; user bisa submit | BR-IAM-04 | OPERATOR-08-02 |
| `IAM-VERIFY-04` | denial | Profil belum lengkap — pembuatan submission ditolak walau akun terverifikasi | BR-IAM-03 | SHARED-01-06 |
| `IAM-VERIFY-05` | happy | Profil lengkap membuka pembuatan submission | BR-IAM-03 | SHARED-01-06 |

`IAM-VERIFY-01` sengaja `@boundary` dan bukan `@denial`: aturannya justru **membolehkan**
login. Kalau `pending` diperlakukan sebagai tidak bisa login, user tidak akan pernah
sampai ke layar yang memberitahunya bahwa ia sedang menunggu verifikasi
(SHARED-01-05) — dan itu jalan buntu produk.

`IAM-VERIFY-02` dan `IAM-VERIFY-04` memblokir aksi yang sama dari dua sebab berbeda.
Keduanya perlu ada, karena implementasi yang hanya mengecek salah satunya akan lolos
setengah kasus.

---

## 4. Permission additive

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-PERM-01` | happy | Direct permission menambah, **tidak menggantikan**, permission dari role | OPERATOR-08-03 |
| `IAM-PERM-02` | boundary | Mencabut role tidak mencabut direct permission yang diberikan terpisah | OPERATOR-08-03 |
| `IAM-PERM-03` | boundary | User dengan dua role mendapat gabungan permission keduanya | OPERATOR-08-03 |

Kasus "direct permission tanpa role sama sekali" sudah dibuktikan `[FE-ACCESS-06]` di
M0 (BR-IAM-09) — tidak diulang di sini.

---

## 5. Deaktivasi

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-DEACT-01` | happy | Deaktivasi menyetel `is_active = false`; data user tetap utuh | OPERATOR-08-03 |
| `IAM-DEACT-02` | denial | User non-aktif tidak bisa login | SHARED-01-01 |

Penjagaan kepemilikan submission saat lead researcher dinonaktifkan ada di
`[SM-OWNER-03]` dan `[SM-OWNER-04]` (M1), bertag `@BR-IAM-10` juga.

---

## 6. Organisasi

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-ORG-01` | denial | Organisasi tidak bisa dihapus selama masih ada `UserProfile` di dalamnya | ADMIN-08-02 |
| `IAM-ORG-02` | denial | Penghapusan juga ditolak kalau `UserProfile`-nya ada di **organisasi turunan**, bukan di node itu sendiri | ADMIN-08-02 |
| `IAM-ORG-03` | happy | Organisasi tanpa user di seluruh subtree-nya bisa dihapus | ADMIN-08-02 |

`IAM-ORG-02` adalah inti BR-IAM-08 — kata "subtree" di aturannya bukan hiasan.
Implementasi yang hanya mengecek node itu sendiri akan menghapus fakultas beserta
seluruh prodi yang masih berisi orang.

> Perhatikan: "subtree" di sini berarti **keturunan**, dan itu memang arah yang benar
> untuk pertanyaan "siapa yang ada di bawah organisasi ini". Berbeda dari BR-FE-02
> yang arahnya leluhur ([ADR 0013](../../adr/0013-form-access-control-matches-ancestor-organizations.md)).
> Dua pertanyaan berbeda, dua arah berbeda — `Organization::subtreeIds()` untuk yang
> ini, `ancestorIds()` untuk access control.

---

## 7. Penunjukan reviewer — BR-IAM-11

Konsekuensi yang mudah terlewat: reviewer eksternal **harus punya akun lebih dulu**
lewat jalur undangan, baru bisa ditunjuk. Tidak ada jalur registrasi mandiri untuk
reviewer.

| ID | Kelas | Yang dibuktikan | Frame |
| -- | ----- | --------------- | ----- |
| `IAM-REVIEWER-01` | happy | Operator menunjuk user terdaftar sebagai reviewer internal; user mendapat role `reviewer_internal` dan record `reviewers` | OPERATOR-08-04 |
| `IAM-REVIEWER-02` | happy | Penunjukan sebagai reviewer eksternal memberi role `reviewer_external` — role inilah yang membatasi keterlihatan skor di `[REV-VIS-02]` | OPERATOR-08-04 |
| `IAM-REVIEWER-03` | denial | Menunjuk seseorang yang belum punya akun ditolak — undang dulu lewat `[IAM-REG-08]` | OPERATOR-08-04 |

`IAM-REVIEWER-02` menautkan dua milestone: `reviewer_type` yang disetel di sini adalah
yang dipakai BR-REV-11 di M2 untuk memutuskan siapa boleh melihat skor reviewer lain.
Kalau penunjukan tidak menyetel role Spatie yang benar, aturan keterlihatan di M2 ikut
salah tanpa ada yang gagal di M3.

---

## 8. Frame yang tercakup

SHARED-01-01 · SHARED-01-02 · SHARED-01-03 · SHARED-01-04 · SHARED-01-05 ·
SHARED-01-06 · OPERATOR-08-02 · OPERATOR-08-03 · OPERATOR-08-04 · ADMIN-08-01 ·
ADMIN-08-02

Frame yang **tidak** punya aturan bisnis di baliknya dan karenanya tidak melahirkan
skenario: SHARED-01-07 (Profil Saya), SHARED-01-08 (Lupa Password), SHARED-01-09
(Reset Password), OPERATOR-08-01 (Daftar User). Ketiga layar password memakai
mekanisme bawaan Laravel dan tidak diatur BR mana pun — kalau nanti ada aturan khusus
(mis. masa berlaku tautan reset), aturannya ditambahkan ke DDD lebih dulu, baru
skenarionya.

---

## 9. Yang menunggu keputusan

| # | Perkara | Dampak |
| - | ------- | ------ |
| baru | Apakah user `pending` boleh menyimpan DRAFT, atau hanya dilarang **submit** | `IAM-VERIFY-02` mengasumsikan hanya submit yang diblokir — konsisten dengan bunyi BR-IAM-04 ("bisa login tapi tidak bisa submit"), tapi belum ditegaskan |
| baru | Apa yang terjadi pada undangan yang sudah dipakai sebagian saat organisasinya dihapus | Bersinggungan dengan BR-IAM-08 dan BR-IAM-06; tidak diatur di DDD |
