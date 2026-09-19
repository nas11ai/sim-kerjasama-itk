# Penutupan Gap — Kebutuhan Bisnis Tanpa Antarmuka

**Versi:** 2.0
**Tanggal:** 2026-09-19 (gelombang 1: 2026-09-04)
**Status:** Gelombang 1 menunggu review · Gelombang 2 mendarat 2026-09-19

Dokumen ini memuat dua gelombang penutupan gap yang berbeda sebabnya.

**Gelombang 1** lahir saat menyusun [spec cakupan BDD](bdd-coverage/): lima kebutuhan
bisnis yang aturannya sudah ada di DDD tapi belum punya antarmuka, satu kontradiksi
langsung antara wireframe dan aturan, dan satu pertanyaan yang belum terjawab.

**Gelombang 2** lahir dari arah sebaliknya — menelusuri kode terhadap DDD dan
wireframe, bukan spec terhadap dirinya sendiri. Yang muncul adalah kelas gap yang
tidak mungkin ditangkap gelombang 1: kapabilitas yang **layarnya sudah digambar dan
modelnya sudah ada, tapi tidak punya pintu masuk**, plus satu lubang sistematis pada
perilaku hapus dan nonaktif.

Keduanya ditutup di tiga lapis yang sama: aturan, skenario, dan layar.

