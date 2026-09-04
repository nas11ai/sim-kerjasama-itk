# Desain BDD Baseline — Skenario Gherkin SIMPAS v2

**Versi:** 1.0
**Tanggal:** 2026-09-03
**Status:** Menunggu review

Dokumen ini merancang **baseline skenario bisnis berformat Gherkin** untuk SIMPAS v2:
di mana file-nya hidup, bagaimana ia tersambung ke business rule dan wireframe, dan
mekanisme apa yang menjaganya tetap jujur.

Turunan dari [`docs/requirement-analysis/ddd/`](../requirement-analysis/ddd/) (100
business rule bernomor), [`docs/requirement-analysis/ux/`](../requirement-analysis/ux/),
dan wireframe `simpas-wireframe` (57 frame). Berdampingan dengan
[`docs/design/backend-implementation.md`](backend-implementation.md) — dokumen itu
menjelaskan *apa yang dibangun*, dokumen ini menetapkan *apa yang harus terbukti benar*.

---

## 1. Kenapa Ini Dibuat

Tujuannya satu kalimat: **memberi AI agent spesifikasi yang tidak bisa ditebak salah,
dan tidak bisa diam-diam basi.**

DDD sudah bagus, tapi ada tiga hal yang tidak bisa ia lakukan.

**DDD bilang *apa*, bukan *apa yang teramati*.** BR-SM-01 ("satu active submission per
period per scheme") ditegakkan unique index parsial di database. Kalau researcher
menyiapkan dua draft ke skema yang sama lalu submit keduanya, yang kedua kena constraint
violation. Pesan apa yang dilihat researcher? Draft-nya jadi apa? DDD diam, dan agent
yang membacanya akan menebak. Pola ini berulang di hampir semua BR.

**Tiga sumber, tiga sistem penamaan, nol jahitan.** DDD memakai `BR-SM-04`. Wireframe
memakai `RESEARCHER-02-02`. Kode memakai `SubmitFormSubmission`. Belum ada satu artefak
pun yang menyebut ketiganya sekaligus, sehingga tidak ada cara menjawab "frame ini
menegakkan aturan yang mana" selain membaca semuanya.

**Prosa tidak punya penjaga.** Controller legacy sekarang bertentangan dengan DDD —
auto-reject melanggar BR-REV-07 — dan tidak ada yang gagal karenanya. Spesifikasi yang
tidak diverifikasi mesin akan mengalami nasib yang sama.

---

## 2. Keputusan yang Sudah Diambil

| # | Keputusan | Alasan |
| - | --------- | ------ |
| 1 | **Hybrid: `.feature` sebagai spec + guard test**, bukan spec-only, bukan Behat | Spec-only pasti basi — itu persis penyakit yang sedang kita obati. Behat berarti test runner kedua di samping Pest, bootstrap Laravel manual, strategi transaksi DB sendiri, dan perombakan `composer run quality`; ekosistem Behat-Laravel juga sudah lama tidak dirawat. Guard test memberi penegakan tanpa runner kedua. |
| 2 | **Deklaratif domain-level**, frame wireframe sebagai tag | Skenario imperatif ("klik tombol X") pecah tiap kali layout dirapikan dan mengubur aturan bisnis di balik langkah klik. Domain-level memetakan bersih ke Action/Policy — layering yang sudah ditetapkan di CODING_STANDARDS — dan tidak butuh browser runner. |
| 3 | **Keyword Gherkin English, prosa Bahasa Indonesia** | Konsisten dengan aturan repo: penamaan kode English, dokumentasi Bahasa Indonesia. Istilah domain tetap mengikuti [ubiquitous language](../requirement-analysis/ddd/02_ubiquitous_language.md) (`FormSubmission`, `NEEDS_REVISION`). Tetap dikenali semua tooling Gherkin tanpa header locale. |
| 4 | **Lokasi `docs/requirement-analysis/bdd/`** | `AGENTS.md` sudah menyuruh agent membaca `docs/requirement-analysis/ddd/` sebelum menyentuh business logic. `bdd/` sebagai saudaranya adalah kelanjutan instruksi yang sudah ada, bukan tempat baru yang harus diajarkan. Strukturnya mengikuti DDD persis supaya tidak ada pemetaan mental kedua. |

### Prinsip yang diwarisi

Mekanisme di dokumen ini bukan penemuan baru. Ia idiom yang sudah dipakai repo ini,
diterapkan ke spesifikasi:

> **Aturan yang bisa ditegakkan mesin, ditegakkan mesin.**
> — [`CODING_STANDARDS.md`](../../.github/CODING_STANDARDS.md)

`pending.txt` di desain ini adalah `excludePaths` di `phpstan-strict.neon`: daftar utang
yang terlihat, **hanya boleh menyusut**, dan menambah entri wajib dibahas eksplisit di PR.

---

## 3. Struktur Artefak

```
docs/requirement-analysis/bdd/
├── README.md              ← cara baca, konvensi, cara menambah skenario
├── pending.txt            ← skenario belum diimplementasi (hanya boleh menyusut)
├── non-behavioral.md      ← BR yang bukan perilaku + siapa yang menegakkannya
├── open-questions.md      ← register kontradiksi yang menunggu keputusan
│
├── core/
│   ├── submission/
│   │   ├── wizard.feature          ← 7 langkah, last_completed_step, jangkar temporal
│   │   ├── submit.feature          ← guard submit/resubmit, proposal PDF, BR-SM-01
│   │   ├── access.feature          ← isAccessibleBy per peran (BR-SM-07)
│   │   ├── members.feature         ← research/student member, COI (BR-SM-08)
│   │   ├── ownership.feature       ← transfer ownership (BR-SM-09)
│   │   ├── withdrawal.feature      ← withdrawal + freeze (BR-SM-11)
│   │   └── archival.feature        ← isArchived, period tutup (BR-SM-10, BR-SM-13)
│   └── review/
│       ├── assignment.feature      ← min_reviewer_count, workload, COI
│       ├── evaluation.feature      ← ReviewFormResponse, lock setelah submitted
│       ├── outcome.feature         ← auto-approve / auto-needs-revision / reject manual
│       ├── discussion.feature      ← ReviewSummary + threaded comment
│       ├── reassignment.feature    ← replaced, history dipertahankan
│       └── visibility.feature      ← siapa boleh lihat skor kapan (BR-REV-11, BR-REV-12)
│
├── supporting/
│   ├── budget.feature
│   ├── monev.feature
│   ├── research_output.feature
│   ├── scheme.feature
│   └── reporting.feature
│
└── generic/
    ├── form_engine/
    │   ├── temporal_binding.feature   ← BR-FE-11, inti Form Engine
    │   ├── deadline_override.feature  ← BR-FE-08/09/10/15
    │   ├── access_control.feature     ← BR-FE-02, permission + org subtree
    │   ├── form_changes.feature       ← BR-FE-12/13, soft delete + notifikasi draft
    │   ├── concurrency.feature        ← BR-FE-14, optimistic locking
    │   └── submission_gating.feature  ← BR-FE-04, child hanya dari induk APPROVED
    ├── identity/
    │   ├── registration.feature       ← tiga jalur registrasi
    │   ├── verification.feature       ← NIDN, status pending
    │   ├── permission.feature         ← role + direct permission additive
    │   └── deactivation.feature       ← guard ownership (BR-IAM-10)
    ├── notification.feature
    └── system_config.feature
```

Satu file `.feature` = satu tema yang bisa direview sekali duduk. Kalau sebuah file
tumbuh melewati ~25 skenario, itu sinyal temanya dua — pecah, jangan dibiarkan.

---

## 4. Konvensi ID & Tag

### ID skenario

Format `[AREA-TOPIK-NN]`, ditulis **di judul skenario** dalam kurung siku.

```
[SM-SUBMIT-04]   [REV-ASSIGN-05]   [FE-TEMPORAL-01]   [IAM-REG-02]
```

Di judul, bukan sebagai tag, karena ID yang sama harus muncul di deskripsi test Pest —
dan `[SM-SUBMIT-04]` terbaca wajar di kedua tempat. Ini yang menyambungkan spec ke test
tanpa perlu file pemetaan terpisah.

ID bersifat **permanen**. Skenario yang dihapus tidak melepaskan nomornya untuk dipakai
ulang — nomor bekas tetap pensiun, supaya referensi di PR lama tidak berubah makna.

### Tag

| Tag | Kardinalitas | Fungsi |
| --- | ------------ | ------ |
| `@BR-XX-NN` | ≥ 1 | Business rule yang dibuktikan skenario ini |
| `@M0`…`@M5` | tepat 1 | Milestone ([ADR 0009](../adr/0009-milestone-sequencing-critical-path-first.md)) — ini yang membuat spec bisa diiris jadi antrian kerja |
| `@<FRAME-ID>` | 0..n | Frame wireframe, mis. `@RESEARCHER-02-02` |
| `@happy` / `@boundary` / `@denial` | tepat 1 | Kelas skenario — dipakai laporan coverage untuk menunjukkan BR mana yang belum punya jalur gagal |

### Contoh lengkap

```gherkin
Feature: Penugasan Reviewer
  Operator menugaskan reviewer ke submission yang sudah masuk. Jumlah minimum
  berasal dari scheme.rules, bukan konstanta di kode.

  Background:
    Given skema "DIPA Internal" dengan min_reviewer_count 2 dan max_reviewer_workload 10
    And submission "P-001" berstatus SUBMITTED dengan skema "DIPA Internal"

  @BR-REV-01 @M2 @OPERATOR-03-01 @boundary
  Scenario: [REV-ASSIGN-02] Submission tidak masuk review sebelum jumlah minimum terpenuhi
    When Operator menugaskan 1 reviewer ke "P-001"
    Then status "P-001" tetap SUBMITTED
    And Operator melihat indikator "1 dari minimal 2 reviewer"

  @BR-REV-01 @M2 @OPERATOR-03-01 @happy
  Scenario: [REV-ASSIGN-03] Reviewer terakhir memindahkan submission ke UNDER_REVIEW
    Given 1 reviewer sudah ditugaskan ke "P-001"
    When Operator menugaskan 1 reviewer lagi ke "P-001"
    Then status "P-001" menjadi UNDER_REVIEW
    And setiap reviewer menerima notifikasi penugasan

  @BR-REV-02 @BR-SM-08 @M2 @OPERATOR-03-01 @denial
  Scenario: [REV-ASSIGN-07] Anggota penelitian tidak bisa jadi reviewer submission yang sama
    Given "Budi" terdaftar sebagai co_investigator di "P-001"
    When Operator menugaskan "Budi" sebagai reviewer "P-001"
    Then penugasan ditolak karena konflik kepentingan
    And "P-001" tidak punya SubmissionReviewer atas nama "Budi"

  @BR-BUD-02 @M1 @denial
  Scenario Outline: [BUD-LOCK-01] Budget terkunci pada state terminal
    Given submission "P-001" berstatus <status>
    When Lead Researcher mengubah BudgetLineItem di "P-001"
    Then perubahan ditolak dengan 403

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |
```

`Scenario Outline` dipakai untuk boundary yang bisa dienumerasi — delapan status budget
lock jadi satu skenario, bukan delapan. Tiap baris `Examples` **tidak** mendapat ID
sendiri; ID melekat di Outline-nya.

---

## 5. Guard Test

`tests/Unit/Bdd/BddCoverageTest.php`. Berjalan otomatis di `composer run quality` —
tanpa runner Gherkin, tanpa step CI baru, tanpa perubahan gate yang sudah ada.
Ia tinggal di `tests/Unit` dan bukan `tests/Feature` karena `tests/Pest.php`
memasang `RefreshDatabase` ke seluruh `Feature`; guard ini membaca berkas, bukan
tabel, sehingga tidak ada alasan menyeret migrasi Postgres setiap kali ia jalan.

### Sembilan assertion

| # | Assertion | Menangkap |
| - | --------- | --------- |
| 1 | Setiap ID skenario unik di seluruh repo | Copy-paste yang lupa diganti nomornya |
| 2 | Setiap skenario punya ≥1 `@BR-`, tepat satu `@M`, tepat satu kelas | Skenario yatim yang tidak membuktikan aturan apa pun |
| 3 | Setiap `@BR-XX-NN` benar-benar ada di tabel DDD | Typo dan referensi ke BR yang sudah dihapus |
| 4 | Setiap ID skenario muncul di ≥1 test Pest, kecuali terdaftar di `pending.txt` | Spec yang ditulis lalu dilupakan |
| 5 | Tidak ada entri `pending.txt` yang ternyata sudah diimplementasi | `pending.txt` yang membusuk jadi hiasan |
| 6 | Tidak ada entri `pending.txt` yang ID-nya tidak ada di `.feature` mana pun | Entri basi setelah skenario di-rename |
| 7 | **Setiap BR di DDD tercakup ≥1 skenario, atau terdaftar di `non-behavioral.md`, atau di `uncovered.txt`** | BR baru ditambah ke DDD tanpa skenario |
| 8 | Jumlah entri `pending.txt` tidak melebihi plafon yang dideklarasikan di header file itu | Utang yang diam-diam tumbuh |
| 9 | Tidak ada BR di `uncovered.txt` yang ternyata sudah punya skenario | Daftar utang yang membusuk jadi hiasan |

`uncovered.txt` adalah kelonggaran sementara untuk BR yang wave-nya belum tiba —
berplafon dan menyusut ke 0. Assertion 9 menjaganya tetap jujur: begitu sebuah BR
punya skenario, ia **wajib** keluar dari daftar utang. Tanpa itu assertion 7 tidak
pernah protes, karena ia menerima gabungan tercakup ∪ non-behavioral ∪ uncovered —
celah yang sempat membuat BR-IAM-09 tercatat sebagai utang padahal `[FE-ACCESS-06]`
sudah membuktikannya. Tanpanya assertion 7 baru bisa dinyalakan
setelah wave terakhir, dan assertion yang menganggur berbulan-bulan sama saja
dengan tidak ada.

Assertion 7 adalah yang menutup loop. Tanpanya, DDD bisa tumbuh meninggalkan BDD dan
tidak ada yang tahu. Dengannya, menambah baris di tabel business rule memaksa penulisnya
memilih: tulis skenarionya, atau nyatakan eksplisit bahwa ini bukan perilaku.

Assertion 8 sengaja **tidak** membandingkan terhadap base branch — itu menuntut akses git
dari dalam test dan pecah di checkout dangkal. Sebagai gantinya `pending.txt` membawa
plafonnya sendiri di baris pertama:

```
# max: 355
SM-SUBMIT-04
REV-ASSIGN-03
...
```

Guard menegakkan `jumlah entri ≤ max`. Menambah skenario pending berarti menaikkan
angka itu — diff satu baris yang mustahil terlewat saat review. Yang menegakkan angka
itu **hanya boleh turun** adalah review PR, bukan mesin. Ini persis pembagian kerja yang
sudah berlaku untuk `excludePaths` di `phpstan-strict.neon`: mesin menjaga plafon,
manusia menjaga arahnya.

### Cara assertion 4 mencocokkan

Guard memindai `tests/**/*.php` mencari pola `[AREA-TOPIK-NN]` di dalam string. Test
Pest cukup menyebut ID di deskripsinya:

```php
it('[REV-ASSIGN-03] reviewer terakhir memindahkan submission ke under review', function () {
    // ...
});
```

Satu test boleh menyebut lebih dari satu ID kalau ia memang membuktikan beberapa
skenario sekaligus, tapi itu bukan yang dianjurkan — skenario yang butuh assertion
berbeda sebaiknya jadi test berbeda.

### Dependency baru

`behat/gherkin` sebagai `require-dev` — **parser saja, bukan runner.** Satu-satunya
penambahan dependency dalam pekerjaan ini, disebut eksplisit karena `AGENTS.md`
melarang perubahan dependency sebagai efek samping.

Alasannya: kita akan banyak memakai `Scenario Outline`, `Examples`, `Background`, dan
docstring. Parser regex buatan sendiri akan pecah pada konstruksi itu, dan guard test
yang salah baca lebih berbahaya daripada tidak ada guard sama sekali — ia memberi rasa
aman palsu. `behat/gherkin` berdiri sendiri tanpa menarik Behat.

---

## 6. Triase Business Rule

Kriterianya satu pertanyaan:

> **Bisakah aturan ini ditulis sebagai perubahan state yang teramati dari sudut pandang
> seorang aktor?**

Kalau ya → skenario. Kalau tidak → `non-behavioral.md`, dengan catatan siapa yang
menegakkannya. Memaksa menulis Gherkin untuk architectural constraint hanya menghasilkan
noise yang menyamarkan skenario asli.

### Bukan perilaku — 9 BR

| BR | Isi | Ditegakkan oleh |
| -- | --- | --------------- |
| BR-FE-03 | `FormFieldResponse` hanya menyimpan scalar | Skema kolom + `ModelSchemaDriftTest` |
| BR-FE-07 | `FormPhaseDetail.submission_date_id` NOT NULL | Constraint database + test migrasi |
| BR-BUD-07 | Grand total selalu computed, tak ada kolom | `ModelSchemaDriftTest` (sudah ada) |
| BR-REV-13 | `ReviewSummary.status` hanya `open`/`resolved` | Tipe enum + schema drift |
| BR-SCH-06 | Menambah rule ke JSONB tanpa migration | Sifat kolom JSONB — tidak ada yang bisa gagal |
| BR-SCH-09 | `scheme_selector` menulis ke kolom, bukan EAV | [ADR 0005](../adr/0005-scheme-selector-writes-to-column.md) + schema drift |
| BR-RPT-01 | Reporting read-only, tak mengubah state | Review arsitektur — tak ada aksi yang bisa diuji |
| BR-RPT-06 | Statistik live query, tanpa cache layer | Keputusan arsitektur |
| BR-NOT-05 | Unread count di-cache, invalidate saat notif baru | Implementation detail |

**Sisa 94 BR bersifat perilaku** dan mendapat skenario.

### Satu BR dipensiunkan, dua menggantikannya

BR-BUD-06 ("SubmissionOuter hanya bisa diubah jika period config mengizinkan") **dicabut**
— lihat §8.1 untuk temuan lengkapnya. Penggantinya dua rule baru di BC Submission:

| BR | Rule |
| -- | ---- |
| BR-SM-14 | Target luaran hanya boleh dari tipe yang terdaftar di `output_type_definitions`; jika submission punya scheme, dibatasi lagi oleh `scheme.rules.allowed_output_types` |
| BR-SM-15 | Target luaran bisa diubah sampai submission REJECTED atau WITHDRAWN. Setiap perubahan setelah APPROVED wajib dicatat di audit trail dengan nilai lama dan baru |

Inventaris berubah dari 100 menjadi **101 BR** (100 − 1 + 2), dengan **92** di antaranya
bersifat perilaku. Wave 1b ikut naik dari 20 ke 21 BR.

### Dua rule ditambahkan saat menutup register

Menutup entri #8 di [`open-questions.md`](../requirement-analysis/bdd/open-questions.md)
menambah dua BR lagi (2026-09-04). Keduanya aturan yang sejak awal ditegakkan sistem
tapi tidak pernah dinomori, sehingga skenarionya terpaksa menumpang BR tetangga:

| BR | Rule |
| -- | ---- |
| BR-FE-16 | Researcher bisa melihat *status* FormSubmissionOverride, tapi tidak `reason`-nya |
| BR-SCH-10 | Scheme `is_active = false` tidak ditawarkan sebagai pilihan pengajuan baru |

Inventaris akhir **103 BR**, dengan **94** bersifat perilaku. Keduanya masuk Wave 1a,
yang karenanya kembali ke 20 BR.

Triase ini adalah **pass awal, bukan final**. Selama penulisan, sebuah BR boleh berpindah
bucket — tapi perpindahannya wajib disertai alasan tertulis di `non-behavioral.md`.
Beberapa yang terlihat arsitektural ternyata bisa dirumuskan sebagai perilaku kalau
dibingkai dari sisi aktor; BR-RPT-04 ("audit log append-only") misalnya tetap masuk
skenario, karena "Operator mencoba menghapus entri audit log → ditolak" adalah perilaku
yang teramati.

---

## 7. Kedalaman & Estimasi

**Kedalaman minimum per BR perilaku:** satu `@happy` + satu `@denial` atau `@boundary`
kalau aturannya punya failure mode. BR yang mengatur transisi otomatis (BR-REV-05,
BR-REV-06, BR-REV-14) mendapat lebih — di situlah kode legacy sudah terbukti salah, dan
di situlah biaya kesalahan paling mahal.

**Lima wave, urut milestone.** Wave 1 dipecah dua; 45 BR sekali telan terlalu besar
untuk direview dengan jujur.

| Wave | Cakupan | BR perilaku | Est. skenario |
| ---- | ------- | ----------- | ------------- |
| 1a | M0 · Form Engine + Scheme (platform) | 20 | ~80 |
| 1b | M1 · Submission + Budget | 21 | ~83 |
| 2 | M2 · Review | 15 | ~55 |
| 3 | M3 · Identity & Access | 10 | ~40 |
| 4 | M4 · Monev + Research Output | 15 | ~55 |
| 5 | M5 · Reporting + Notification + System Config | 13 | ~50 |
| | **Total** | **94** | **~363** |

BR-FE-05 dan BR-FE-06 pindah dari Wave 1a ke Wave 2: keduanya mengatur
`ReviewFormResponse` dan `ReviewSummary`, dan saling mengunci dengan BR-REV-06 dan
BR-REV-08 — menulisnya terpisah berarti menulis separuh aturan tanpa pasangannya.
Sebagai gantinya BR-FE-16 dan BR-SCH-10 masuk ke Wave 1a, jadi angkanya kembali ke 20.

**Wave 1a sudah mendarat** (2026-09-04): 20 BR, 54 skenario di 6 berkas `.feature`,
seluruhnya terdaftar di `pending.txt`. Estimasi ~80 skenario terbukti terlalu tinggi —
`Scenario Outline` menyerap banyak boundary yang semula dihitung sebagai skenario
terpisah.

Urutan mengikuti [ADR 0009](../adr/0009-milestone-sequencing-critical-path-first.md),
bukan urutan nomor BC. Alasannya sama dengan alasan ADR itu sendiri: Form Engine dan
Scheme adalah bagian yang paling sudah jadi di codebase, sehingga skenarionya paling
cepat berubah dari `pending` menjadi hijau — dan itu memvalidasi mekanismenya sebelum
kita menulis 275 skenario sisanya di atas fondasi yang belum terbukti.

---

## 8. Register Kontradiksi

Menulis skenario akan memaksa bertemu tempat-tempat di mana DDD, wireframe, dan kode
legacy saling bertentangan. Ini bukan efek samping — ini salah satu hasil paling berharga
dari pekerjaan ini.

**Aturannya: kontradiksi tidak diputuskan sepihak saat menulis skenario.** Ia masuk
`open-questions.md` dengan opsi dan rekomendasi, diputuskan pemilik produk, baru
skenarionya mengunci keputusan itu. Yang bobotnya besar naik jadi ADR.

Empat yang sudah terlihat sebelum penulisan dimulai:

| # | Kontradiksi | Sumber |
| - | ----------- | ------ |
| 1 | `Gate::before` memberi Super Admin keempat ability `FormSubmissionPolicy` tanpa syarat, sementara `SubmitFormSubmission::run()` menegakkan BR-SM-04 sendiri | `SuperAdminSubmitDivergenceTest` mendokumentasikan tanpa menyetujui; CODING_STANDARDS mencatatnya sebagai utang M1 |
| 2 | Auto-reject di kode warisan fork vs BR-REV-07/BR-REV-14 | [ADR 0006](../adr/0006-rejection-is-manual-only.md) sudah memutuskan arah; skenario harus menguncinya |
| 3 | Enum `SubmissionStatus` frontend 5 nilai vs state backend 8 nilai | Terdaftar sebagai utang di CODING_STANDARDS |
| 4 | BR-SM-01 ditegakkan unique index parsial — perilaku user-facing saat constraint violation belum ditetapkan | Tidak terdefinisi di sumber mana pun |

---

### 8.1 Terselesaikan — BR-BUD-06 dan entitas `SubmissionOuter`

Ditemukan saat analisis, ditelusuri ke dump legacy, diputuskan 2026-09-03. Dicatat di
sini karena ia contoh kerja dari mekanisme di §8: kontradiksi ditemukan, tidak diputuskan
sepihak, lalu dikunci skenario.

**Temuan.** BR-BUD-06 berbunyi "SubmissionOuter hanya bisa diubah jika period config
mengizinkan", padahal "period config" tidak terdefinisi di dokumen mana pun. Penelusuran
ke `database/simpas.sql` menunjukkan masalahnya lebih dalam — ada **dua** entitas "outer"
di sistem legacy, dan DDD mencampur keduanya:

| Tabel legacy | Kolom | Artinya |
| ------------ | ----- | ------- |
| `submission_outers` | `submission_id`, `submission_outer_type_id` — murni pivot | Luaran yang **dijanjikan** peneliti saat mengajukan. Tidak ada kolom uang. |
| `budget_plan_outers` | `submission_budget_plan_id`, `keterangan`, `file_path` | Lampiran bukti di dalam rencana anggaran — entitas yang sama sekali berbeda |

Isi `submission_outer_types` (*Publikasi Ilmiah, Pertemuan Ilmiah, HAKI, Prototype,
Artikel LPPM, Buku, PKS*) adalah taksonomi yang sama dengan `research_outputs.output_type`
di BC Research Output. Route legacy-nya `dashboard/user/luaranPengajuan/{id}`.

Maknanya: **`SubmissionOuter` = luaran yang dijanjikan (target); `ResearchOutput` = luaran
yang direalisasikan (aktual).** Satu taksonomi, dua titik dalam lifecycle.

**Keputusan.**

1. Entitas pindah ke **BC Submission** sebagai `SubmissionTargetOutput` — extension table `FormSubmission`, sejajar `ResearchMember` dan `Partner`. Entitas tanpa uang tidak punya urusan di BC Budget.
2. **Tidak ada "period config" yang diciptakan.** Pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada di [`supporting/04_scheme.md`](../requirement-analysis/ddd/supporting/04_scheme.md). Membuat config di level period berarti dua sumber kebenaran untuk pertanyaan yang sama.
3. Target luaran **tetap bisa diubah setelah APPROVED**, terkunci hanya di REJECTED dan WITHDRAWN — berbeda dari Budget (BR-BUD-02), karena LPPM membutuhkan penyesuaian target selama kegiatan berjalan. Kompensasinya wajib: setiap perubahan pasca-APPROVED dicatat audit trail dengan nilai lama dan baru, mengikuti pola BR-BUD-08.
4. BR-BUD-06 dicabut; BR-SM-14 dan BR-SM-15 menggantikannya (lihat §6).

**Konsekuensi ke dokumen DDD.** Tiga suntingan berikut adalah **prasyarat wave 1b** —
guard assertion 3 menolak tag `@BR-SM-14` selama BR-nya belum ada di tabel DDD:

- `core/01_submission.md` — tambah agregat `SubmissionTargetOutput`, tambah BR-SM-14 dan BR-SM-15
- `supporting/01_budget.md` — hapus `SubmissionOuter` dan `OuterType` dari diagram agregat (atribut `amount` dan `description` di sana keliru; tabel legacy tidak punya keduanya), cabut BR-BUD-06
- `02_ubiquitous_language.md` — tambah istilah **Target Luaran** dan bedakan eksplisit dari **Research Output**

5. Yang boleh mengubah target pasca-APPROVED dibatasi **Lead Researcher**, bukan co-investigator — sejalan dengan semangat BR-SM-04 bahwa hanya `submitted_by` yang mengikat diri secara kontraktual. Ini sengaja berbeda dari BR-RO-07, yang membolehkan co-investigator mengisi luaran *realisasi*: mengisi hasil bukan tindakan yang sama dengan mengubah janji. Dikunci skenario `[SM-TARGET-08]`.

---

## 9. Definition of Done per Wave

Sebuah wave selesai kalau:

1. Semua BR perilaku dalam cakupannya punya ≥1 skenario, atau tercatat di `open-questions.md` sebagai menunggu keputusan
2. Setiap BR dengan failure mode punya minimal satu `@denial` atau `@boundary`
3. Guard test hijau — termasuk assertion 3 dan 7, yang berarti tidak ada referensi BR yatim dan tidak ada BR tak tercakup
4. Skenario yang belum diimplementasi terdaftar di `pending.txt` (ini normal — spec mendahului kode)
5. `composer run quality` hijau
6. Kontradiksi yang ditemukan sudah masuk `open-questions.md`, bukan diputuskan diam-diam

Poin 4 sengaja permisif: wave menulis **spec**, bukan implementasi. Yang tidak boleh
adalah skenario yang tidak terdaftar di mana pun — hijau palsu karena guard tidak tahu
ia ada.

---

## 10. Di Luar Cakupan

Yang **tidak** dikerjakan dokumen ini, supaya tidak ada yang menunggu sia-sia:

- **Mengimplementasikan 355 skenario.** Wave menghasilkan `.feature` + entri `pending.txt`. Menurunkan `pending.txt` adalah pekerjaan M1–M5 yang sudah punya jalurnya sendiri.
- **Skenario UI-level.** Sudah diputuskan domain-level (keputusan 2). Kalau nanti terbukti ada aturan yang maknanya benar-benar hidup di UI dan tak terwakili backend, itu penambahan lapis yang dibahas terpisah — bukan diselundupkan.
- **Refactor controller legacy.** Skenario mengungkap divergensi; memperbaikinya mengikuti strangler ([ADR 0008](../adr/0008-strangler-refactor-of-legacy-controllers.md)) saat flow-nya disentuh.
- **Mengubah `docs/requirement-analysis/ddd/`.** Itu dokumen kesepakatan. Kalau skenario mengungkap BR yang salah atau ambigu, hasilnya adalah entri di `open-questions.md` — bukan edit sepihak.
- **Menurunkan utang PHPStan atau memperbaiki advisory dependency.** Tidak berhubungan.
