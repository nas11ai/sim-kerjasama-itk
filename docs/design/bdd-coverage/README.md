# Spec Cakupan BDD — Milestone M1 s/d M5

**Versi:** 1.0
**Tanggal:** 2026-09-04
**Status:** Menunggu review

Spec ini menetapkan **skenario apa saja yang harus ada** supaya seluruh kebutuhan
bisnis SIMPAS v2 terbukti benar — 83 business rule perilaku yang tersisa setelah
Wave 1a mendarat, dipetakan ke 65 frame wireframe.

Ini **bukan** implementation plan. Ia menjawab "apa yang harus terbukti", bukan
"siapa mengerjakan apa dan dalam urutan apa".

| Dokumen | Isi |
| ------- | --- |
| [`bdd-gherkin-baseline.md`](../bdd-gherkin-baseline.md) | **Mekanismenya** — konvensi ID, tag, guard delapan assertion, ledger |
| Spec ini | **Isinya** — BR mana dibuktikan skenario mana, di berkas apa |
| `docs/plans/` | Nanti — urutan eksekusi |

Turunan dari [`docs/requirement-analysis/ddd/`](../../requirement-analysis/ddd/) dan
wireframe `simpas-wireframe` (65 frame).

---

## 1. Berkas per milestone

| Milestone | Spec | BR | Berkas | Skenario |
| --------- | ---- | -- | ------ | -------- |
| M1 · Submission + Budget | [`m1-submission-budget.md`](m1-submission-budget.md) | 23 | 10 | 80 |
| M2 · Review | [`m2-review.md`](m2-review.md) | 17 | 7 | 42 |
| M3 · Identity & Access | [`m3-identity-access.md`](m3-identity-access.md) | 10 | 6 | 27 |
| M4 · Monev + Research Output | [`m4-monev-research-output.md`](m4-monev-research-output.md) | 17 | 2 | 37 |
| M5 · Reporting + Notification + Config | [`m5-reporting-notification-config.md`](m5-reporting-notification-config.md) | 16 | 4 | 28 |
| | **Total** | **83** | **29** | **215** |

Angka skenario di atas **bukan estimasi** — setiap ID sudah dialokasikan satu per satu
di berkas milestone masing-masing, lengkap dengan kelas tag dan BR yang dibuktikannya.
Totalnya lebih rendah dari perkiraan baseline (~310) karena `Scenario Outline` menyerap
banyak boundary yang semula dihitung satu-satu — pola yang sudah terbukti di Wave 1a
(53 skenario nyata vs ~80 perkiraan).

Dengan 54 skenario Wave 1a, inventaris akhir **269 skenario untuk 105 BR perilaku**.

---

## 2. Temuan yang mengubah cara membaca DDD

Menyusun spec ini memaksa membaca 101 baris business rule berdampingan. Dua pola
muncul yang tidak terlihat saat membaca per bounded context.

### 2.1 Tujuh aturan ditulis dua kali

Beberapa aturan ditulis di **dua bounded context** karena keduanya berkepentingan.
Kalau ini tidak dikenali, akibatnya bukan sekadar skenario dobel — melainkan **dua
implementasi untuk satu aturan**, yang pasti menyimpang cepat atau lambat. Itu risiko
integritas data, bukan sekadar kerapian.