Register terkait: [#9, #10, #11](../requirement-analysis/bdd/open-questions.md).

---

# Bagian I — Gelombang 1 · 2026-09-04

## 1. Target Luaran — di mana ia hidup

**Masalah.** `SubmissionTargetOutput` lahir dari keputusan register #5, jadi ia tidak
pernah masuk wireframe. Wizard tujuh langkah tidak punya tempat untuknya, dan
`RESEARCHER-06-*` yang bernama "Luaran" ternyata `ResearchOutput` — entitas lain.

**Keputusan.** Target luaran masuk **langkah 3**, bergabung dengan Skema & TRL.

Alasannya bukan kerapian melainkan urutan data: BR-SM-14 membatasi pilihan tipe dengan
`scheme.rules.allowed_output_types`, jadi target **tidak bisa** dipilih sebelum skema
ditentukan. Langkah 3 adalah titik paling awal di mana pembatas itu sudah diketahui.
Jumlah langkah tetap tujuh — [ADR 0012](../adr/0012-wizard-driven-submission-lifecycle.md)
tidak dilanggar.

**Konsekuensi kedua.** BR-SM-15 membolehkan target diubah sampai REJECTED/WITHDRAWN,
termasuk setelah APPROVED. Wizard tidak bisa jadi satu-satunya pintu, karena wizard
selesai saat submit. Karena itu target juga mendapat **tab tersendiri di halaman
detail** (`RESEARCHER-02-11`) yang hidup sepanjang siklus.

| Lapis | Wujud |
| ----- | ----- |
| DDD | Tabel wizard `core/01_submission.md` langkah 3 kini menulis ke `submission_target_outputs` |
| Skenario | `[SM-TARGET-01..09]` — sudah dialokasikan di [M1](bdd-coverage/m1-submission-budget.md) |
| UI | `RESEARCHER-02-07` (wizard) dan `RESEARCHER-02-11` (tab detail) |

Layar detail sengaja memuat dua pernyataan yang menegakkan BR-SM-15: target tetap bisa
diubah setelah disetujui, dan **hanya ketua pengaju** yang boleh mengubahnya —
Co-Investigator hanya membaca. Yang terakhir ini berbeda dari BR-RO-07 pada luaran
realisasi, dan perbedaannya disengaja.

---

## 2. Wizard yang hanya punya dua langkah

**Masalah.** Label ketujuh langkah ada di wireframe, tapi hanya langkah 1 dan 5 yang
pernah punya isi. Lima langkah lain tidak pernah didesain — termasuk langkah 4 yang
memuat pengecekan konflik kepentingan (BR-SM-08).

**Keputusan.** Kelima langkah dibuatkan layarnya, masing-masing menegakkan aturan yang
sudah ada:

| Langkah | Frame | Aturan yang terlihat di layar |
| ------- | ----- | ----------------------------- |
| 2 Informasi Dasar | `RESEARCHER-02-06` | BR-FE-11 — field yang diwajibkan **setelah** pengajuan dibuat tidak menghalangi submit |
| 3 Skema, TRL & Target | `RESEARCHER-02-07` | BR-SCH-05 (TRL disaring skema), BR-SM-14 (tipe target dibatasi skema) |
| 4 Anggota Penelitian | `RESEARCHER-02-08` | BR-SM-08 — penolakan eksplisit saat orang yang ditambahkan sudah jadi reviewer |
| 6 Upload Berkas | `RESEARCHER-02-09` | BR-SM-02 — proposal PDF wajib sebelum submit |
| 7 Review & Submit | `RESEARCHER-02-10` | BR-SM-04 (hanya ketua yang bisa submit), BR-FE-11 (validasi penuh baru di sini) |

Langkah 4 mendapat perhatian khusus: pesan penolakan COI ditulis sebagai kalimat yang
menjelaskan **kenapa**, bukan sekadar "tidak dapat ditambahkan". Aturan yang ditolak
tanpa alasan akan dilaporkan sebagai bug oleh penggunanya.

---

## 3. Notifikasi tanpa rumah

**Masalah.** BR-NOT-01 dan BR-NOT-04 mengatur notifikasi in-app yang tersimpan permanen
dan ditandai dibaca saat diklik. Di 57 frame tidak ada satu pun daftar notifikasi —
hanya kartu KPI "4 belum dibaca" yang tidak menuju ke mana-mana.

**Keputusan.** Frame baru `SHARED-02-05`, dijangkau dari Aksi Cepat di dashboard.

Layarnya memuat dua pernyataan yang menegakkan aturan: notifikasi ditandai dibaca
**saat dibuka, bukan saat halaman daftar dibuka** (BR-NOT-04), dan peristiwa ringan
cukup in-app tanpa email (BR-NOT-02). Keduanya keputusan yang mudah salah
diimplementasikan kalau hanya dibaca dari tabel aturan.

Skenario `[NOT-INAPP-01..03]` dan `[NOT-CHANNEL-01..02]` di
[M5](bdd-coverage/m5-reporting-notification-config.md) kini punya layar yang dirujuk.

---

## 4. Tiga status tanpa wajah

**Masalah.** `REJECTED`, `WITHDRAWN`, dan `RESUBMITTED` tidak pernah dirender sebagai
status di satu pun frame — padahal ketiganya justru status yang paling perlu jelas bagi
peneliti. Ini yang selama ini tercatat samar sebagai register #3 "frontend 5 nilai vs
backend 8".

**Keputusan.** Ketiganya masuk daftar pengajuan, dan `REJECTED` mendapat halaman detail
sendiri (`RESEARCHER-02-12`).

Halaman penolakan memuat tiga hal yang tidak ada di halaman detail biasa:

1. **Alasan penolakan beserta siapa yang memutuskan.** Penolakan adalah keputusan
   manual (BR-REV-07), jadi selalu ada manusia yang bertanggung jawab.
2. **Skor evaluasi yang akhirnya terbuka.** BR-REV-12 menahan skor sampai APPROVED atau
   REJECTED; halaman ini adalah salah satu dari dua tempat skor itu boleh muncul.
3. **Pernyataan bahwa penolakan tidak pernah otomatis.** Ini menutup salah paham yang
   paling mungkin muncul dari kode legacy yang justru masih melakukannya
   ([ADR 0006](../adr/0006-rejection-is-manual-only.md)).

---

## 5. Anggaran melebihi batas — kontradiksi yang diperbaiki

**Masalah.** `RESEARCHER-02-04` menyatakan anggaran yang melewati batas skema **tetap
dapat disubmit**. BR-BUD-01 melarangnya.

**Keputusan.** Aturan menang; kalimat di wireframe diganti. Layar kini menyatakan
pengajuan tidak dapat disubmit selama total masih melebihi batas.

Anggaran menyangkut kepatuhan dana dan salahnya baru ketahuan saat audit. Kalau LPPM
justru menghendaki submit tetap boleh dengan Operator sebagai pengadil, itu keputusan
sah — tapi berarti **BR-BUD-01 yang diubah**, bukan UI yang diam-diam melunak.

---

## 6. Keterlihatan hasil evaluasi monev

**Masalah.** Dua layar menerapkan gerbang berbeda: pengajuan menahan skor sampai
APPROVED/REJECTED, monev menampilkannya begitu reviewer submit.

**Keputusan.** Monev **dikecualikan secara eksplisit** lewat BR-MON-09 yang baru,
bukan dibiarkan sebagai tafsir bebas atas BR-REV-12.

Alasannya kebutuhan bisnis: monev bersifat formatif. Tujuannya mengoreksi arah kegiatan
selagi masih berjalan, dan itu mustahil kalau catatan reviewer baru terbaca setelah
siklusnya berakhir. Menyamakan keduanya akan membuat monev kehilangan gunanya.

Pengecualian ini dijaga agar tetap sempit: `[MON-REVIEW-09]` membuktikan gerbang
pengajuan awal **tidak** ikut longgar.

---

## 7. Ringkasan gelombang 1

| Lapis | Perubahan |
| ----- | --------- |
| DDD | Langkah 3 wizard menulis `submission_target_outputs`; BR-MON-09 baru; BR-REV-12 menautkan pengecualiannya; BR-IAM-11 baru (penunjukan reviewer) |
| Skenario | `[MON-REVIEW-07..09]` baru; `[SM-WIZARD-09]` baru; total 186 ID dialokasikan |
| UI | 8 frame baru (57 → 65), 6 frame disunting, 4 fixture baru |

Yang **tidak** dikerjakan gelombang ini: mengimplementasikan skenarionya. Layar dan
aturannya kini ada, jadi skenario yang selama ini terhalang sudah bisa dikerjakan —
itu pekerjaan M1, M4, dan M5 yang punya jalurnya sendiri.

---

# Bagian II — Gelombang 2 · 2026-09-19

## 8. Kenapa gap ini tidak tertangkap pelacakan yang ada

Saat gelombang ini ditelusuri, seluruh lapis pelacakan repo menyatakan tertutup:

| Ukuran | Hasil |
| ------ | ----- |
| BR di DDD | 124 |
| BR yang dirujuk `app/` atau `tests/` | 119 |
| 5 sisanya | seluruhnya terdaftar di `non-behavioral.md` |
| Scenario ID di `bdd/` | 329 |
| Scenario ID yang punya test Pest | 329 — nol selisih |
| `pending.txt` / `uncovered.txt` | `max: 0` / `max: 0` |
| `open-questions.md` § Terbuka | kosong |

Angka-angka itu benar. Yang tidak dijawabnya adalah pertanyaan yang berbeda:
`pending.txt` mengukur skenario yang **sudah ditulis** tapi belum punya test, dan
`uncovered.txt` mengukur BR yang **sudah dirumuskan** tapi belum punya skenario.
Keduanya mengukur jarak dari spec ke kode. Tidak satu pun mengukur jarak dari
**kebutuhan ke spec** — dan di situlah gelombang ini menemukan isinya.

Pola temuannya satu dan berulang:

> Skenarionya hijau karena test-nya memanggil model langsung, sementara penggunanya
> tidak punya jalan sama sekali.

`[IAM-REG-16]` memanggil `$token->revoke()` di
`tests/Feature/Identity/RegistrationPathTest.php:226`. `[BUD-AUDIT-01]` memanggil
`$item->update([...])` di `tests/Feature/BudgetAuditTrailTest.php:31`. Keduanya lulus.
Keduanya menguji perilaku yang **tidak bisa dipicu siapa pun** lewat HTTP, karena
Action dan route-nya tidak pernah dibuat.

Ini bukan kecurangan penulis test — model memang tempat yang sah untuk menguji
invarian. Tapi begitu sebuah skenario hanya punya pembuktian di tingkat model, tidak
ada lagi yang mengingatkan bahwa pintu masuknya belum ada. Test-nya hijau, plafonnya
nol, dan kapabilitasnya tidak lahir.

**Konsekuensi untuk cara kerja.** Skenario yang menyebut pelaku manusia —
"Operator mencabut", "peneliti mengeluarkan", "Admin menghapus" — perlu pembuktian
yang melewati pelakunya, bukan hanya modelnya. Ini dicatat sebagai lampiran
[§17](#18-ringkasan-gelombang-2), bukan sebagai aturan baru: ia soal disiplin menulis
test, bukan soal domain.

---

## 9. Undangan registrasi — dua jalur tanpa pintu masuk

**Masalah.** `ADMIN-08-01` adalah layar penuh: "Buat Undangan Baru" dengan organisasi
tujuan, permission, maks. pemakaian dan masa berlaku, plus tombol "Cabut" per baris.
Wireframe-nya bahkan menuliskan alasannya sendiri:

> *"Pencabutan adalah sebab keempat token tidak berlaku, di samping kuota habis,
> kedaluwarsa, dan organisasinya nonaktif. Tanpa ini, tautan yang bocor tidak punya
> cara dimatikan selain menunggu kedaluwarsa."*

Itu persis keadaan kodenya. `InvitationToken::revoke()` ada di
`app/Models/InvitationToken.php:77`, `isValid()` sudah menegakkan keempat sebab
BR-IAM-06, dan `RegisterByInvitation` sudah **memakai** token. Yang tidak ada: Action,
Policy, controller, maupun route untuk **menerbitkan** dan **mencabut**. Satu-satunya
`InvitationToken::create()` di `app/` adalah query di `DeleteOrganization`.

BR-IAM-06 berbunyi "dicabut **Operator**", tapi tidak ada satu baris pun yang memeriksa
bahwa pelakunya Operator — karena tidak ada satu baris pun yang mencabut.

**Masalah kedua.** `routes/auth.php:22` hanya mendaftarkan **POST**
`register/invitation/{token}`. Tidak ada GET. `SHARED-01-03` (form registrasi undangan)
dan `SHARED-01-04` (token tidak berlaku) karena itu tidak punya tempat mendarat —
padahal BR-IAM-06 secara eksplisit menyebut *"Undangan yang terlanjur dibuka mendarat
di layar token tidak berlaku"*. Kalimat itu menggambarkan perilaku yang belum bisa
terjadi.

**Keputusan.** Penerbitan dan pencabutan jadi use case penuh dengan wewenang eksplisit.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-IAM-16** baru — penerbitan dan pencabutan undangan adalah wewenang Operator; pencabutan menghentikan kuota tersisa dan **tidak menyentuh akun yang terlanjur lahir** |
| Skenario | `[IAM-INVITE-01..08]` — keluarga baru |
| UI | `ADMIN-08-01` sudah ada; `SHARED-01-03`/`04` butuh route GET agar bisa dirender |

Pencabutan sengaja **tidak** dibuat menghapus token. Ia menyetel `revoked_at`, sejalan
dengan prinsip yang sudah berlaku di seluruh repo ([§16](#16-perilaku-hapus-dan-nonaktif--audit-menyeluruh)):
jejak kenapa sebuah tautan dimatikan sama pentingnya dengan matinya.

---

## 10. Anggota penelitian — UI menyuruh, backend tidak bisa

**Masalah.** `RESEARCHER-02-08` punya tombol "Hapus" per anggota. Yang menentukan
bukan tombolnya, melainkan pesan yang muncul saat kuota skema penuh:

> *"Jumlah anggota sudah mencapai batas skema. **Keluarkan anggota lain** sebelum
> menambah."*

Layar menyuruh pengguna melakukan sesuatu yang tidak ada di backend.
`ResearchMemberController` hanya punya `store()`, tidak ada route DELETE, dan pencarian
`ResearchMember` berpasangan dengan `delete|destroy|detach|remove` di seluruh `app/`
mengembalikan nol baris. `members.feature` pun hanya punya `SM-MEMBER-01..03` —
tambah, COI, batas. Tidak ada skenario pengeluaran.

Akibatnya jalan buntu yang utuh: kena batas → disuruh keluarkan → tidak bisa.

**Keputusan.** Pengeluaran anggota jadi operasi berwewenang, bukan sekadar DELETE.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-SM-18** baru — anggota dikeluarkan oleh `submitted_by`; hanya selama pengajuan belum terminal; ketua pengaju tidak bisa mengeluarkan dirinya sendiri |
| Skenario | `[SM-MEMBER-04..08]` |
| UI | `RESEARCHER-02-08` sudah ada |

Batasan "tidak bisa mengeluarkan diri sendiri" bukan kerapian: `submitted_by` adalah
sumbu access control BR-SM-07, dan pengajuan tanpa ketua tidak punya siapa pun yang
boleh menyentuhnya. Yang ingin melepas kepemilikan memakai jalur transfer
(BR-SM-09), bukan jalur keluar.

---

## 11. Anggaran — tidak bisa disunting, tidak bisa dihapus

**Masalah.** `SaveBudgetLineItem` hanya memanggil `BudgetLineItem::create()`
(`app/Actions/Budget/SaveBudgetLineItem.php:53`). Route-nya cuma POST
`/submissions/{submission}/budget-items`. Tidak ada update, tidak ada delete, di
lapis mana pun.

Dua aturan langsung terbentur:

1. **BR-BUD-08** mewajibkan setiap *perubahan* saat NEEDS_REVISION tercatat dengan
   **nilai sebelum dan sesudah**. "Sebelum" hanya punya arti kalau ada operasi
   update — yang tidak ada. `[BUD-AUDIT-01]` membuktikannya lewat `$item->update()`
   langsung ke model, dan audit-nya dihasilkan observer di
   `app/Models/BudgetLineItem.php:75`, bukan oleh Action mana pun.
2. **BR-BUD-01** melarang submit selama grand total melewati `scheme.max_budget`.
   Peneliti yang terlanjur melewati batas tidak punya cara menurunkannya —
   pengajuannya **permanen tidak bisa disubmit**.

Yang kedua membalik keputusan [§5](#5-anggaran-melebihi-batas--kontradiksi-yang-diperbaiki).
Di sana aturan dimenangkan atas UI dengan asumsi peneliti bisa memperbaiki
anggarannya. Asumsi itu tidak pernah benar.

Detail yang memperjelas bahwa ini kelalaian dan bukan keputusan: `BudgetLineItem`
**sudah memakai `SoftDeletes`** dan tabelnya sudah punya `deleted_at`. Infrastruktur
penghapusannya terpasang lengkap dan tidak pernah bisa dijangkau.

**Keputusan.** Penyuntingan dan penghapusan line item dibuat, memakai soft delete yang
sudah ada.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-BUD-10** baru — line item disunting dan dihapus oleh pemegang `update-budget` yang sama (BR-BUD-03), dengan gerbang status yang sama (BR-BUD-02); penghapusan adalah soft delete |
| Skenario | `[BUD-ITEM-05..10]` |
| UI | `RESEARCHER-02-04` perlu kolom aksi per baris |

Soft delete dipilih karena BR-BUD-08: nilai yang pernah masuk rencana anggaran adalah
bahan dispute antara reviewer dan researcher. Baris yang hilang tanpa jejak membuat
pertanyaan "kenapa angkanya turun" tidak terjawab.

---

## 12. Target luaran — tombol hapus yang tidak menuju ke mana-mana

**Masalah.** `RESEARCHER-02-07` (wizard langkah 3) dan `RESEARCHER-02-11` (tab detail)
sama-sama punya tombol "Hapus". `TargetOutputController` punya `store()` dan
`update()`, tanpa `destroy()`; tidak ada route DELETE dan tidak ada
`SubmissionTargetOutput` berpasangan dengan `delete` di `app/`.

Ini gap yang lahir dari gelombang 1 sendiri: [§1](#1-target-luaran--di-mana-ia-hidup)
membangun satu tab khusus supaya target bisa diubah sepanjang siklus (BR-SM-15), tapi
"diubah" di situ tidak pernah diurai jadi "ditambah, disunting, **dihapus**".

**Keputusan.** Penghapusan target mengikuti gerbang BR-SM-15 yang sudah ada.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-SM-19** baru — target dihapus sampai REJECTED/WITHDRAWN, oleh `submitted_by` saja (sejajar BR-SM-15, berbeda dari BR-RO-07 dan perbedaannya disengaja) |
| Skenario | `[SM-TARGET-10..13]` |
| UI | `RESEARCHER-02-07` dan `RESEARCHER-02-11` sudah ada |

---

## 13. Organisasi — tiga aksi di layar, nol di backend

**Masalah.** `ADMIN-08-02` punya "Tambah Unit Induk", "Tambah Sub-unit", dan
"Edit Nama / Tipe". `OrganizationController` hanya punya `deactivate()` dan
`destroy()`. Di DDD, BR-IAM hanya mengatur penghapusan (BR-IAM-08) dan deaktivasi
(BR-IAM-14) — pembuatan, penyuntingan, dan pemindahan unit tidak pernah jadi aturan.

Yang memperburuk, BR-IAM-14 menolak deaktivasi selama masih ada user aktif di
subtree, dengan obat yang tertulis di aturannya sendiri: *"pindahkan orangnya lebih
dulu"*. Obat itu tidak bisa diminum. `UserController::update()` hanya menyentuh
`name`, `email`, `password`, role, dan permission — tidak ada `organization_id` di
jalur mana pun di seluruh aplikasi.

Jadi BR-IAM-14 memberi resep yang sistemnya tidak sediakan obatnya, dan satu-satunya
jalan keluar Operator adalah menonaktifkan user satu per satu — yang justru mengubah
keadaan orang demi merapikan struktur.

**Keputusan.** Pengelolaan pohon organisasi dan pemindahan user jadi use case
eksplisit.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-IAM-17** baru — unit dibuat, diganti nama, dan dipindah induknya; pemindahan ditolak kalau menciptakan siklus atau memindahkan unit ke bawah keturunannya sendiri. **BR-IAM-18** baru — user dipindah antar unit, dan pemindahan adalah prasyarat yang disebut BR-IAM-14 |
| Skenario | `[IAM-ORG-09..15]` |
| UI | `ADMIN-08-02` sudah ada |

Pemindahan unit butuh penjagaan siklus karena access control BR-FE-02 menelusuri
rantai leluhur lewat CTE rekursif. Pohon yang punya siklus membuat query itu tidak
berhenti — kegagalannya muncul sebagai timeout di layar peneliti yang tidak
bersalah, jauh dari tempat kesalahannya dibuat.

---

## 14. `Partner` dan `StudentMember` — entitas yang tidak pernah lahir

**Masalah.** `docs/requirement-analysis/ddd/core/01_submission.md:187` mendeklarasikan
keduanya sebagai kelas dalam agregat, lengkap dengan relasi
`FormSubmission "1" --> "0..*"`. Tabel *Feature Gap* di file yang sama bahkan
menuliskan tugasnya: *"Partner input | Tabel `submission_partners`, UI"*.

Keadaan sekarang: tidak ada model, tidak ada migration, tidak ada controller, tidak
ada BR, **tidak ada satu pun skenario**, tidak ada test. Turunannya ikut hilang —
`max_student_members` terdaftar sebagai kunci `scheme.rules` di
`02_ubiquitous_language.md:106` dan `04_scheme.md:76`, tapi nol kemunculan di `app/`.

`uncovered.txt` tetap `max: 0` karena keduanya tidak pernah dirumuskan sebagai
perilaku. Ini satu-satunya baris Feature Gap DDD yang belum dikerjakan.

**Keputusan (2026-09-19): dipisah, karena keduanya tidak sekuat itu.** Dua
kemungkinannya sama masuk akal untuk satu entitas dan tidak seimbang untuk yang lain:

| Opsi | Konsekuensi |
| ---- | ----------- |
| **A — keduanya dibuat** | Dua tabel, dua langkah wizard tambahan (atau perluasan langkah 4), BR + skenario baru, `max_student_members` jadi rule yang ditegakkan |
| **B — keduanya dibatalkan** | Diagram agregat `core/01_submission.md` dan baris Feature Gap-nya disunting; `max_student_members` dihapus dari contoh `scheme.rules`; kebutuhannya diserap FormField dinamis |

**Opsi B untuk `Partner`, opsi A untuk `StudentMember`.** Mitra eksternal hanya butuh
nama institusi dan kontak — persis yang bisa dilayani FormField dinamis tanpa tabel
sendiri, dan wireframe tidak pernah memberinya layar. `StudentMember` berbeda: ia punya
rule skema (`max_student_members`) yang hanya bisa ditegakkan kalau anggotanya terhitung
sebagai baris, dan keterlibatan mahasiswa adalah angka yang dilaporkan LPPM ke luar.

`StudentMember` dicatat sebagai **data, bukan akun**. Mahasiswa tidak mendaftar ke
SIMPAS, jadi barisnya tidak menunjuk `UserProfile` seperti `ResearchMember`; memaksanya
punya akun berarti menuntut ratusan pendaftaran yang tidak pernah dipakai untuk apa pun.
Cek konflik kepentingan BR-SM-08 sengaja **tidak** disalin ke sini: mahasiswa tidak
pernah bisa jadi reviewer, dan menyalinnya hanya menyiratkan larangan yang tidak ada.

| Lapis | Wujud |
| ----- | ----- |
| DDD | **BR-SM-20** baru; kelas `Partner` dan baris *Feature Gap*-nya dihapus dari `core/01_submission.md` |
| Skenario | `[SM-STUDENT-01..06]` |
| Register | [#39](../requirement-analysis/bdd/open-questions.md) |

---

## 15. `require_external_reviewer` — rule tanpa turunan

**Masalah.** Terdaftar di contoh `rules` JSONB (`04_scheme.md:76`). Nol kemunculan di
`app/`, `tests/`, maupun `bdd/`. BR-REV-01 hanya mengikat `min_reviewer_count`,
BR-REV-04 hanya `max_reviewer_workload`. Satu kunci di katalog rule yang tidak punya
penegak di lapis mana pun.

**Keputusan.** Dihapus dari contoh, bukan diimplementasikan.

Alasannya: komposisi reviewer sudah punya sumbu pengaturnya sendiri di BR-REV-01, dan
menambah sumbu kedua yang hanya hidup sebagai contoh dokumentasi akan melahirkan rule
yang tidak pernah diuji siapa pun. Kalau LPPM memang menghendaki skema tertentu wajib
punya reviewer eksternal, itu BR baru dengan skenarionya sendiri — bukan kunci yang
diam-diam sudah ada.

Contoh `rules` di `04_scheme.md` kini memuat kunci yang **semuanya** ditegakkan, dan
menyatakan itu di komentarnya sendiri — supaya pembacanya tidak salah menyimpulkan mana
yang hidup.

---

## 16. Perilaku hapus dan nonaktif — audit menyeluruh

Empat butir di atas ([§9](#9-undangan-registrasi--dua-jalur-tanpa-pintu-masuk),
[§10](#10-anggota-penelitian--ui-menyuruh-backend-tidak-bisa),
[§11](#11-anggaran--tidak-bisa-disunting-tidak-bisa-dihapus),
[§12](#12-target-luaran--tombol-hapus-yang-tidak-menuju-ke-mana-mana)) semuanya
menyangkut penghapusan. Kesamaan itu bukan kebetulan, jadi seluruh operasi hapus dan
nonaktif di sistem ini ditelusuri sekaligus — bukan hanya yang muncul dari wireframe.

Hasilnya terbelah tajam. Bagian domain yang aturannya ditulis sadar punya perlakuan
yang **konsisten dan benar**. Bagian yang diwarisi fork punya empat jalur penghapusan
tanpa penjaga apa pun, dan salah satunya bisa menghapus seluruh riwayat pengajuan
sistem dalam satu klik.

### 16.1 Prinsip yang sebenarnya sudah dianut repo ini

Prinsipnya tidak pernah ditulis di satu tempat, tapi terbaca jelas dari aturan yang
sudah ada:

| Aturan | Yang dinyatakannya |
| ------ | ------------------ |
| BR-IAM-05 | Deaktivasi user **tidak** delete data |
| BR-IAM-14 | Organisasi **dinonaktifkan**, bukan dihapus, begitu ia pernah dipakai |
| BR-IAM-08 | Penghapusan organisasi **ditolak** selama subtree-nya masih berisi profil |
| BR-SC-01 | Entity acuan yang masih dipakai **tidak bisa** dihapus |
| BR-FE-* | FormField ditandai terhapus, **tidak dihilangkan** dari basis data |
| BR-REV-10 | SubmissionReviewer lama di-mark `replaced`, **tidak dihapus** |
| BR-RPT-04 | Audit log **append-only** |
| BR-RO-06 | Luaran tetap ada sebagai data historis setelah WITHDRAWN |
| BR-SM-13 | Pengajuan **diarsipkan**, tidak dihapus |
| BR-BUD-05 | Komponen nonaktif berhenti ditawarkan, item lama **tidak disentuh** |

Dirangkum jadi satu kalimat yang berlaku di seluruh sistem:

> **Nonaktifkan, jangan hapus. Kalau memang harus hapus, tolak selama masih ada yang
> merujuknya — dan sebutkan apa yang merujuk serta berapa banyak.**

Kalimat kedua sama pentingnya dengan yang pertama. `[IAM-ORG-01]` tidak sekadar
menolak; wireframe `ADMIN-08-02` menyebut *"4 unit turunannya masih memiliki total 38
user aktif"*. Penolakan yang tidak memberi tahu apa penghalangnya memaksa Operator
menebak, dan tebakan pada operasi merusak adalah cara data hilang.

### 16.2 Empat kelas perlakuan

Setiap entitas yang bisa "hilang" dari pandangan pengguna masuk tepat satu kelas.
Menentukan kelasnya adalah keputusan domain, bukan keputusan teknis — dan itulah yang
tidak pernah dilakukan untuk entitas di §16.4.

| Kelas | Mekanisme | Dipakai saat | Bisa dikembalikan |
| ----- | --------- | ------------ | ----------------- |
| **1 — Tidak pernah hilang** | append-only atau arsip | Jejaknya justru gunanya | — |
| **2 — Deaktivasi** | `is_active = false` | Entitas acuan yang masih dirujuk data lama | Ya, seketika |
| **3 — Soft delete** | `deleted_at` | Data milik pengguna yang boleh dikoreksi tapi riwayatnya bernilai | Ya, lewat Operator |
| **4 — Hard delete bersyarat** | `DELETE` + guard referensial | Hanya saat **tidak ada** yang merujuk | Tidak |

Empat syarat yang berlaku lintas kelas, dan wajib dipenuhi sebelum sebuah operasi
merusak dianggap selesai:

1. **Guard referensial menyebut angka.** "Tidak dapat dihapus" saja memaksa menebak.
2. **Jejak audit.** Siapa, kapan, dan apa yang hilang (BR-RPT-09).
3. **Konfirmasi menyebut dampak.** Wireframe sudah memberi contohnya —
   *"Hapus Unit & Cabut 2 Undangan"*, *"menghentikan 9 pemakaian yang tersisa"*.
4. **Cascade harus keputusan sadar.** `onDelete('cascade')` di migrasi adalah
   kebijakan bisnis yang ditulis dalam bahasa skema. Kalau tidak ada BR yang
   menyatakannya, ia kebijakan yang tidak pernah disetujui siapa pun.

### 16.3 Yang sudah tercover — dan benar

Empat belas entitas sudah punya perlakuan lengkap: kelas yang jelas, aturan, skenario,
dan test.

| Entitas | Kelas | Aturan | Skenario |
| ------- | ----- | ------ | -------- |
| `AuditEntry` | 1 | BR-RPT-04 | `[RPT-AUDIT-02]` |
| `FormSubmission` | 1 (arsip) | BR-SM-13 | `[SM-ARCHIVE-03..06]` |
| `ResearchOutput` | 1 | BR-RO-06 | `[RO-OUTPUT-10]` |
| `SubmissionReviewer` | 1 (`replaced`) | BR-REV-10 | `[REV-REASSIGN-01]` |
| `User` | 2 | BR-IAM-05, BR-IAM-10 | `[IAM-DEACT-01..04]`, `[SM-OWNER-03..04]`, `[IAM-EVENT-03]` |
| `Organization` | 2 + 4 | BR-IAM-08, BR-IAM-14 | `[IAM-ORG-01..08]` |
| `Scheme` | 2 | BR-SCH-* | `[SCH-RULE-04..06]`, `[SCH-RULE-15]` |
| `BudgetComponent` | 2 | BR-BUD-05 | `[BUD-ITEM-03..04]` |
| `Reviewer` | 2 | BR-IAM-11 | `[IAM-DEACT-04]`, `[IAM-REVIEWER-05]` |
| `InvitationToken` | 2 (`revoked_at`) | BR-IAM-06 | `[IAM-REG-16]` |
| `FormSubmissionOverride` | 2 | BR-FE-16 | `[FE-OVERRIDE-04..05]` |
| `FormField` | 3 | BR-FE-* | `[FE-CHANGE-01]`, `[FE-TEMPORAL-*]` |
| Entity acuan (4 jenis) | 4 | BR-SC-01, BR-SC-02 | `[SC-CONFIG-01..05]` |
| `ExportJob` | 4 (prune 24 jam) | BR-RPT-12 | `[RPT-EXPORT-10]` |

`Organization` muncul di dua kelas dan itu benar: penghapusan (kelas 4, BR-IAM-08)
dan deaktivasi (kelas 2, BR-IAM-14) adalah dua operasi berbeda dengan dua ambang
berbeda — profil apa pun menghalangi yang pertama, hanya user **aktif** yang
menghalangi yang kedua. Ini justru contoh terbaik di repo tentang bagaimana sebuah
entitas seharusnya diperlakukan.

### 16.4 Yang belum tercover

Enam entitas belum punya kelas sama sekali. Semuanya kode warisan fork, dan tidak
satu pun punya BR maupun skenario.

#### D1 — `Form`: cascade yang tidak pernah diputuskan siapa pun

Yang paling serius di seluruh dokumen ini.

`FormController::bulkDelete` menjalankan `Form::whereIn('id', $ids)->delete()` — hard
delete, tanpa guard apa pun, tanpa audit, tanpa konfirmasi berdampak. `Form` tidak
memakai `SoftDeletes`.

Yang membuatnya merusak bukan baris itu, melainkan pasangannya di migrasi:

```php
// database/migrations/2025_06_04_071454_create_form_submissions_table.php:16
$table->foreignId('form_id')->constrained()
      ->onUpdate('cascade')->onDelete('cascade');
```

Menghapus satu `Form` menghapus **setiap `FormSubmission`** miliknya. Dari sana,
delapan tabel ikut cascade lewat `form_submission_id`:

`form_field_responses` · `research_members` · `budget_line_items` ·
`submission_target_outputs` · `submission_reviewers` · `review_summaries` ·
`research_outputs` · `form_submission_overrides`

Satu klik Admin menghapus seluruh riwayat pengajuan sebuah form beserta anggota,
anggaran, evaluasi, dan luarannya. Audit log-nya selamat — BR-RPT-04 append-only —
tapi setiap subjek yang dirujuknya lenyap, sehingga jejaknya jadi daftar peristiwa
tentang data yang tidak ada lagi.

Tiga hal membuat ini gap spec dan bukan sekadar bug:

1. `Form` **sudah punya `is_active`**. Jalur amannya terpasang dan tidak dipakai
   route penghapusan.
2. Tidak ada satu BR pun yang menyatakan "menghapus Form menghapus riwayatnya".
   Kebijakan sebesar itu hanya hidup sebagai satu kata di migrasi 2025.
3. Ia melanggar prinsip yang repo tegakkan ketat di tempat lain — BR-SC-01 menolak
   menghapus SchemeType yang masih dipakai, sementara Form yang memayungi ribuan
   pengajuan boleh hilang tanpa ditanya.

#### D2 — `FormPhase` & `FormPhaseDetail`: kegagalan FK yang bocor ke layar

`FormPhaseController::bulkDelete` menghapus `formPhaseDetails()` lalu phase-nya, juga
tanpa guard. `FormPhase` punya `is_active` yang tidak dipakai.

Bedanya dengan D1, `form_submissions.form_phase_detail_id` memakai `constrained()`
**tanpa** cascade. Jadi penghapusan detail yang masih dirujuk pengajuan tidak merusak
data — ia gagal di tingkat basis data, tertangkap `catch (\Exception $e)` generik, dan
mendarat di layar Admin sebagai:

> *Gagal menghapus tahap formulir terpilih: SQLSTATE[23503]…*

Integritasnya selamat secara kebetulan, bukan karena aturan. Yang dilihat Admin adalah
pesan Postgres mentah, bukan aturan domain yang menjelaskan apa yang menghalangi dan
berapa banyak.

#### D3 — `SubmissionPeriod`: menghapus tanggal yang diwajibkan aturan lain

`SubmissionPeriodController::destroy` menghapus `submissionDates()`,
`submissionPeriodPhases()`, lalu periodenya — tanpa memeriksa pengajuan yang
merujuknya, dan `submission_period_id` termasuk FK yang cascade.

`submissionDates()` membuatnya lebih tajam: BR-FE-07 mewajibkan
`FormPhaseDetail.submission_date_id` NOT NULL. Menghapus tanggalnya membuat aturan
yang seluruh Form Engine andalkan jadi tidak terpenuhi.

#### D4 — `User`: jalur hapus yang menembus penjagaan jalur nonaktif

`UserController::delete` menjalankan `$user->delete()` — soft delete, karena `User`
memakai `SoftDeletes` — dengan satu-satunya penjagaan berupa cek role (tidak boleh
menghapus Super Admin/Admin kecuali oleh Super Admin).

Ini membalik seluruh susunan penjagaan identitas:

| Operasi | Penjagaan |
| ------- | --------- |
| **Nonaktifkan** user yang jadi lead pengajuan aktif | Ditolak sampai kepemilikan dipindah — BR-IAM-10, BR-SM-09, `[SM-OWNER-03]`, `[SM-OWNER-04]` |
| **Hapus** user yang sama | Tidak ada penjagaan sama sekali |

Jalur yang lebih ringan dijaga ketat; jalur yang lebih merusak dibiarkan terbuka. Dan
BR-IAM-05 memang tidak pernah membayangkan penghapusan user — kalimatnya berbunyi
"Deaktivasi User tidak delete data", bukan "user tidak pernah dihapus". Route
`DELETE /users/{user}` mengisi celah kalimat itu dengan perilaku yang tidak pernah
disepakati.

#### D5 — `OutputTypeDefinition`: `is_active` yang tidak ada pengelolanya

Dibaca di mana-mana — `SaveTargetOutput.php:69` lewat `activeCodes()`,
`ResearchOutputGuard`, dan `SubmissionViewController` yang menyaring
`where('is_active', true)`. Tidak ada satu pun jalur yang **menulisnya**: tidak masuk
`ReferenceEntityRegistry`, tidak punya route, tidak punya controller.

Jadi seluruh pembacaan itu menyiapkan diri untuk keadaan yang tidak mungkin terjadi.
`[RO-OUTPUT-09]` lulus karena test-nya menyemai definisi langsung — pola yang sama
dengan [§8](#8-kenapa-gap-ini-tidak-tertangkap-pelacakan-yang-ada).

Taksonomi luaran adalah keputusan LPPM, bukan kosakata mesin, jadi ia memenuhi syarat
masuk `ReferenceEntityRegistry` persis seperti komponen anggaran.

#### D6 — `ReviewEvaluationForm`: `destroy()` tanpa route

`ReviewEvaluationFormController::destroy` ada di baris 331 dan tidak pernah
didaftarkan di `routes/web.php`. Modelnya punya `is_active` yang juga tidak punya
jalur pengubah.

Ini kode mati, dan kode mati pada operasi merusak berbahaya justru karena ia terbaca
seolah kapabilitasnya sudah ada dan sudah dipikirkan.

### 16.5 Keputusan

Keenamnya diselesaikan dengan memberi kelas, bukan dengan menambal guard satu per satu.

| # | Entitas | Kelas | Aturan baru | Skenario |
| - | ------- | ----- | ----------- | -------- |
| D1 | `Form` | 2 — deaktivasi; hapus hanya saat nol pengajuan | **BR-FE-19** | `[FE-DELETE-01..05]` |
| D2 | `FormPhase`, `FormPhaseDetail` | 2 — sama | **BR-FE-20** | `[FE-DELETE-06..09]` |
| D3 | `SubmissionPeriod` | 2 — sama | **BR-FE-21** | `[FE-DELETE-10..12]` |
| D4 | `User` | 2 — **tidak pernah dihapus** | **BR-IAM-19** | `[IAM-DEACT-05..08]` |
| D5 | `OutputTypeDefinition` | 2 — masuk data acuan | **BR-SC-04** | `[SC-CONFIG-06..09]` |
| D6 | `ReviewEvaluationForm` | 2 — deaktivasi | **BR-FE-22** | `[FE-DELETE-13..14]` |

Tiga konsekuensi yang perlu disebut terang-terangan karena menyentuh kode yang sudah
berjalan:

**`onDelete('cascade')` pada `form_submissions.form_id` dicabut.** Selama ia ada,
BR-FE-19 hanya jadi penjagaan di lapis aplikasi yang bisa dilewati query mana pun.
Ini butuh migrasi, dan migrasinya menyentuh tabel inti — jadi ia pekerjaan yang
dijadwalkan, bukan disisipkan.

**Route `DELETE /users/{user}` dihapus.** BR-IAM-19 menyatakan user tidak pernah
dihapus, jadi jalurnya ikut hilang, bukan sekadar dijaga. Menyisakan route yang selalu
menolak berarti menyisakan pertanyaan kenapa ia ada.

**`ResearchOutput` ditegaskan kelas 1.** BR-RO-06 sudah menyatakan luaran tetap ada
setelah WITHDRAWN, tapi tidak pernah menyatakan bahwa **peneliti pun tidak bisa
menghapusnya**. `ResearchOutputController` kebetulan tidak punya `destroy()`, dan
"kebetulan" bukan jaminan. `[RO-OUTPUT-23..24]` mengunci ketiadaan itu sebagai
perilaku yang disengaja — luaran yang salah **dikoreksi lewat update**, tidak
dihilangkan, karena ia dasar pelaporan LPPM ke luar.

### 16.6 Yang dipakai untuk memeriksa berikutnya

Daftar periksa di bawah dipakai setiap kali sebuah operasi hapus atau nonaktif
diusulkan. Keenam temuan di §16.4 masing-masing gagal di butir 1 — dan karena gagal di
butir 1, tidak ada butir lain yang sempat ditanyakan.

1. Entitas ini masuk kelas berapa, dan **aturan mana** yang menyatakannya?
2. Apa yang merujuknya, dan apakah penolakannya menyebut **berapa banyak**?
3. Apakah ada FK cascade yang diam-diam jadi kebijakan bisnis?
4. Apakah operasinya meninggalkan jejak audit lengkap dengan pelakunya?
5. Apakah konfirmasinya menyebut dampak, bukan sekadar "Yakin?"
6. Apakah jalur yang **lebih merusak** dijaga minimal seketat jalur yang lebih ringan?
7. Apakah skenarionya membuktikan lewat **pelaku manusia**, bukan hanya lewat model?

Butir 6 adalah yang menangkap D4, dan butir 7 yang menangkap [§9](#9-undangan-registrasi--dua-jalur-tanpa-pintu-masuk)
dan [§11](#11-anggaran--tidak-bisa-disunting-tidak-bisa-dihapus). Keduanya tidak akan
pernah muncul dari membaca tabel BR saja.

---

## 17. Form berubah saat pengajuan sedang berjalan

Pertanyaannya: sebuah Form aktif sedang dipakai, Admin menambah satu pertanyaan, dan
pengajuan-pengajuan yang lahir sebelumnya otomatis tidak punya jawaban untuk
pertanyaan itu. Bagaimana sistem seharusnya menanganinya?

### 17.1 Untuk penambahan field, jawabannya sudah ada dan sudah benar

Ini bukan gap. Repo ini sudah memakai **temporal binding**, dan penerapannya termasuk
yang paling rapi di seluruh sistem.

`FormField::isRequiredFor()` (`app/Models/FormField.php:63`) memutuskan kewajiban per
pengajuan, bukan per form:

```php
if ($this->created_at > $submission->created_at)        return false;
if ($this->required_since?->gt($submission->created_at)) return false;
return $this->is_required;
```

Yang dinyatakannya: sebuah field baru diwajibkan untuk sebuah pengajuan hanya kalau ia
**sudah ada** dan **sudah wajib** sebelum pengajuan itu lahir (BR-FE-11).

Jadi `null` pada pengajuan lama bukan kerusakan yang perlu diperbaiki — ia keadaan yang
sah. Field barunya tetap **tampil** bagi peneliti, tapi sebagai opsional, dan submit
tidak terhalang. Enam mekanisme menjaga itu tetap benar:

| Mekanisme | Aturan | Letak |
| --------- | ------ | ----- |
| `required_since` disetel saat aturannya berubah, **tidak pernah mundur** | BR-FE-18 | `FormField::booted()` |
| Jangkar waktunya baris `form_submissions` yang lahir di **langkah 1** wizard | ADR 0012 | — |
| Jangkar tetap `created_at` asli saat **resubmit** | register #27 | `ResubmitFormSubmission.php:42` |
| Field tidak pernah hard delete, jawaban lama tetap terbaca | BR-FE-12 | `[FE-CHANGE-01]` |
| Admin diberi tahu berapa draft terdampak, notifikasi **ditawarkan** | BR-FE-13 | `[FE-CHANGE-02..04]` |
| Pengajuan yang sudah dikirim **sengaja tidak** dihitung terdampak | keputusan sadar | `[FE-CHANGE-04]` |

Baris ketiga yang paling mudah terlewat: tanpa itu, pengajuan yang masuk
NEEDS_REVISION lalu direvisi akan menarik semua field wajib baru ke dalam dirinya —
peneliti dihukum karena proposalnya perlu diperbaiki.

Ini memang praktik yang dianjurkan untuk form berversi: **aditif dan tidak retroaktif.**
Dua alternatifnya lebih buruk. Mengunci Form selama ada draft berjalan berarti Admin
tidak pernah bisa memperbaiki salah ketik. Memberlakukan surut berarti pengajuan yang
sedang berjalan mendadak tidak valid karena keputusan orang lain — kegagalan klasik
sistem form. Versioning penuh (setiap Form punya versi, setiap pengajuan dipatok ke
satu versi) memberi hasil yang sama dengan ongkos jauh lebih besar; temporal binding
mencapainya dengan satu perbandingan timestamp.

### 17.2 Yang tidak dijaga: bentuk field, bukan keberadaannya

Temporal binding menjaga **kewajiban** field sepanjang waktu. BR-FE-12 menjaga
**keberadaan** field. Tidak ada satu pun yang menjaga **bentuknya** — dan di situlah
penambahan pertanyaan punya saudara yang jauh lebih merusak.

`FormController::update` menyimpan field begini:

```php
$field->field_type_id = $fieldData['field_type_id'];   // tanpa penjagaan apa pun
```

Admin bisa mengubah field `text` menjadi `select`, `number`, atau `file` pada Form yang
sudah menyimpan ratusan jawaban. `FormFieldResponse` hanya punya tiga kolom —
`form_submission_id`, `form_field_id`, `value` — jadi nilai lama tetap tersimpan tapi
berhenti bisa dibaca oleh renderer dan validator tipe barunya. Tidak ada yang gagal,
tidak ada yang memberi tahu; jawabannya sekadar berhenti punya arti.

Opsi pilihan lebih tajam lagi:

```php
$field->formFieldOptions()->whereNotIn('id', $existingOptionIds)->delete();
// dan, kalau payload-nya tidak memuat options sama sekali:
$field->formFieldOptions()->delete();
```

Hard delete — `FormFieldOption` tidak memakai `SoftDeletes` — tanpa memeriksa apakah
ada jawaban yang memilih opsi itu. Dan karena `FormFieldResponse.value` menyimpan
nilainya sebagai string tanpa FK ke `form_field_options`, jawaban lama **selamat tapi
yatim**: ia menunjuk pilihan yang tidak ada lagi di daftar. Pengajuan menampilkan
jawaban yang tidak bisa dipilih ulang, dan laporan apa pun yang men-join ke opsi yang
berlaku menjatuhkannya diam-diam.

Cabang `else`-nya menghapus **seluruh** opsi saat payload tidak memuat `options` —
satu permintaan yang kekurangan satu kunci menghapus seluruh pilihan sebuah field.

Tidak ada BR, tidak ada skenario, dan tidak ada test untuk satu pun dari ketiganya.

### 17.3 Keputusan

Prinsipnya diperluas dari "field tidak dihapus" menjadi **"field yang sudah punya
jawaban tidak berubah bentuk"** — perluasan yang wajar, karena keduanya melindungi hal
yang sama: jawaban yang terlanjur diberikan orang.

| # | Perilaku | Aturan baru | Skenario |
| - | -------- | ----------- | -------- |
| 1 | Tipe field **tidak bisa diubah** begitu ada `FormFieldResponse` yang merujuknya; yang dibutuhkan Admin adalah field baru, bukan field lama yang berganti rupa | **BR-FE-23** | `[FE-CHANGE-07..10]` |
| 2 | `FormFieldOption` memakai soft delete; opsi yang sudah pernah dipilih **ditandai tidak aktif**, tidak dihilangkan — ia berhenti ditawarkan untuk jawaban baru, persis BR-BUD-05 pada komponen anggaran | **BR-FE-24** | `[FE-CHANGE-11..14]` |

Butir 2 sengaja meniru BR-BUD-05 kata per kata. Masalahnya identik — acuan yang berhenti
dipakai tapi masih dirujuk data lama — dan sistem ini sudah memutuskan bentuk
jawabannya sekali. Memutuskannya dua kali dengan cara berbeda adalah bagaimana sebuah
domain kehilangan konsistensinya.

Cabang `else` yang menghapus seluruh opsi diperlakukan sebagai bug biasa, bukan
keputusan domain: payload yang tidak memuat `options` berarti "tidak ada perubahan
opsi", bukan "hapus semuanya".

**Yang sengaja tidak diubah.** Penambahan field tetap bebas, tanpa peringatan tambahan,
tanpa gerbang. Ia sudah aman berkat temporal binding, dan memperlakukannya seperti
perubahan tipe akan membuat Admin takut menyentuh Form yang hidup — yang justru
mendorong penyelesaian yang lebih buruk, yaitu membuat Form baru dan memecah riwayat
pengajuan ke dua tempat.
---

## 18. Ringkasan gelombang 2

| Lapis | Perubahan |
| ----- | --------- |
| DDD | **15 BR baru** — BR-SM-18/19/20, BR-BUD-10, BR-IAM-16/17/18/19, BR-FE-19/20/21/22/23/24, BR-SC-04. Dua suntingan: `require_external_reviewer` dihapus dari contoh `04_scheme.md`, dan kelas `Partner` dihapus dari diagram agregat beserta baris *Feature Gap*-nya |
| Skenario | **68 ID baru** dalam 11 keluarga; dua keluarga baru, `[FE-DELETE-*]` dan `[IAM-INVITE-*]`. Seluruhnya **sudah punya test** — plafon `pending.txt` naik 0 → 62 lalu kembali ke 0 di hari yang sama |
| UI | **Nol frame wireframe baru.** Seluruhnya sudah digambar — yang hilang route dan Action-nya. Tiga halaman Inertia dibuat untuk frame yang sudah ada: `ADMIN-08-01`, `SHARED-01-03`, `SHARED-01-04` |
| Kode | Cascade `form_submissions.form_id` dan `submitted_by` diubah jadi `restrict`; route `DELETE /users/{user}` dan `DELETE /profile` dihapus; `FormFieldOption` memakai soft delete; cabang `else` penghapus seluruh opsi di `FormController::update` diperbaiki; tabel `student_members` dibuat |
| Register | Satu entri terselesaikan: [#39](../requirement-analysis/bdd/open-questions.md) `Partner` / `StudentMember` |

Rincian alokasi:

| Keluarga | ID | Sumber |
| -------- | -- | ------ |
| `[IAM-INVITE-*]` | 01..08 | [§9](#9-undangan-registrasi--dua-jalur-tanpa-pintu-masuk) |
| `[SM-MEMBER-*]` | 04..08 | [§10](#10-anggota-penelitian--ui-menyuruh-backend-tidak-bisa) |
| `[BUD-ITEM-*]` | 05..10 | [§11](#11-anggaran--tidak-bisa-disunting-tidak-bisa-dihapus) |
| `[SM-TARGET-*]` | 10..13 | [§12](#12-target-luaran--tombol-hapus-yang-tidak-menuju-ke-mana-mana) |
| `[IAM-ORG-*]` | 09..15 | [§13](#13-organisasi--tiga-aksi-di-layar-nol-di-backend) |
| `[FE-DELETE-*]` | 01..14 | [§16](#16-perilaku-hapus-dan-nonaktif--audit-menyeluruh) D1, D2, D3, D6 |
| `[IAM-DEACT-*]` | 05..08 | [§16](#16-perilaku-hapus-dan-nonaktif--audit-menyeluruh) D4 |
| `[SC-CONFIG-*]` | 06..09 | [§16](#16-perilaku-hapus-dan-nonaktif--audit-menyeluruh) D5 |
| `[RO-OUTPUT-*]` | 23..24 | [§16.5](#165-keputusan) |
| `[FE-CHANGE-*]` | 07..14 | [§17](#17-form-berubah-saat-pengajuan-sedang-berjalan) |
| `[SM-STUDENT-*]` | 01..06 | [§14](#14-partner-dan-studentmember--entitas-yang-tidak-pernah-lahir) |

### Urutan yang disarankan

Bukan urutan kemudahan, melainkan urutan **besarnya kerusakan kalau dibiarkan**:

1. **D1, D4, dan [§17.2](#172-yang-tidak-dijaga-bentuk-field-bukan-keberadaannya)** —
   jalur yang bisa menghilangkan atau membusukkan data sungguhan hari ini. D4 lebih
   dulu karena penutupannya hanya menghapus satu route; §17.2 menyusul rapat karena
   kerusakannya **senyap** — tidak ada yang gagal saat jawaban berhenti punya arti.
2. **[§11](#11-anggaran--tidak-bisa-disunting-tidak-bisa-dihapus)** — satu-satunya
   temuan yang membuat pengajuan **permanen tidak bisa disubmit**, dan itu menimpa
   peneliti yang tidak melakukan kesalahan apa pun.
3. **[§9](#9-undangan-registrasi--dua-jalur-tanpa-pintu-masuk)** — tautan bocor tanpa
   cara dimatikan; satu-satunya temuan dengan konsekuensi keamanan.
4. **[§10](#10-anggota-penelitian--ui-menyuruh-backend-tidak-bisa) dan
   [§12](#12-target-luaran--tombol-hapus-yang-tidak-menuju-ke-mana-mana)** — jalan
   buntu yang terlihat pengguna setiap hari.
5. **D2, D3, D5, D6 dan [§13](#13-organisasi--tiga-aksi-di-layar-nol-di-backend)** —
   perapian yang tidak mendesak selama tidak ada yang menekannya.
6. **[§14](#14-partner-dan-studentmember--entitas-yang-tidak-pernah-lahir)** menunggu
   keputusan produk, bukan menunggu giliran.

### Lampiran — disiplin test yang menutup lubang §8

Bukan aturan domain, jadi tempatnya di sini dan bukan di DDD. Menjadi bagian dari
review PR, sejajar dengan penegakan arah plafon `pending.txt`:

> Skenario yang kalimat *When*-nya menyebut pelaku manusia — "Operator mencabut",
> "peneliti mengeluarkan", "Admin menghapus" — wajib punya **minimal satu** test yang
> melewati pelakunya: route, Policy, lalu Action. Pembuktian di tingkat model boleh
> mendampinginya untuk invarian, tapi tidak boleh berdiri sendiri.

Alasannya persis kejadian di gelombang ini: `[IAM-REG-16]` dan `[BUD-AUDIT-01]` hijau
selama berbulan-bulan sambil menguji perilaku yang tidak bisa dijangkau siapa pun.
Bukan test-nya yang salah — tapi tidak ada yang bertugas menanyakan apakah pintunya
sudah ada.

### Apa yang sudah mendarat

Berbeda dari gelombang 1, gelombang ini **dikerjakan sampai kodenya**. Kelima belas BR
sudah jadi baris tabel di DDD, keenam puluh delapan skenario sudah ditulis dan
seluruhnya punya test Pest, dan setiap kapabilitas yang selama ini hanya digambar
wireframe kini punya Action, Policy, dan route-nya.

Satu temuan lahir dari suite, bukan dari audit: `ProfileController::destroy` warisan
Breeze memanggil `forceDelete()` atas permintaan pemiliknya sendiri, dan
`form_submissions.submitted_by` ber-cascade. Satu klik di halaman profil memusnahkan
seluruh riwayat penelitian orang itu. Audit §16 tidak menemukannya karena ia menelusuri
jalur Operator, bukan jalur pemilik akun — dan itu pelajaran yang lebih berharga
daripada temuannya sendiri: **daftar periksa selalu punya sumbu yang tidak ia lihat.**

Yang **belum** dikerjakan: antarmuka Vue untuk operasi yang Action-nya sudah ada tapi
layarnya bukan frame baru — pengeluaran anggota (`RESEARCHER-02-08`), penghapusan target
(`RESEARCHER-02-11`), penyuntingan anggaran (`RESEARCHER-02-04`), dan pengelolaan pohon
organisasi (`ADMIN-08-02`). Backend-nya lengkap dan teruji; yang tersisa pekerjaan
frontend di frame yang wireframe-nya sudah final.

