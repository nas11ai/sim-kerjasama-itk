# Spec Cakupan BDD — Milestone M1 s/d M5

**Versi:** 1.0
**Tanggal:** 2026-09-04
**Status:** Menunggu review

Spec ini menetapkan **skenario apa saja yang harus ada** supaya seluruh kebutuhan
bisnis SIMPAS v2 terbukti benar — 74 business rule perilaku yang tersisa setelah
Wave 1a mendarat, dipetakan ke 57 frame wireframe.

Ini **bukan** implementation plan. Ia menjawab "apa yang harus terbukti", bukan
"siapa mengerjakan apa dan dalam urutan apa".

| Dokumen | Isi |
| ------- | --- |
| [`bdd-gherkin-baseline.md`](../bdd-gherkin-baseline.md) | **Mekanismenya** — konvensi ID, tag, guard delapan assertion, ledger |
| Spec ini | **Isinya** — BR mana dibuktikan skenario mana, di berkas apa |
| `docs/plans/` | Nanti — urutan eksekusi |

Turunan dari [`docs/requirement-analysis/ddd/`](../../requirement-analysis/ddd/) dan
wireframe `simpas-wireframe` (57 frame).

---

## 1. Berkas per milestone

| Milestone | Spec | BR | Berkas | Skenario |
| --------- | ---- | -- | ------ | -------- |
| M1 · Submission + Budget | [`m1-submission-budget.md`](m1-submission-budget.md) | 21 | 10 | 68 |
| M2 · Review | [`m2-review.md`](m2-review.md) | 15 | 6 | 37 |
| M3 · Identity & Access | [`m3-identity-access.md`](m3-identity-access.md) | 10 | 5 | 22 |
| M4 · Monev + Research Output | [`m4-monev-research-output.md`](m4-monev-research-output.md) | 15 | 2 | 30 |
| M5 · Reporting + Notification + Config | [`m5-reporting-notification-config.md`](m5-reporting-notification-config.md) | 13 | 4 | 22 |
| | **Total** | **74** | **27** | **179** |

Angka skenario di atas **bukan estimasi** — setiap ID sudah dialokasikan satu per satu
di berkas milestone masing-masing, lengkap dengan kelas tag dan BR yang dibuktikannya.
Totalnya lebih rendah dari perkiraan baseline (~310) karena `Scenario Outline` menyerap
banyak boundary yang semula dihitung satu-satu — pola yang sudah terbukti di Wave 1a
(53 skenario nyata vs ~80 perkiraan).

Dengan 54 skenario Wave 1a, inventaris akhir **233 skenario untuk 94 BR perilaku**.

---

## 2. Temuan yang mengubah cara membaca DDD

Menyusun spec ini memaksa membaca 101 baris business rule berdampingan. Dua pola
muncul yang tidak terlihat saat membaca per bounded context.

### 2.1 Sembilan BR ternyata kembar

Beberapa aturan ditulis **dua kali di dua bounded context** karena keduanya
berkepentingan. Kalau ini tidak dikenali, akibatnya bukan sekadar skenario dobel —
melainkan **dua implementasi untuk satu aturan**, yang pasti menyimpang cepat atau
lambat. Itu risiko integritas data, bukan sekadar kerapian.

| A | B | Hubungan | Perlakuan |
| - | - | -------- | --------- |
| BR-SM-12 | BR-FE-14 | **Identik** — optimistic locking `updated_at` | Satu skenario, dua tag. `[FE-LOCK-01..04]` sudah ada → BR-SM-12 lunas tanpa skenario baru |
| BR-REV-08 | BR-FE-05 | **Identik** — `ReviewFormResponse` terkunci setelah `submitted` | Satu skenario, dua tag |
| BR-SM-09 | BR-IAM-10 | **Identik** — transfer ownership sebelum deaktivasi lead | Satu skenario, dua tag |
| BR-SM-06 | BR-BUD-01 | **Identik** — total anggaran ≤ `scheme.max_budget` | Satu skenario, dua tag |
| BR-IAM-07 | BR-REV-02 | IAM-07 **subset** REV-02 | Satu skenario, dua tag |
| BR-SM-08 | BR-REV-02 | **Dua arah** dari satu mutual block | Dua skenario — arahnya memang beda |
| BR-MON-01 | BR-FE-04 | MON-01 = FE-04 diterapkan ke monev | `[FE-CHILD-01/02]` sudah membuktikan mekanismenya; MON-01 tetap butuh skenario akses monev |
| BR-MON-05 | BR-FE-08 | MON-05 = FE-08 diterapkan ke fase monev | Skenario monev sendiri, mekanismenya dirujuk |
| BR-RO-05 | BR-SM-14 | Taksonomi sama, **dua titik lifecycle** (janji vs realisasi) | Dua skenario — memang beda momen |

**Aturan yang dipakai spec ini:** satu skenario boleh membawa dua tag `@BR-` **hanya
kalau ia benar-benar membuktikan keduanya**. Kalau ia cuma menyenggol yang satu, itu
tag dekoratif — persis yang dicabut Ruling A di Wave 1a dan yang melahirkan entri #8
di register.

Konsekuensi ke implementasi: **satu aturan kembar = satu Action/Policy**, dipanggil
dari dua tempat. Bukan dua implementasi yang kebetulan mirip.

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

Dengan sepuluh frame ini, **57 dari 57 frame** terhitung: 47 di M1–M5, 10 di M0.

---

## 6. Supaya spec ini tetap hidup

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

## 7. Definition of Done per milestone

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

## 8. Risiko yang sudah terlihat

| # | Risiko | Terlihat di |
| - | ------ | ----------- |
| 1 | Empat entri register masih terbuka dan **memblokir** sebagian M1/M2 | `open-questions.md` #1–#4 |
| 2 | `SubmissionStatus` frontend 5 nilai vs backend 8 nilai — skenario M1 memakai 8 | register #3 |
| 3 | Perilaku user-facing saat BR-SM-01 kena constraint violation belum ditetapkan | register #4 |
| 4 | Auto-reject legacy masih hidup di `ReviewController` dan melanggar BR-REV-07 — skenario M2 sengaja menguncinya sebagai perilaku yang **dilarang** | ADR 0006, baseline §8 |
| 5 | `SubmissionViewController` (~1000 baris) dan `ReviewController` (~780 baris) menampung logic M1/M2 | AGENTS.md — pakai strangler, ADR 0008 |