Sumber kebenarannya sekarang ada di DDD sendiri: setiap pasangan sudah ditandai
kanonik/cermin dan ditautkan dua arah, dengan tabel ringkasnya di
[`ddd/README.md` — Aturan Cermin Lintas Konteks](../../requirement-analysis/ddd/README.md#aturan-cermin-lintas-konteks).
Spec ini hanya mengikuti.

| Kanonik | Cermin | Perlakuan di skenario |
| ------- | ------ | --------------------- |
| BR-FE-05 | BR-REV-08 | `[REV-EVAL-01/02]` bertag keduanya |
| BR-FE-08 | BR-MON-05 | Mekanismenya sudah dibuktikan `[FE-OVERRIDE-01..05]`; `[MON-CYCLE-08/09]` membuktikan penerapannya di fase monev |
| BR-FE-14 | BR-SM-12 | Sudah lunas oleh `[FE-LOCK-01..04]` — **nol skenario baru** |
| BR-BUD-01 | BR-SM-06 | `[BUD-LIMIT-01..03]` bertag keduanya |
| BR-REV-02 | BR-SM-08, BR-IAM-07 | `[REV-ASSIGN-07]` bertag ketiganya; `[SM-MEMBER-01]` membuktikan sisi anggota |
| BR-IAM-10 | BR-SM-09 | `[SM-OWNER-01..04]` bertag keduanya |

**Aturannya:** satu skenario boleh membawa dua tag `@BR-` **hanya kalau ia benar-benar
membuktikan keduanya**. Kalau ia cuma menyenggol yang satu, itu tag dekoratif — persis
yang dicabut Ruling A di Wave 1a dan yang melahirkan entri #8 di register.

### 2.1a Dua yang tampak kembar tapi sengaja dipisah

| A | B | Kenapa tidak digabung |
| - | - | --------------------- |
| BR-SM-14 | BR-RO-05 | Taksonomi `output_type` sama, **entitas berbeda**: target yang dijanjikan vs luaran terealisasi. `[SM-TARGET-08]` melarang Co-Investigator mengubah target, sementara `[RO-OUTPUT-12]` justru membolehkannya mengisi realisasi — perbedaan yang hilang kalau disamakan |
| BR-FE-04 | BR-MON-01 | FE-04 mengatur **pembuatan** child submission (`[FE-CHILD-01..03]`), MON-01 mengatur **akses** ke fase monev (`[MON-CYCLE-01/02]`). Syaratnya sama-sama induk APPROVED, guard-nya beda |

### 2.2 Kode legacy bukan bukti kebenaran

Spec ini **tidak** dikalibrasi terhadap apa yang sudah dikerjakan kode sekarang.
Repo ini fork, sebagian controller-nya gemuk, dan sebagiannya **bertentangan dengan
DDD** — auto-reject yang melanggar BR-REV-07 contohnya. Kalau skenario ditulis dengan
melirik kode yang ada, yang terjadi adalah spesifikasi yang mendokumentasikan bug.

Arahnya sebaliknya: **skenario menetapkan perilaku yang dituju, lalu kode legacy
ditarik ke sana** mengikuti strangler ([ADR 0008](../../adr/0008-strangler-refactor-of-legacy-controllers.md)).
Saat sebuah skenario diimplementasikan, flow yang disentuh diekstrak ke Action +
Policy berikut test-nya. Skenario yang hijau tanpa perubahan kode adalah bonus, bukan
target — dan tidak pernah jadi alasan menulis skenario yang lebih lunak.

Praktisnya, sebuah skenario di spec ini dianggap selesai kalau ada test Pest yang
menyebut ID-nya dan test itu menguji **perilaku yang dijanjikan skenario**, bukan
perilaku yang kebetulan dimiliki kode saat ini.

---

## 3. Konvensi

Seluruhnya diwarisi dari [`bdd-gherkin-baseline.md`](../bdd-gherkin-baseline.md) §4
dan [`bdd/README.md`](../../requirement-analysis/bdd/README.md). Yang paling sering
salah dipakai, diulang di sini:

- **Kelas tag menggambarkan hasil aksi aktor**, bukan sikap aturannya. `@happy` aksi
  berhasil, `@denial` aksi ditolak, `@boundary` batasnya yang jadi pokok. Skenario
  yang aksinya berhasil tapi sesuatu ditahan (mis. field disembunyikan) **bukan**
  `@denial`.
- **ID permanen.** Nomor bekas tidak dipakai ulang. Rentang ID di spec ini sudah
  dialokasikan — jangan menggeser.
- **Deklaratif domain-level.** "Lead Researcher menyimpan anggaran", bukan "klik
  tombol Simpan". Frame wireframe masuk sebagai tag, bukan sebagai langkah.
- Setiap skenario: ≥1 `@BR-`, tepat satu `@M`, tepat satu kelas.

### ID yang sudah terpakai di dokumen lain

Baseline §4 dan §8.1 memakai contoh `[SM-SUBMIT-04]`, `[BUD-LOCK-01]`,
`[REV-ASSIGN-02]`, `[REV-ASSIGN-03]`, `[REV-ASSIGN-07]`, dan `[SM-TARGET-08]`. Spec
ini **mempertahankan makna ID tersebut** supaya contoh di baseline tidak jadi bohong.

---

## 4. Kedalaman minimum

Sama dengan baseline §7: satu `@happy` + satu `@denial`/`@boundary` per BR yang punya
failure mode. Tiga area mendapat lebih, karena di situ biaya kesalahan paling mahal:

| Area | Kenapa lebih dalam |
| ---- | ------------------ |
| Transisi otomatis (BR-REV-05/06/07/14) | Kode legacy **sudah terbukti salah** di sini — auto-reject melanggar BR-REV-07. ADR 0006 memutuskan arahnya; skenario yang menguncinya |
| Conflict of interest (BR-SM-08, BR-REV-02, BR-IAM-07) | Satu aturan dilihat dari tiga sisi; gampang ditegakkan separuh |
| Integritas anggaran & luaran (BR-BUD-*, BR-SM-14/15) | Menyangkut uang dan komitmen kontraktual; salah di sini tidak ketahuan sampai audit |

---

## 5. Cakupan frame wireframe

57 frame, seluruhnya tercakup. Pemetaan detail ada di tiap berkas milestone.

| Grup | Frame | Milestone |
| ---- | ----- | --------- |
| SHARED (auth, onboarding, dashboard) | 13 | M3, sebagian M1 |
| RESEARCHER (submission, monev, luaran, audit) | 12 | M1, M4, M5 |
| REVIEWER | 4 | M2 |
| OPERATOR (submission, review, user, periode, laporan) | 19 | M1, M2, M3, M5 |
| ADMIN (form builder, skema, organisasi, config) | 9 | M0 (sudah), M3, M5 |

Frame **tidak** melahirkan skenario sendiri. Ia tag pada skenario yang aturannya sudah
ada. Frame yang tidak punya aturan di baliknya — mis. layar daftar murni — cukup
muncul sebagai tag di skenario akses yang relevan.

### Frame yang sudah tercakup Wave 1a (M0)

Sepuluh frame adalah layar Form Engine dan Skema, yang aturannya sudah dikunci Wave 1a
dan **tidak diulang** di milestone mana pun:

| Frame | Skenario yang menjaganya |
| ----- | ------------------------ |
| ADMIN-08-05 Daftar Form | `[FE-ACCESS-01..06]` |
| ADMIN-08-06 Edit Form — General & Fields | `[FE-TEMPORAL-01..09]`, `[FE-CHANGE-01..04]` |
| ADMIN-08-07 Edit Form — Access Control | `[FE-ACCESS-01..06]` |
| ADMIN-08-08 Edit Form — Phases | `[FE-CHILD-01..03]` |
| ADMIN-08-09 Edit Phase — Details | `[FE-OVERRIDE-01..11]` |
| ADMIN-08-03 Daftar Skema | `[SCH-RULE-04..06]`, `[SCH-RULE-14]` |
| ADMIN-08-04 Buat / Edit Skema | `[SCH-RULE-01..03]`, `[SCH-RULE-09..10]` |
| OPERATOR-09-01 Periode Pengajuan | `[SCH-RULE-11..13]`, `[FE-OVERRIDE-11]` |
| SHARED-02-03 Dashboard Operator | `[FE-ACCESS-*]` + skenario akses M1 |
| SHARED-02-04 Dashboard Admin | `[FE-ACCESS-*]` + skenario akses M3 |

Dengan sepuluh frame ini, **65 dari 65 frame** terhitung: 55 di M1–M5, 10 di M0.
Delapan frame terbaru lahir dari [gap-closure](../gap-closure.md).

---

## 6. Konfrontasi dengan wireframe — dan penutupannya

Spec ini diuji balik terhadap wireframe, bukan hanya terhadap DDD. Hasilnya menemukan
satu kontradiksi dan lima kebutuhan tanpa layar. **Ketujuhnya sudah ditutup**
(2026-09-04) — jejak lengkapnya di [gap-closure](../gap-closure.md).

| Temuan | Penutupan |
| ------ | --------- |
| `RESEARCHER-02-04` membolehkan submit dengan anggaran melebihi batas skema | Aturan menang; kalimat UI diganti pernyataan penolakan (register #9) |
| Target Luaran tidak punya layar | `RESEARCHER-02-07` (wizard langkah 3) + `RESEARCHER-02-11` (tab detail) |
| Anggota penelitian + COI tidak punya layar | `RESEARCHER-02-08`, lengkap dengan pesan penolakan COI |
| Notifikasi in-app tidak punya layar | `SHARED-02-05`, dijangkau dari Aksi Cepat dashboard |
| Wizard langkah 2, 3, 4, 6, 7 tanpa isi | `RESEARCHER-02-06..10` |
| `REJECTED`, `WITHDRAWN`, `RESUBMITTED` tidak pernah dirender | Ketiganya masuk daftar; `REJECTED` dapat halaman detail `RESEARCHER-02-12` |
| Keterlihatan skor monev berbeda dari pengajuan | BR-MON-09 — monev dikecualikan eksplisit (register #10) |

Wireframe naik dari 57 ke **65 frame**. Yang menuntut keputusan desain paling banyak
adalah Target Luaran: ia ditempatkan di **langkah 3**, bukan langkah tersendiri, karena
BR-SM-14 membatasi pilihan tipenya dengan `scheme.rules.allowed_output_types` — target
tidak bisa dipilih sebelum skema ditentukan. Jumlah langkah tetap tujuh.

Satu temuan **tidak** berujung perubahan UI: wireframe monev ternyata sudah benar, dan
yang kurang justru aturannya. BR-MON-09 ditambahkan supaya perilaku itu punya dasar,
bukan sekadar kebetulan.

---

## 7. Supaya spec ini tetap hidup

Kebutuhan bisnis akan berubah di tengah jalan. Empat hal berikut yang membuat spec ini
bisa ikut berubah tanpa harus dibongkar.

**Satu berkas `.feature` = satu tema.** Kalau sebuah berkas lewat ~25 skenario, temanya
sebenarnya dua — pecah. Rentang ID tidak perlu berurutan dengan nama berkas, jadi
memecah berkas tidak memaksa menomori ulang apa pun.

**Skenario deklaratif tidak pecah saat UI dirapikan.** Karena langkahnya berbunyi
"Lead Researcher menyimpan anggaran" dan bukan "klik tombol Simpan", perubahan layout,
penggantian komponen, bahkan penggantian framework frontend tidak menyentuh satu pun
skenario. Frame wireframe menempel sebagai tag — kalau frame di-rename, yang berubah
hanya tag-nya.

**`Scenario Outline` untuk boundary yang bisa dienumerasi.** Delapan status yang
mengunci anggaran jadi satu skenario dengan delapan baris `Examples`, bukan delapan
skenario. Menambah status baru = menambah satu baris, bukan satu ID baru. Ini juga yang
membuat estimasi skenario turun jauh dari perkiraan awal.

**Menambah BR tidak boleh diam-diam.** Guard assertion 7 memaksa setiap BR baru di DDD
memilih: punya skenario, dinyatakan bukan perilaku, atau masuk `uncovered.txt` yang
berplafon. Tidak ada jalan keempat. Itu yang menjaga spec ini tidak pelan-pelan
ketinggalan dari DDD — penyakit yang persis sedang diobati.

Kalau sebuah aturan ternyata perlu diubah, urutannya: ubah BR di DDD → sesuaikan
skenario → sesuaikan test. Bukan sebaliknya. Skenario yang berubah tanpa BR-nya berubah
adalah tanda keputusannya belum benar-benar diambil.

---

## 8. Definition of Done per milestone

Sama dengan baseline §9, ditambah dua yang lahir dari pengalaman Wave 1a:

1. Semua BR dalam cakupannya punya ≥1 skenario, atau tercatat di `open-questions.md`
2. Setiap BR dengan failure mode punya minimal satu `@denial` atau `@boundary`
3. Guard delapan assertion hijau
4. Skenario yang belum diimplementasi terdaftar di `pending.txt`
5. `composer run quality` hijau
6. Kontradiksi masuk `open-questions.md`, bukan diputuskan diam-diam
7. **BR kembar (§2.1) di cakupan itu ditandai dua tag, bukan ditulis dua kali**
8. **Aturan yang ternyata tidak punya nomor BR dinomori lebih dulu**, bukan ditempelkan
   ke BR tetangga — pelajaran dari register #8

---

## 9. Risiko yang sudah terlihat

| # | Risiko | Terlihat di |
| - | ------ | ----------- |
| ~~1~~ | ~~Entri register terbuka~~ — **register kosong** sejak 2026-09-04; 23 entri terselesaikan, nol terbuka | `open-questions.md` |
| ~~2~~ | ~~`SubmissionStatus` frontend 5 vs backend 8~~ — **diputuskan**: frontend menyusul, diturunkan dari kontrak Inertia | register #3 |
| ~~3~~ | ~~Perilaku saat BR-SM-01 kena constraint~~ — **BR-SM-16** menetapkannya | register #4 |
| 4 | Auto-reject legacy masih hidup di `ReviewController` dan melanggar BR-REV-07 — skenario M2 sengaja menguncinya sebagai perilaku yang **dilarang** | ADR 0006, baseline §8 |
| 5 | `SubmissionViewController` (~1000 baris) dan `ReviewController` (~780 baris) menampung logic M1/M2 | AGENTS.md — pakai strangler, ADR 0008 |
| ~~6~~ | ~~Lima kebutuhan bisnis belum punya layar~~ — **ditutup** 2026-09-04, 8 frame baru | §6, register #11 |
| ~~7~~ | ~~Wireframe anggaran membolehkan submit melebihi batas~~ — **ditutup** 2026-09-04 | §6, register #9 |
