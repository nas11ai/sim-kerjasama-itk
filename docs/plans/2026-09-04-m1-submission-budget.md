# M1 — Submission & Budget: Skenario + Irisan Vertikal

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Menulis 80 skenario Gherkin M1 yang mengunci 23 business rule Submission &
Budget, lalu membuktikan jalur spec→test→hijau lewat irisan vertikal Budget (14
skenario) sampai keluar dari `pending.txt`.

**Architecture:** Dua fase berurutan. Fase A menulis `.feature` (spesifikasi murni, nol
kode) — enam task, tiap task satu tema yang bisa direview sekali duduk. Fase B
mengimplementasikan **hanya** skenario Budget: Action + Policy + test Pest yang menyebut
ID skenario, sehingga ID-nya keluar dari `pending.txt`. Fase B sengaja sempit — ia ada
untuk membuktikan kosakata langkah dan pola fixture di skala nyata **sebelum** 200
skenario M2–M5 ditulis di atas pola yang baru terbukti 6 kali.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · Pest · `spatie/laravel-model-states` ·
`lorisleiva/laravel-actions` · `spatie/laravel-permission` · `behat/gherkin` (parser guard)

**Spec:** [`docs/design/bdd-coverage/m1-submission-budget.md`](../design/bdd-coverage/m1-submission-budget.md)
— plan ini berargumen dari spec itu; keduanya dibaca bersama. Konvensi ID, tag, dan guard
ada di [`docs/design/bdd-gherkin-baseline.md`](../design/bdd-gherkin-baseline.md).

## Global Constraints

- **Setiap task berakhir dengan `composer run quality` hijau sebelum commit.** Pint +
  PHPStan level 5 + PHPStan level 9 (`phpstan-strict.neon`) + seluruh Pest.
- **Commit memakai Conventional Commits, tanpa trailer co-author.**
- **Dilarang menambah dependency.** Semua yang dibutuhkan sudah terpasang.
- **Dilarang mengubah `docs/requirement-analysis/ddd/`.** Kalau skenario mengungkap
  aturan yang salah, hasilnya entri di `open-questions.md` — bukan sunting sepihak.
- **Status hanya berubah lewat `transitionTo()`**, tidak pernah `update(['status' => …])`.
- **Setiap query yang mengembalikan data submission wajib di-scope** `isAccessibleBy()`.
- **Business logic tidak di Controller** — Controller hanya FormRequest → DTO → Action → response.
- **PostgreSQL, bukan MySQL.** Tidak ada `YEAR()`, tidak ada `LIKE` untuk pencarian
  case-insensitive (pakai `whereLike`/`ILIKE`).
- **Dilarang menyiasati PHPStan** — tidak ada `@phpstan-ignore`, entri baseline,
  `assert()`, inline `@var`, atau cast untuk menghilangkan error.
- **Dilarang percabangan khusus testing di production code** (`app()->environment('testing')`).
- **Guard clause, bukan `if` bersarang.** Satu Action = satu use case.
- **Gaya negasi `!$x`, bukan `! $x`** — `pint.json` menyetel
  `not_operator_with_successor_space=false`. Jalankan Pint sebelum commit.
- **Frasa langkah Gherkin wajib memakai kosakata baku** di
  [`docs/requirement-analysis/bdd/README.md`](../requirement-analysis/bdd/README.md)
  bagian *Kosakata langkah*. Frasa baru boleh, tapi barisnya ditambahkan ke tabel itu
  dalam commit yang sama.
- **Keyword Gherkin English, prosa Bahasa Indonesia.** Istilah domain mengikuti
  [ubiquitous language](../requirement-analysis/ddd/02_ubiquitous_language.md) —
  `NEEDS_REVISION`, bukan "perlu revisi".

### Menjalankan perintah

Host PHP 8.5 tidak bisa dipakai (`composer install` gagal di `phpoffice/phpspreadsheet`,
dan `pdo_pgsql` tidak ada). Container wajib.

```bash
# Postgres — sekali saja
docker run -d --name simpas_dev_pg -e POSTGRES_DB=simpas_test \
  -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres \
  -p 55432:5432 postgres:18-alpine
# kalau sudah pernah dibuat: docker start simpas_dev_pg

# Image proyek — sekali saja. php:8.4-cli polos TIDAK punya pdo_pgsql maupun composer.
docker build -t simpas-php84:local - <<'DOCKERFILE'
FROM php:8.4-cli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip git \
 && docker-php-ext-install pdo_pgsql \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/*
DOCKERFILE
```

Alias yang dipakai sepanjang plan ini:

```bash
# PEST — satu berkas atau seluruh suite
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest <args>

# QUALITY — gate penuh
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  -e COMPOSER_HOME=/tmp/composer simpas-php84:local composer run quality
```

---

## File Structure

### Fase A — berkas spesifikasi (nol kode)

| Berkas | Tanggung jawab | ID |
| ------ | -------------- | -- |
| `docs/requirement-analysis/bdd/core/submission/wizard.feature` | Navigasi wizard & prasyarat data | `SM-WIZARD-01..11` |
| `docs/requirement-analysis/bdd/core/submission/submit.feature` | Keunikan, proposal PDF, siapa yang boleh submit | `SM-SUBMIT-01..17` |
| `docs/requirement-analysis/bdd/core/submission/state.feature` | State machine & penguncian terminal | `SM-STATE-01..05` |
| `docs/requirement-analysis/bdd/core/submission/access.feature` | Akses tingkat submission | `SM-ACCESS-01..06` |
| `docs/requirement-analysis/bdd/core/submission/members.feature` | Anggota penelitian & COI | `SM-MEMBER-01..03` |
| `docs/requirement-analysis/bdd/core/submission/ownership.feature` | Transfer kepemilikan | `SM-OWNER-01..04` |
| `docs/requirement-analysis/bdd/core/submission/withdrawal.feature` | Penarikan | `SM-WITHDRAW-01..05` |
| `docs/requirement-analysis/bdd/core/submission/archival.feature` | Arsip & computed `isArchived()` | `SM-ARCHIVE-01..06` |
| `docs/requirement-analysis/bdd/core/submission/target_output.feature` | Target luaran | `SM-TARGET-01..09` |
| `docs/requirement-analysis/bdd/supporting/budget.feature` | Anggaran | `BUD-LIMIT/LOCK/ITEM/AUDIT/WHO` |

### Fase B — kode irisan vertikal

| Berkas | Tanggung jawab |
| ------ | -------------- |
| `app/Policies/BudgetLineItemPolicy.php` | **Create.** Sumbu *siapa* (BR-BUD-09) dan *keadaan* (BR-BUD-02/03) untuk line item |
| `app/Actions/Budget/SaveBudgetLineItem.php` | **Create.** Satu use case: menyimpan satu line item, menegakkan BR-BUD-01/04/05 |
| `app/Providers/AppServiceProvider.php` | **Modify.** Daftarkan `BudgetLineItemPolicy` |
| `tests/Feature/Budget/BudgetAuthorizationTest.php` | **Create.** `[BUD-WHO-01..03]`, `[BUD-LOCK-01..02]` |
| `tests/Feature/Budget/BudgetLimitTest.php` | **Create.** `[BUD-LIMIT-01..03]` |
| `tests/Feature/Budget/BudgetLineItemValidationTest.php` | **Create.** `[BUD-ITEM-01..04]` |
| `tests/Feature/BudgetAuditTrailTest.php` | **Modify.** Tambahkan ID `[BUD-AUDIT-01..02]` ke deskripsi test yang sudah ada |

Ledger yang disentuh **setiap** task: `docs/requirement-analysis/bdd/pending.txt` dan
`docs/requirement-analysis/bdd/uncovered.txt`.

### Aritmetika ledger — WAJIB berurutan

Angka ini kumulatif. Task yang ditukar urutannya membuat assertion 8 gagal dengan pesan
yang menyesatkan. Kondisi awal: `pending` 48/48, `uncovered` 83/83.

| Task | Skenario | `pending` | BR yang keluar dari `uncovered` | `uncovered` |
| ---- | -------- | --------- | ------------------------------- | ----------- |
| 1 | 11 | 48 → **59** | BR-SM-17 | 83 → **82** |
| 2 | 17 | 59 → **76** | BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 | 82 → **78** |
| 3 | 11 | 76 → **87** | BR-SM-03, BR-SM-05, BR-SM-07 | 78 → **75** |
| 4 | 12 | 87 → **99** | BR-SM-08, BR-SM-09, BR-SM-11, BR-IAM-10 | 75 → **71** |
| 5 | 15 | 99 → **114** | BR-SM-10, BR-SM-13, BR-SM-14, BR-SM-15 | 71 → **67** |
| 6 | 14 | 114 → **128** | BR-BUD-01, 02, 03, 04, 05, 08, 09, BR-SM-06 | 67 → **59** |
| 7 | — | 128 → **125** | — | 59 |
| 8 | — | 125 → **118** | — | 59 |
| 9 | — | 118 → **116** | — | 59 |
| 10 | — | 116 → **114** | — | 59 |

Fase A menaikkan `pending` (spec mendahului kode — ini normal). Fase B menurunkannya.
`BR-IAM-10` ikut keluar di Task 4 karena ia **cermin** BR-SM-09; skenario yang sama
membawa dua tag. `BR-SM-06` idem terhadap BR-BUD-01 di Task 6.

---

## Task 1: `wizard.feature` — navigasi bebas, gerbang prasyarat

**Files:**
- Create: `docs/requirement-analysis/bdd/core/submission/wizard.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `SM-WIZARD-01` … `SM-WIZARD-11`

Poin yang mudah terlewat: BR-SM-17 baru diputuskan (register #16). Yang ditolak **bukan**
"melompat langkah", melainkan **submit dengan prasyarat belum terpenuhi**. Halaman
langkah 7 boleh dibuka kapan saja.

- [ ] **Step 1: Tulis berkas feature**

```gherkin
Feature: Wizard Pengajuan Tujuh Langkah
  Baris form_submissions lahir di langkah 1, bukan di akhir — jangkar temporal
  BR-FE-11 harus ada saat researcher mulai mengisi. Navigasi antar langkah bebas;
  yang menggerbang adalah prasyarat data, bukan nomor langkah (BR-SM-17).

  Background:
    Given Form "Pengajuan Penelitian" punya field bertipe scheme_selector
    And periode berjalan aktif dan belum force closed

  @BR-FE-11 @M1 @RESEARCHER-02-02 @happy
  Scenario: [SM-WIZARD-01] Langkah 1 membuat draft beserta jangkar temporalnya
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1
    And created_at submission menjadi jangkar Temporal Field Binding

  @BR-SM-17 @M1 @RESEARCHER-02-06 @happy
  Scenario: [SM-WIZARD-02] Isian langkah sebelumnya bertahan saat berpindah
    Given "Budi" sudah mengisi Informasi Dasar di langkah 2
    When "Budi" berpindah ke langkah 3 lalu kembali ke langkah 2
    Then isian Informasi Dasar masih utuh

  @BR-FE-11 @M1 @RESEARCHER-02-06 @boundary
  Scenario: [SM-WIZARD-03] Validasi langkah bersifat parsial
    Given "Budi" berada di langkah 2
    When "Budi" menyimpan langkah 2 dengan Rencana Anggaran masih kosong
    Then penyimpanan berhasil
    And tidak ada error tentang Rencana Anggaran

  @BR-SM-17 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-WIZARD-04] Anggaran terkunci selama skema belum dipilih
    Given "Budi" belum memilih skema
    When "Budi" membuka langkah Rencana Anggaran
    Then akses ditolak karena skema belum dipilih

  @BR-SCH-09 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-WIZARD-05] Langkah 3 menulis ke kolom, bukan ke jawaban form
    When "Budi" memilih skema "DIPA Internal" dan TRL 4 di langkah 3
    Then scheme_id submission terisi skema "DIPA Internal"
    And trl_id submission terisi TRL 4

  @BR-FE-11 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-WIZARD-06] Validasi penuh baru berjalan di langkah 7
    Given "Budi" belum mengisi field wajib "Mitra"
    When "Budi" membuka langkah Review & Submit
    Then halaman terbuka
    And "Budi" melihat error per field pada "Mitra"

  @BR-SM-17 @M1 @RESEARCHER-02-01 @happy
  Scenario: [SM-WIZARD-07] Draft dilanjutkan dari langkah terakhir
    Given "Budi" menutup wizard pada langkah 4
    When "Budi" membuka kembali draft itu
    Then wizard terbuka pada langkah 4

  @BR-SM-17 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-WIZARD-08] Submit ditolak selama prasyarat belum terpenuhi
    Given "Budi" belum mengunggah proposal PDF
    When "Budi" menekan Submit di langkah Review & Submit
    Then submit ditolak karena prasyarat belum terpenuhi
    And halaman Review & Submit tetap terbuka

  @BR-SCH-04 @M1 @RESEARCHER-02-07 @boundary
  Scenario: [SM-WIZARD-09] Form tanpa scheme_selector melewati pembatas skema
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    And "Budi" memulai pengajuan baru pada "Pengajuan Sederhana"
    When "Budi" membuka langkah Rencana Anggaran
    Then langkah bisa diisi
    And tidak ada batas anggaran yang berlaku

  @BR-SM-17 @M1 @RESEARCHER-02-06 @happy
  Scenario: [SM-WIZARD-10] Melompat mundur beberapa langkah sekaligus
    Given "Budi" berada di langkah 6
    When "Budi" melompat langsung ke langkah 2
    Then langkah 2 terbuka
    And isian langkah 6 tetap tersimpan

  @BR-SM-17 @M1 @RESEARCHER-02-09 @boundary
  Scenario: [SM-WIZARD-11] Langkah tanpa prasyarat terbuka kapan saja
    Given "Budi" baru menyelesaikan langkah 1
    When "Budi" membuka langkah Upload Berkas
    Then langkah bisa diisi
```

- [ ] **Step 2: Perbarui ledger**

Di `pending.txt`: ubah plafon menjadi `# max: 59`, tambahkan `SM-WIZARD-01` … `SM-WIZARD-11`
dalam urutan alfabetis.

Di `uncovered.txt`: hapus baris `BR-SM-17`, ubah plafon menjadi `# max: 82`.

- [ ] **Step 3: Jalankan guard**

Run PEST dengan `tests/Unit/Bdd/BddCoverageTest.php`.
Expected: PASS, 9 test.

Kalau assertion 3 gagal menyebut `BR-SM-17`, berarti baris BR-nya belum ada di DDD —
hentikan dan laporkan, jangan menghapus tag.

- [ ] **Step 4: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add submission wizard scenarios (BR-SM-17)"
```

---

## Task 2: `submit.feature` — keunikan, proposal, siapa yang boleh

**Files:**
- Create: `docs/requirement-analysis/bdd/core/submission/submit.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Consumes: konvensi tag dan kosakata langkah dari Task 1
- Produces: ID `SM-SUBMIT-01` … `SM-SUBMIT-17`

Tiga hal yang mudah salah:
1. "Active" pada BR-SM-01 berarti **sudah disubmit** — DRAFT tidak dihitung.
2. `SM-SUBMIT-13` mengunci ADR 0015: Super Admin **tidak** bisa submit milik orang lain.
3. `SM-SUBMIT-15..17` mengunci BR-SM-16: draft yang gagal disubmit **tetap DRAFT**.

- [ ] **Step 1: Tulis berkas feature**

Susun 17 skenario memakai tabel di [spec §3](../design/bdd-coverage/m1-submission-budget.md)
— kolom ID, Kelas, dan "Yang dibuktikan" adalah isi yang mengikat. Bentuknya:

```gherkin
Feature: Submit Pengajuan
  Satu researcher hanya boleh punya satu pengajuan aktif per periode per skema, dan
  hanya submitted_by yang boleh menekan submit. Draft yang gagal disubmit tidak
  pernah hilang.

  Background:
    Given periode berjalan aktif dan belum force closed
    And skema "DIPA Internal" dan skema "DIPA Eksternal" tersedia

  @BR-SM-01 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-SUBMIT-01] Submit pertama ke sebuah skema berhasil
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-01 @M1 @RESEARCHER-02-10 @denial
  Scenario: [SM-SUBMIT-02] Submit kedua ke skema yang sama ditolak
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then submit ditolak karena sudah ada pengajuan aktif pada skema itu

  @BR-SM-16 @M1 @RESEARCHER-02-10 @boundary
  Scenario: [SM-SUBMIT-16] Draft yang gagal disubmit tetap bisa disunting
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus SUBMITTED
    And submission "P-002" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    When "Budi" submit "P-002"
    Then submit ditolak karena sudah ada pengajuan aktif pada skema itu
    And status "P-002" tetap DRAFT
    And "Budi" masih bisa menyunting "P-002"
```

Sisa skenario mengikuti pola yang sama. Daftar lengkap dengan kelas tag:

| ID | Kelas | BR | Inti assertion |
| -- | ----- | -- | -------------- |
| `SM-SUBMIT-01` | happy | BR-SM-01 | Submit pertama ke skema A berhasil |
| `SM-SUBMIT-02` | denial | BR-SM-01 | Submit kedua ke skema A ditolak |
| `SM-SUBMIT-03` | boundary | BR-SM-01 | Dua DRAFT ke skema berbeda boleh hidup bersamaan |
| `SM-SUBMIT-04` | happy | BR-SM-01 | Submit ke skema B tetap boleh meski A sudah disubmit |
| `SM-SUBMIT-05` | boundary | BR-SM-01 | Setelah REJECTED, submit ulang ke skema sama boleh |
| `SM-SUBMIT-06` | boundary | BR-SM-01 | Submission tanpa skema tidak saling menghalangi |
| `SM-SUBMIT-07` | denial | BR-SM-02 | Submit tanpa proposal PDF ditolak, pesan per field |
| `SM-SUBMIT-08` | happy | BR-SM-02 | Submit dengan proposal PDF lolos ke SUBMITTED |
| `SM-SUBMIT-09` | happy | BR-SM-04 | `submitted_by` submit berhasil |
| `SM-SUBMIT-10` | denial | BR-SM-04 | Co-Investigator submit ditolak walau bisa menyunting |
| `SM-SUBMIT-11` | denial | BR-SM-04 | Member submit ditolak |
| `SM-SUBMIT-12` | happy | BR-SM-04 | `submitted_by` resubmit dari NEEDS_REVISION |
| `SM-SUBMIT-13` | denial | BR-SM-04 | Super Admin bukan pemilik **tidak** bisa submit |
| `SM-SUBMIT-14` | happy | BR-SM-04 | Super Admin tetap bisa view, update, withdraw |
| `SM-SUBMIT-15` | denial | BR-SM-16 | Penolakan menyebut pengajuan aktif yang sudah ada + tautannya |
| `SM-SUBMIT-16` | boundary | BR-SM-16 | Draft tetap DRAFT dan tetap bisa disunting |
| `SM-SUBMIT-17` | boundary | BR-SM-16 | Kalau index DB yang menangkap, pesannya sama seperti -15 |

- [ ] **Step 2: Perbarui ledger**

`pending.txt`: plafon `# max: 76`, tambahkan `SM-SUBMIT-01` … `SM-SUBMIT-17`.
`uncovered.txt`: hapus `BR-SM-01`, `BR-SM-02`, `BR-SM-04`, `BR-SM-16`; plafon `# max: 78`.

- [ ] **Step 3: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

- [ ] **Step 4: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add submit scenarios (BR-SM-01, 02, 04, 16)"
```

---

## Task 3: `state.feature` + `access.feature`

**Files:**
- Create: `docs/requirement-analysis/bdd/core/submission/state.feature`
- Create: `docs/requirement-analysis/bdd/core/submission/access.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `SM-STATE-01..05`, `SM-ACCESS-01..06`

Digabung dalam satu task karena keduanya kecil dan sama-sama menjaga "siapa boleh apa
pada keadaan apa" — reviewer menilai keduanya sekali duduk.

- [ ] **Step 1: Tulis `state.feature`**

```gherkin
Feature: State Machine Pengajuan
  Status hanya berpindah lewat transitionTo(); tidak ada lompatan. Status terminal
  hanya bisa disentuh Operator lewat override.

  @BR-SM-03 @M1 @RESEARCHER-02-10 @happy
  Scenario: [SM-STATE-01] Draft berpindah ke submitted lewat state machine
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When "Budi" submit "P-001"
    Then status "P-001" menjadi SUBMITTED

  @BR-SM-03 @M1 @denial
  Scenario Outline: [SM-STATE-02] Lompatan status terlarang ditolak
    Given submission "P-001" berstatus <dari>
    When sistem mencoba memindahkan "P-001" ke <ke>
    Then perpindahan ditolak

    Examples:
      | dari      | ke             |
      | DRAFT     | APPROVED       |
      | DRAFT     | RESUBMITTED    |
      | APPROVED  | DRAFT          |
      | REJECTED  | APPROVED       |
      | REJECTED  | RESUBMITTED    |
      | WITHDRAWN | APPROVED       |

  @BR-SM-03 @M1 @RESEARCHER-02-05 @happy
  Scenario: [SM-STATE-03] Revisi kembali masuk antrean review
    Given submission "P-001" milik "Budi" berstatus NEEDS_REVISION
    When "Budi" resubmit "P-001"
    Then status "P-001" menjadi RESUBMITTED
    And status "P-001" menjadi UNDER_REVIEW

  @BR-SM-05 @M1 @denial
  Scenario Outline: [SM-STATE-04] Isi pengajuan terkunci pada status terminal
    Given submission "P-001" milik "Budi" berstatus <status>
    When "Budi" menyunting isi "P-001"
    Then penyuntingan ditolak dengan 403

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  @BR-SM-05 @M1 @OPERATOR-02-02 @happy
  Scenario: [SM-STATE-05] Operator override tetap bisa menyentuh status terminal
    Given submission "P-001" berstatus APPROVED
    When Operator mengubah "P-001" lewat override
    Then perubahan tersimpan
    And perubahan tercatat di jejak audit
```

- [ ] **Step 2: Tulis `access.feature`**

```gherkin
Feature: Akses Tingkat Submission
  Lapis kedua di luar FormAccessControl. Form-nya boleh terbuka untuk seluruh
  fakultas, tapi isi submission hanya untuk pemilik dan anggotanya (BR-SM-07).

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW

  @BR-SM-07 @M1 @RESEARCHER-02-03 @happy
  Scenario: [SM-ACCESS-01] Pemilik melihat pengajuannya
    When "Budi" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @RESEARCHER-02-03 @happy
  Scenario: [SM-ACCESS-02] Anggota penelitian melihat pengajuan yang ia ikuti
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @RESEARCHER-02-01 @denial
  Scenario: [SM-ACCESS-03] Researcher lain di organisasi yang sama ditolak
    Given "Andi" berorganisasi sama dengan "Budi" dan bukan anggota "P-001"
    When "Andi" membuka detail "P-001"
    Then akses ditolak dengan 403

  @BR-SM-07 @M1 @OPERATOR-02-02 @happy
  Scenario: [SM-ACCESS-04] Operator melihat semua lewat submissions.view-all
    Given "Siti" punya permission "submissions.view-all"
    When "Siti" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @REVIEWER-03-01 @happy
  Scenario: [SM-ACCESS-05] Reviewer yang ditugaskan melihat submission itu
    Given "Rina" ditugaskan sebagai reviewer "P-001"
    When "Rina" membuka detail "P-001"
    Then detail terbuka

  @BR-SM-07 @M1 @REVIEWER-03-01 @denial
  Scenario: [SM-ACCESS-06] Reviewer yang tidak ditugaskan ditolak
    Given "Rina" tidak ditugaskan ke "P-001"
    When "Rina" membuka detail "P-001"
    Then akses ditolak dengan 403
```

- [ ] **Step 3: Perbarui ledger**

`pending.txt`: plafon `# max: 87`, tambahkan `SM-STATE-01..05` dan `SM-ACCESS-01..06`.
`uncovered.txt`: hapus `BR-SM-03`, `BR-SM-05`, `BR-SM-07`; plafon `# max: 75`.

- [ ] **Step 4: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

- [ ] **Step 5: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add state machine and submission access scenarios"
```

---

## Task 4: `members` + `ownership` + `withdrawal`

**Files:**
- Create: `docs/requirement-analysis/bdd/core/submission/members.feature`
- Create: `docs/requirement-analysis/bdd/core/submission/ownership.feature`
- Create: `docs/requirement-analysis/bdd/core/submission/withdrawal.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `SM-MEMBER-01..03`, `SM-OWNER-01..04`, `SM-WITHDRAW-01..05`

Tiga berkas kecil digabung: ketiganya penjagaan siklus hidup pengajuan oleh Operator.

**Penting:** skenario `SM-OWNER-*` membawa **dua tag** `@BR-SM-09 @BR-IAM-10` — satu
aturan, dua bounded context. Implementasinya nanti wajib satu Action.

- [ ] **Step 1: Tulis `members.feature`**

```gherkin
Feature: Anggota Penelitian
  Satu orang tidak boleh menilai penelitian yang ia ikuti. Larangan ini berlaku dua
  arah; sisi reviewer dijaga [REV-ASSIGN-07] di M2.

  Background:
    Given submission "P-001" milik "Budi" berstatus DRAFT
    And skema "DIPA Internal" membatasi max_members 4

  @BR-SM-08 @M1 @RESEARCHER-02-08 @denial
  Scenario: [SM-MEMBER-01] Reviewer submission ini tidak bisa jadi anggotanya
    Given "Rina" ditugaskan sebagai reviewer "P-001"
    When "Budi" menambahkan "Rina" sebagai anggota "P-001"
    Then penambahan ditolak karena konflik kepentingan

  @BR-SM-08 @M1 @RESEARCHER-02-08 @happy
  Scenario: [SM-MEMBER-02] Anggota biasa bisa ditambahkan
    When "Budi" menambahkan "Sari" sebagai co_investigator "P-001"
    Then "Sari" terdaftar sebagai co_investigator di "P-001"

  @BR-SM-08 @M1 @RESEARCHER-02-08 @boundary
  Scenario: [SM-MEMBER-03] Jumlah anggota dibatasi skema
    Given "P-001" sudah punya 4 anggota
    When "Budi" menambahkan satu anggota lagi ke "P-001"
    Then penambahan ditolak karena melebihi batas skema
```

- [ ] **Step 2: Tulis `ownership.feature`**

```gherkin
Feature: Transfer Kepemilikan Pengajuan
  Lead researcher yang dinonaktifkan tidak boleh meninggalkan pengajuan tanpa
  penanggung jawab. Aturannya kanonik di BR-IAM-10, dicerminkan BR-SM-09 — satu
  Action, dipanggil dari layar deaktivasi maupun layar transfer.

  Background:
    Given submission "P-001" milik "Budi" berstatus UNDER_REVIEW

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-02-04 @happy
  Scenario: [SM-OWNER-01] Operator memindahkan kepemilikan ke co-investigator aktif
    Given "Sari" terdaftar sebagai co_investigator aktif di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Sari"
    Then submitted_by "P-001" menjadi "Sari"
    And "Budi" tetap terdaftar sebagai anggota "P-001"

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-02-04 @denial
  Scenario: [SM-OWNER-02] Transfer ke member biasa ditolak
    Given "Andi" terdaftar sebagai member di "P-001"
    When Operator memindahkan kepemilikan "P-001" ke "Andi"
    Then pemindahan ditolak karena penerima bukan co_investigator

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-08-03 @denial
  Scenario: [SM-OWNER-03] Deaktivasi lead ditolak sebelum kepemilikan dipindahkan
    When Operator menonaktifkan "Budi"
    Then deaktivasi ditolak karena masih ada pengajuan aktif

  @BR-SM-09 @BR-IAM-10 @M1 @OPERATOR-08-03 @boundary
  Scenario: [SM-OWNER-04] Tidak ada co-investigator aktif, deaktivasi tetap diblokir
    Given "P-001" tidak punya co_investigator aktif
    When Operator menonaktifkan "Budi"
    Then deaktivasi ditolak karena masih ada pengajuan aktif
    And Operator melihat alasan bahwa tidak ada co_investigator aktif
```

- [ ] **Step 3: Tulis `withdrawal.feature`**

```gherkin
Feature: Penarikan Pengajuan
  Penarikan hanya lewat Operator, dan tidak dapat dibatalkan — state machine
  sengaja dijaga asiklik (BR-SM-11).

  Background:
    Given submission "P-001" milik "Budi" berstatus APPROVED

  @BR-SM-11 @M1 @OPERATOR-02-06 @happy
  Scenario: [SM-WITHDRAW-01] Operator menarik pengajuan yang sudah disetujui
    When Operator menarik "P-001"
    Then status "P-001" menjadi WITHDRAWN

  @BR-SM-11 @M1 @RESEARCHER-02-03 @denial
  Scenario: [SM-WITHDRAW-02] Researcher tidak bisa menarik sendiri
    When "Budi" menarik "P-001"
    Then penarikan ditolak karena bukan self-service

  @BR-SM-11 @M1 @OPERATOR-02-06 @boundary
  Scenario: [SM-WITHDRAW-03] Menarik pengajuan yang belum disetujui ditolak
    Given submission "P-002" milik "Budi" berstatus UNDER_REVIEW
    When Operator menarik "P-002"
    Then penarikan ditolak karena pengajuan belum disetujui

  @BR-SM-11 @M1 @OPERATOR-02-02 @boundary
  Scenario: [SM-WITHDRAW-04] Data historis tetap utuh setelah penarikan
    When Operator menarik "P-001"
    Then seluruh anggaran, anggota, dan berkas "P-001" tetap terbaca

  @BR-SM-11 @M1 @OPERATOR-02-06 @denial
  Scenario: [SM-WITHDRAW-05] Penarikan tidak dapat dibatalkan
    Given submission "P-001" berstatus WITHDRAWN
    When Operator mencoba mengembalikan "P-001" ke APPROVED
    Then perpindahan ditolak
```

- [ ] **Step 4: Perbarui ledger**

`pending.txt`: plafon `# max: 99`, tambahkan `SM-MEMBER-01..03`, `SM-OWNER-01..04`,
`SM-WITHDRAW-01..05`.
`uncovered.txt`: hapus `BR-SM-08`, `BR-SM-09`, `BR-SM-11`, `BR-IAM-10`; plafon `# max: 71`.

- [ ] **Step 5: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

Assertion 9 akan gagal kalau `BR-IAM-10` lupa dihapus dari `uncovered.txt` — ia sudah
punya skenario lewat tag kembar di `SM-OWNER-*`.

- [ ] **Step 6: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add member, ownership and withdrawal scenarios"
```

---

## Task 5: `archival` + `target_output`

**Files:**
- Create: `docs/requirement-analysis/bdd/core/submission/archival.feature`
- Create: `docs/requirement-analysis/bdd/core/submission/target_output.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `SM-ARCHIVE-01..06`, `SM-TARGET-01..09`

- [ ] **Step 1: Tulis `archival.feature`**

```gherkin
Feature: Arsip Pengajuan
  isArchived() adalah computed property, bukan kolom: is_submitted = false DAN
  period sudah tutup. Draft tidak pernah dihapus saat period tutup.

  @BR-SM-10 @M1 @RESEARCHER-02-01 @happy
  Scenario: [SM-ARCHIVE-01] Draft berubah read-only saat period tutup
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When periode berjalan ditutup
    Then "P-001" tetap ada
    And "P-001" ditandai diarsipkan

  @BR-SM-10 @M1 @SHARED-02-01 @happy
  Scenario: [SM-ARCHIVE-02] Pemilik draft diberi tahu saat period tutup
    Given submission "P-001" milik "Budi" berstatus DRAFT
    When periode berjalan ditutup
    Then "Budi" menerima notifikasi periode ditutup

  @BR-SM-10 @M1 @RESEARCHER-02-04 @denial
  Scenario: [SM-ARCHIVE-03] Draft yang diarsipkan tidak bisa disunting
    Given submission "P-001" milik "Budi" ditandai diarsipkan
    When "Budi" menyunting isi "P-001"
    Then penyuntingan ditolak dengan 403

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-04] Belum disubmit dan period tutup berarti diarsipkan
    Given submission "P-001" belum disubmit
    And periode berjalan sudah tutup
    Then "P-001" ditandai diarsipkan

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-05] Sudah disubmit tidak ikut diarsipkan
    Given submission "P-001" sudah disubmit
    And periode berjalan sudah tutup
    Then "P-001" tidak ditandai diarsipkan

  @BR-SM-13 @M1 @boundary
  Scenario: [SM-ARCHIVE-06] Force closed memicu arsip meski tanggal belum lewat
    Given submission "P-001" belum disubmit
    And periode berjalan berstatus is_force_closed
    Then "P-001" ditandai diarsipkan
```

- [ ] **Step 2: Tulis `target_output.feature`**

```gherkin
Feature: Target Luaran Pengajuan
  Target luaran adalah luaran yang dijanjikan — bedakan dari ResearchOutput yang
  terealisasi. Ia boleh berubah setelah disetujui, tapi hanya oleh ketua pengaju,
  dan setiap perubahan pasca-APPROVED tercatat.

  Background:
    Given submission "P-001" milik "Budi" berskema "DIPA Internal"
    And skema "DIPA Internal" mengizinkan output type "Publikasi Ilmiah" dan "HAKI"

  @BR-SM-14 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-TARGET-01] Memilih tipe luaran yang terdaftar
    When "Budi" menambahkan target luaran bertipe "Publikasi Ilmiah" ke "P-001"
    Then target luaran tersimpan di "P-001"

  @BR-SM-14 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-TARGET-02] Tipe di luar daftar definisi ditolak
    When "Budi" menambahkan target luaran bertipe "Podcast" ke "P-001"
    Then penambahan ditolak karena tipe tidak terdaftar

  @BR-SM-14 @M1 @RESEARCHER-02-07 @denial
  Scenario: [SM-TARGET-03] Tipe di luar izin skema ditolak
    When "Budi" menambahkan target luaran bertipe "Buku" ke "P-001"
    Then penambahan ditolak karena tipe tidak diizinkan skema

  @BR-SM-14 @M1 @RESEARCHER-02-07 @boundary
  Scenario: [SM-TARGET-04] Submission tanpa skema hanya dibatasi daftar definisi
    Given submission "P-002" milik "Budi" tanpa skema
    When "Budi" menambahkan target luaran bertipe "Buku" ke "P-002"
    Then target luaran tersimpan di "P-002"

  @BR-SM-15 @M1 @RESEARCHER-02-07 @happy
  Scenario: [SM-TARGET-05] Target bisa diubah saat draft
    Given "P-001" berstatus DRAFT
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tersimpan

  @BR-SM-15 @M1 @RESEARCHER-02-11 @happy
  Scenario: [SM-TARGET-06] Target bisa diubah setelah disetujui
    Given "P-001" berstatus APPROVED
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tersimpan

  @BR-SM-15 @M1 @denial
  Scenario Outline: [SM-TARGET-07] Target terkunci pada status mati
    Given "P-001" berstatus <status>
    When "Budi" mengubah target luaran "P-001"
    Then perubahan ditolak dengan 403

    Examples:
      | status    |
      | REJECTED  |
      | WITHDRAWN |

  @BR-SM-15 @M1 @RESEARCHER-02-11 @denial
  Scenario: [SM-TARGET-08] Co-investigator tidak bisa mengubah target
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" mengubah target luaran "P-001"
    Then perubahan ditolak dengan 403

  @BR-SM-15 @M1 @RESEARCHER-02-11 @happy
  Scenario: [SM-TARGET-09] Perubahan pasca-approved tercatat lengkap
    Given "P-001" berstatus APPROVED
    When "Budi" mengubah target luaran "P-001"
    Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru
```

- [ ] **Step 3: Perbarui ledger**

`pending.txt`: plafon `# max: 114`, tambahkan `SM-ARCHIVE-01..06` dan `SM-TARGET-01..09`.
`uncovered.txt`: hapus `BR-SM-10`, `BR-SM-13`, `BR-SM-14`, `BR-SM-15`; plafon `# max: 67`.

- [ ] **Step 4: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

- [ ] **Step 5: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add archival and target output scenarios"
```

---

## Task 6: `budget.feature` — 14 skenario yang akan diimplementasikan

**Files:**
- Create: `docs/requirement-analysis/bdd/supporting/budget.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `BUD-LIMIT-01..03`, `BUD-LOCK-01..02`, `BUD-ITEM-01..04`,
  `BUD-AUDIT-01..02`, `BUD-WHO-01..03` — dikonsumsi Task 7–10

`BUD-LIMIT-*` membawa **dua tag** `@BR-BUD-01 @BR-SM-06`: satu validator, dipanggil dari
langkah 5 wizard maupun dari submit.

- [ ] **Step 1: Tulis berkas feature**

```gherkin
Feature: Anggaran Pengajuan
  Grand total dihitung dari penjumlahan line item dan tidak boleh melebihi batas
  skema. Anggaran terkunci saat pengajuan mencapai status terminal, tapi tetap
  terbuka saat NEEDS_REVISION karena revisi memang menuntutnya.

  Background:
    Given submission "P-001" milik "Budi" berskema "DIPA Internal" berstatus DRAFT
    And skema "DIPA Internal" membatasi max_budget 50000000

  @BR-BUD-01 @BR-SM-06 @M1 @RESEARCHER-02-04 @denial
  Scenario: [BUD-LIMIT-01] Total melebihi batas skema ditolak
    Given "P-001" punya line item bertotal 48000000
    When "Budi" menambahkan line item bertotal 5000000 ke "P-001"
    Then penyimpanan ditolak karena melebihi batas anggaran skema

  @BR-BUD-01 @BR-SM-06 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-LIMIT-02] Total tepat di batas diterima
    Given "P-001" punya line item bertotal 45000000
    When "Budi" menambahkan line item bertotal 5000000 ke "P-001"
    Then penyimpanan berhasil
    And grand total "P-001" menjadi 50000000

  @BR-BUD-01 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-LIMIT-03] Submission tanpa skema tidak punya batas
    Given submission "P-002" milik "Budi" tanpa skema berstatus DRAFT
    When "Budi" menambahkan line item bertotal 90000000 ke "P-002"
    Then penyimpanan berhasil

  @BR-BUD-02 @M1 @RESEARCHER-02-03 @denial
  Scenario Outline: [BUD-LOCK-01] Anggaran terkunci pada status terminal
    Given submission "P-001" berstatus <status>
    When "Budi" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403

    Examples:
      | status    |
      | APPROVED  |
      | REJECTED  |
      | WITHDRAWN |

  @BR-BUD-03 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-LOCK-02] Anggaran tetap terbuka saat perlu revisi
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-04 @M1 @RESEARCHER-02-04 @denial
  Scenario Outline: [BUD-ITEM-01] Volume dan harga satuan harus lebih dari nol
    When "Budi" menambahkan line item dengan volume <volume> dan harga <harga> ke "P-001"
    Then penyimpanan ditolak dengan error validasi

    Examples:
      | volume | harga   |
      | 0      | 1000000 |
      | -1     | 1000000 |
      | 2      | 0       |
      | 2      | -5000   |

  @BR-BUD-04 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-ITEM-02] Line item sah tersimpan dan menaikkan grand total
    When "Budi" menambahkan line item dengan volume 4 dan harga 1250000 ke "P-001"
    Then penyimpanan berhasil
    And grand total "P-001" menjadi 5000000

  @BR-BUD-05 @M1 @RESEARCHER-02-04 @denial
  Scenario: [BUD-ITEM-03] Komponen non-aktif tidak bisa dipilih untuk item baru
    Given komponen anggaran "Perjalanan Dinas" berstatus tidak aktif
    When "Budi" menambahkan line item berkomponen "Perjalanan Dinas" ke "P-001"
    Then penyimpanan ditolak karena komponen tidak aktif

  @BR-BUD-05 @M1 @RESEARCHER-02-04 @boundary
  Scenario: [BUD-ITEM-04] Item lama yang memakai komponen itu tetap utuh
    Given "P-001" punya line item berkomponen "Perjalanan Dinas"
    When komponen anggaran "Perjalanan Dinas" dinonaktifkan
    Then line item "P-001" tetap terbaca

  @BR-BUD-08 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-AUDIT-01] Perubahan saat revisi tercatat nilai sebelum dan sesudah
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" mengubah harga satuan line item "P-001" menjadi 2000000
    Then perubahan tercatat di jejak audit dengan nilai lama dan nilai baru

  @BR-BUD-08 @M1 @OPERATOR-07-03 @boundary
  Scenario: [BUD-AUDIT-02] Jejak audit menyimpan pelaku dan waktunya
    Given submission "P-001" berstatus NEEDS_REVISION
    When "Budi" mengubah harga satuan line item "P-001" menjadi 2000000
    Then jejak audit menyebut "Budi" sebagai pelaku
    And jejak audit menyebut waktu perubahan

  @BR-BUD-09 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-WHO-01] Ketua pengaju menyunting anggaran
    When "Budi" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-09 @M1 @RESEARCHER-02-04 @happy
  Scenario: [BUD-WHO-02] Co-investigator ikut menyunting anggaran
    Given "Sari" terdaftar sebagai co_investigator di "P-001"
    When "Sari" menyunting line item "P-001"
    Then penyuntingan berhasil

  @BR-BUD-09 @M1 @RESEARCHER-02-03 @denial
  Scenario: [BUD-WHO-03] Anggota biasa hanya bisa membaca anggaran
    Given "Andi" terdaftar sebagai member di "P-001"
    When "Andi" menyunting line item "P-001"
    Then penyuntingan ditolak dengan 403
```

- [ ] **Step 2: Perbarui ledger**

`pending.txt`: plafon `# max: 128`, tambahkan keempat belas ID di atas.
`uncovered.txt`: hapus `BR-BUD-01`, `BR-BUD-02`, `BR-BUD-03`, `BR-BUD-04`, `BR-BUD-05`,
`BR-BUD-08`, `BR-BUD-09`, `BR-SM-06`; plafon `# max: 59`.

- [ ] **Step 3: Jalankan guard**

Run PEST `tests/Unit/Bdd/BddCoverageTest.php`. Expected: PASS, 9 test.

- [ ] **Step 4: Verifikasi akhir Fase A**

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt    # harus 128
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/uncovered.txt  # harus 59
grep -E '^BR-(SM|BUD)-' docs/requirement-analysis/bdd/uncovered.txt # harus kosong
```

Baris ketiga kosong berarti seluruh BR M1 sudah punya skenario.

- [ ] **Step 5: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add budget scenarios (BR-BUD-01..09)"
```

---

## Task 7: `BudgetLineItemPolicy` — sumbu siapa dan keadaan

**Files:**
- Create: `app/Policies/BudgetLineItemPolicy.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Create: `tests/Feature/Budget/BudgetAuthorizationTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: ID `BUD-WHO-01..03`, `BUD-LOCK-01..02` dari Task 6
- Produces: `BudgetLineItemPolicy::update(User $user, FormSubmission $submission): bool`
  — dipanggil Task 8 lewat `$user->can('update-budget', $submission)`

`app/Policies/` otomatis kena PHPStan level 9.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php

// tests/Feature/Budget/BudgetAuthorizationTest.php

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Draft;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;

function submissionOwnedBy(User $owner, string $state = Draft::class): FormSubmission
{
    return FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
    ]);
}

it('[BUD-WHO-01] ketua pengaju boleh menyunting anggaran', function () {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner);

    expect($owner->can('update-budget', $submission))->toBeTrue();
});

it('[BUD-WHO-02] co-investigator boleh menyunting anggaran', function () {
    $owner = User::factory()->create();
    $co = User::factory()->create();
    $submission = submissionOwnedBy($owner);
    ResearchMember::create([
        'form_submission_id' => $submission->id,
        'user_id' => $co->id,
        'role' => ResearchMember::ROLE_CO_INVESTIGATOR,
    ]);

    expect($co->can('update-budget', $submission->fresh()))->toBeTrue();
});

it('[BUD-WHO-03] member biasa hanya bisa membaca anggaran', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $submission = submissionOwnedBy($owner);
    ResearchMember::create([
        'form_submission_id' => $submission->id,
        'user_id' => $member->id,
        'role' => ResearchMember::ROLE_MEMBER,
    ]);

    expect($member->can('update-budget', $submission->fresh()))->toBeFalse();
});

it('[BUD-LOCK-01] anggaran terkunci pada status terminal', function (string $state) {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, $state);

    expect($owner->can('update-budget', $submission))->toBeFalse();
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[BUD-LOCK-02] anggaran tetap terbuka saat perlu revisi', function () {
    $owner = User::factory()->create();
    $submission = submissionOwnedBy($owner, NeedsRevision::class);

    expect($owner->can('update-budget', $submission))->toBeTrue();
});
```

- [ ] **Step 2: Jalankan test untuk memastikan gagal**

Run PEST `tests/Feature/Budget/BudgetAuthorizationTest.php`.
Expected: FAIL — ability `update-budget` belum terdaftar, semua `can()` mengembalikan false.

- [ ] **Step 3: Tulis Policy**

```php
<?php

namespace App\Policies;

use App\Models\FormSubmission;
use App\Models\ResearchMember;
use App\Models\User;
use App\States\Submission\Approved;
use App\States\Submission\Rejected;
use App\States\Submission\Withdrawn;

class BudgetLineItemPolicy
{
    /**
     * Status yang mengunci anggaran — BR-BUD-02. NEEDS_REVISION sengaja TIDAK di sini:
     * BR-BUD-03 justru mewajibkan anggaran tetap bisa disunting saat revisi.
     *
     * @var list<class-string>
     */
    private const LOCKED_STATES = [Approved::class, Rejected::class, Withdrawn::class];

    public function update(User $user, FormSubmission $submission): bool
    {
        // BR-BUD-02 — sumbu keadaan diperiksa lebih dulu; status terminal mengunci
        // anggaran bagi siapa pun, termasuk ketua pengaju.
        if (in_array($submission->status::class, self::LOCKED_STATES, true)) {
            return false;
        }

        // BR-BUD-09 — menyusun anggaran pekerjaan bersama; mengikat diri lewat submit
        // tetap hanya hak submitted_by (BR-SM-04).
        if ($submission->submitted_by === $user->id) {
            return true;
        }

        return $submission->researchMembers()
            ->where('user_id', $user->id)
            ->where('role', ResearchMember::ROLE_CO_INVESTIGATOR)
            ->exists();
    }
}
```

- [ ] **Step 4: Daftarkan ability**

Di `app/Providers/AppServiceProvider.php`, di dalam `boot()`, tepat setelah
`Gate::policy(FormSubmission::class, FormSubmissionPolicy::class);`:

```php
Gate::define('update-budget', [BudgetLineItemPolicy::class, 'update']);
```

Tambahkan `use App\Policies\BudgetLineItemPolicy;` di blok import.

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Budget/BudgetAuthorizationTest.php`.
Expected: PASS, 7 test (`BUD-LOCK-01` menghasilkan 3 dataset).

- [ ] **Step 6: Keluarkan ID dari pending**

`pending.txt`: hapus `BUD-WHO-01`, `BUD-WHO-02`, `BUD-WHO-03`, `BUD-LOCK-01`,
`BUD-LOCK-02`; plafon `# max: 125`.

- [ ] **Step 7: Gate penuh lalu commit**

Run QUALITY. Expected: Pint PASS, PHPStan L5 dan L9 `[OK] No errors`, seluruh Pest hijau.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "feat(budget): add budget line item policy for BR-BUD-02, 03, 09"
```

---

## Task 8: `SaveBudgetLineItem` — batas skema dan validasi item

**Files:**
- Create: `app/Actions/Budget/SaveBudgetLineItem.php`
- Create: `tests/Feature/Budget/BudgetLimitTest.php`
- Create: `tests/Feature/Budget/BudgetLineItemValidationTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `BudgetLineItemPolicy` (Task 7) lewat ability `update-budget`
- Produces: `SaveBudgetLineItem::run(FormSubmission $submission, User $user, array $attributes): BudgetLineItem`
  dengan `$attributes` berisi kunci `budget_component_id`, `item_name`, `volume`, `unit`,
  `unit_price`

`app/Actions/` otomatis kena PHPStan level 9.

- [ ] **Step 1: Tulis test batas anggaran yang gagal**

```php
<?php

// tests/Feature/Budget/BudgetLimitTest.php

use App\Actions\Budget\SaveBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\Scheme;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Validation\ValidationException;

function budgetFixture(int $maxBudget): array
{
    $owner = User::factory()->create();
    $scheme = Scheme::factory()->create(['max_budget' => $maxBudget]);
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => $scheme->id,
    ]);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    return [$owner, $submission, $component];
}

function lineItemAttributes(BudgetComponent $component, int $volume, int $unitPrice): array
{
    return [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => $volume,
        'unit' => 'unit',
        'unit_price' => $unitPrice,
    ];
}

it('[BUD-LIMIT-01] menolak total yang melebihi batas skema', function () {
    [$owner, $submission, $component] = budgetFixture(50_000_000);
    BudgetLineItem::factory()->create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'volume' => 1,
        'unit_price' => 48_000_000,
        'total' => 48_000_000,
    ]);

    expect(fn () => SaveBudgetLineItem::run(
        $submission->fresh(), $owner, lineItemAttributes($component, 1, 5_000_000)
    ))->toThrow(ValidationException::class);
});

it('[BUD-LIMIT-02] menerima total yang tepat di batas', function () {
    [$owner, $submission, $component] = budgetFixture(50_000_000);
    BudgetLineItem::factory()->create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'volume' => 1,
        'unit_price' => 45_000_000,
        'total' => 45_000_000,
    ]);

    SaveBudgetLineItem::run(
        $submission->fresh(), $owner, lineItemAttributes($component, 1, 5_000_000)
    );

    expect($submission->fresh()->budgetLineItems()->sum('total'))->toBe(50_000_000);
});

it('[BUD-LIMIT-03] tidak membatasi submission tanpa skema', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    SaveBudgetLineItem::run($submission, $owner, lineItemAttributes($component, 1, 90_000_000));

    expect($submission->fresh()->budgetLineItems()->sum('total'))->toBe(90_000_000);
});
```

- [ ] **Step 2: Tulis test validasi item yang gagal**

```php
<?php

// tests/Feature/Budget/BudgetLineItemValidationTest.php

use App\Actions\Budget\SaveBudgetLineItem;
use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use App\States\Submission\Draft;
use Illuminate\Validation\ValidationException;

function plainSubmission(): array
{
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => Draft::class,
        'scheme_id' => null,
    ]);

    return [$owner, $submission];
}

it('[BUD-ITEM-01] menolak volume atau harga satuan yang tidak positif', function (int $volume, int $unitPrice) {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    expect(fn () => SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Reagen analisis',
        'volume' => $volume,
        'unit' => 'paket',
        'unit_price' => $unitPrice,
    ]))->toThrow(ValidationException::class);
})->with([[0, 1_000_000], [-1, 1_000_000], [2, 0], [2, -5_000]]);

it('[BUD-ITEM-02] menyimpan line item sah dan menghitung totalnya', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);

    $item = SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 4,
        'unit' => 'unit',
        'unit_price' => 1_250_000,
    ]);

    expect($item->total)->toBe(5_000_000)
        ->and($submission->fresh()->budgetLineItems()->sum('total'))->toBe(5_000_000);
});

it('[BUD-ITEM-03] menolak komponen anggaran yang tidak aktif', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => false]);

    expect(fn () => SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Perjalanan dinas',
        'volume' => 1,
        'unit' => 'paket',
        'unit_price' => 1_000_000,
    ]))->toThrow(ValidationException::class);
});

it('[BUD-ITEM-04] membiarkan item lama utuh saat komponennya dinonaktifkan', function () {
    [$owner, $submission] = plainSubmission();
    $component = BudgetComponent::factory()->create(['is_active' => true]);
    $item = SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Perjalanan dinas',
        'volume' => 1,
        'unit' => 'paket',
        'unit_price' => 1_000_000,
    ]);

    $component->update(['is_active' => false]);

    expect(BudgetLineItem::find($item->id))->not->toBeNull()
        ->and($submission->fresh()->budgetLineItems()->sum('total'))->toBe(1_000_000);
});
```

- [ ] **Step 3: Jalankan kedua test untuk memastikan gagal**

Run PEST `tests/Feature/Budget/`.
Expected: FAIL dengan "Class App\Actions\Budget\SaveBudgetLineItem not found".

- [ ] **Step 4: Tulis Action**

```php
<?php

namespace App\Actions\Budget;

use App\Models\BudgetComponent;
use App\Models\BudgetLineItem;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

class SaveBudgetLineItem
{
    use AsAction;

    /**
     * @param  array{budget_component_id: int, item_name: string, volume: int, unit: string, unit_price: int}  $attributes
     */
    public function handle(FormSubmission $submission, User $user, array $attributes): BudgetLineItem
    {
        // BR-BUD-02, BR-BUD-03, BR-BUD-09 — sumbu siapa dan keadaan dijaga Policy.
        if (!$user->can('update-budget', $submission)) {
            throw new AuthorizationException('Tidak berwenang menyunting anggaran pengajuan ini.');
        }

        // BR-BUD-04 — volume dan harga satuan wajib positif.
        if ($attributes['volume'] <= 0 || $attributes['unit_price'] <= 0) {
            throw ValidationException::withMessages([
                'volume' => 'Volume dan harga satuan harus lebih dari nol.',
            ]);
        }

        $component = BudgetComponent::query()->findOrFail($attributes['budget_component_id']);

        // BR-BUD-05 — komponen non-aktif tidak bisa dipilih untuk item BARU.
        // Item lama yang terlanjur memakainya sengaja tidak disentuh.
        if (!$component->is_active) {
            throw ValidationException::withMessages([
                'budget_component_id' => 'Komponen anggaran ini sudah tidak aktif.',
            ]);
        }

        $total = $attributes['volume'] * $attributes['unit_price'];

        $this->guardSchemeLimit($submission, $total);

        return BudgetLineItem::create([
            'form_submission_id' => $submission->id,
            'budget_component_id' => $component->id,
            'item_name' => $attributes['item_name'],
            'volume' => $attributes['volume'],
            'unit' => $attributes['unit'],
            'unit_price' => $attributes['unit_price'],
            'total' => $total,
        ]);
    }

    /**
     * BR-BUD-01 (cermin BR-SM-06) — grand total tidak boleh melebihi scheme.max_budget.
     * Submission tanpa skema tidak punya batas (BR-SCH-04).
     */
    private function guardSchemeLimit(FormSubmission $submission, int $incomingTotal): void
    {
        $scheme = $submission->scheme;

        if ($scheme === null) {
            return;
        }

        $grandTotal = (int) $submission->budgetLineItems()->sum('total') + $incomingTotal;

        if ($grandTotal > $scheme->max_budget) {
            throw ValidationException::withMessages([
                'total' => 'Total anggaran melebihi batas maksimum skema.',
            ]);
        }
    }
}
```

- [ ] **Step 5: Jalankan test sampai lulus**

Run PEST `tests/Feature/Budget/`.
Expected: PASS — 3 test limit, 7 test validasi (`BUD-ITEM-01` menghasilkan 4 dataset),
plus 7 test dari Task 7.

- [ ] **Step 6: Keluarkan ID dari pending**

`pending.txt`: hapus `BUD-LIMIT-01..03` dan `BUD-ITEM-01..04`; plafon `# max: 118`.

- [ ] **Step 7: Gate penuh lalu commit**

Run QUALITY. Expected hijau seluruhnya.

```bash
git add app tests docs/requirement-analysis/bdd
git commit -m "feat(budget): add save budget line item action for BR-BUD-01, 04, 05"
```

---

## Task 9: Sambungkan penguncian status ke Action

**Files:**
- Modify: `tests/Feature/Budget/BudgetAuthorizationTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`

**Interfaces:**
- Consumes: `SaveBudgetLineItem` (Task 8), `BudgetLineItemPolicy` (Task 7)

Task 7 membuktikan Policy menolak; task ini membuktikan **Action ikut menolak** — supaya
penguncian tidak bocor lewat jalur yang tidak memanggil Gate.

- [ ] **Step 1: Tambahkan test yang gagal**

Tambahkan di akhir `tests/Feature/Budget/BudgetAuthorizationTest.php`:

```php
it('[BUD-LOCK-01] action menolak penyuntingan pada status terminal', function (string $state) {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => $state,
        'scheme_id' => null,
    ]);
    $component = App\Models\BudgetComponent::factory()->create(['is_active' => true]);

    expect(fn () => App\Actions\Budget\SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 1,
        'unit' => 'unit',
        'unit_price' => 1_000_000,
    ]))->toThrow(Illuminate\Auth\Access\AuthorizationException::class);
})->with([Approved::class, Rejected::class, Withdrawn::class]);

it('[BUD-LOCK-02] action menerima penyuntingan saat perlu revisi', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => NeedsRevision::class,
        'scheme_id' => null,
    ]);
    $component = App\Models\BudgetComponent::factory()->create(['is_active' => true]);

    $item = App\Actions\Budget\SaveBudgetLineItem::run($submission, $owner, [
        'budget_component_id' => $component->id,
        'item_name' => 'Sensor kualitas air',
        'volume' => 1,
        'unit' => 'unit',
        'unit_price' => 1_000_000,
    ]);

    expect($item->total)->toBe(1_000_000);
});
```

- [ ] **Step 2: Jalankan test**

Run PEST `tests/Feature/Budget/BudgetAuthorizationTest.php`.
Expected: PASS tanpa perubahan kode — Action sudah memanggil Gate di Task 8.

Kalau **gagal**, berarti Action melewati Policy: perbaiki Action, jangan melonggarkan test.

- [ ] **Step 3: Keluarkan ID dari pending**

`BUD-LOCK-01` dan `BUD-LOCK-02` sudah dihapus di Task 7 — tidak ada perubahan
`pending.txt` di sini. Turunkan plafon menjadi `# max: 116` hanya kalau langkah
verifikasi di bawah menunjukkan jumlah entri sudah 116; kalau belum, biarkan.

- [ ] **Step 4: Verifikasi jumlah**

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt
```

Sesuaikan `# max:` dengan angka yang muncul.

- [ ] **Step 5: Gate penuh lalu commit**

Run QUALITY.

```bash
git add tests docs/requirement-analysis/bdd
git commit -m "test(budget): assert action enforces budget lock states"
```

---

## Task 10: Jejak audit dan penutupan irisan

**Files:**
- Modify: `tests/Feature/BudgetAuditTrailTest.php`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/README.md`

**Interfaces:**
- Consumes: seluruh keluaran Task 7–9

`BudgetLineItem::booted()` sudah menulis jejak audit saat status NEEDS_REVISION. Task ini
menyambungkan perilaku itu ke ID skenario, lalu menutup irisan vertikal.

- [ ] **Step 1: Kenali mekanismenya**

Jejak audit anggaran memakai `spatie/laravel-activitylog`, **bukan relasi Eloquent**.
`BudgetLineItem::booted()` memasang hook `updating` yang memanggil `activity('budget')`
dengan properti `old` dan `new`, dan hanya saat submission berstatus NEEDS_REVISION
(BR-BUD-08). Test membacanya lewat `Activity::inLog('budget')`.

Test yang sudah ada di `tests/Feature/BudgetAuditTrailTest.php:20` sudah membuktikan
nilai lama dan baru — ia hanya belum menyebut ID skenario.

- [ ] **Step 2: Sisipkan ID ke test yang sudah ada**

Ubah baris 20 dari:

```php
test('edit budget line item saat NEEDS_REVISION tercatat di audit trail dengan old dan new', function () {
```

menjadi:

```php
test('[BUD-AUDIT-01] edit budget line item saat NEEDS_REVISION tercatat di audit trail dengan old dan new', function () {
```

Jangan mengubah isi test-nya — ia sudah membuktikan yang benar.

- [ ] **Step 3: Tambahkan test pelaku dan waktu**

Tambahkan di akhir `tests/Feature/BudgetAuditTrailTest.php`:

```php
test('[BUD-AUDIT-02] jejak audit menyimpan pelaku dan waktu perubahan', function () {
    $owner = User::factory()->create();
    $submission = FormSubmission::factory()->create([
        'submitted_by' => $owner->id,
        'status' => NeedsRevision::class,
    ]);
    $component = BudgetComponent::factory()->create(['is_active' => true]);
    $item = BudgetLineItem::factory()->create([
        'form_submission_id' => $submission->id,
        'budget_component_id' => $component->id,
        'volume' => 1,
        'unit_price' => 1_000_000,
        'total' => 1_000_000,
    ]);

    $this->actingAs($owner);
    $item->update(['unit_price' => 2_000_000, 'total' => 2_000_000]);

    $activity = Activity::inLog('budget')->latest()->first();

    expect($activity)->not->toBeNull()
        ->and($activity->causer_id)->toBe($owner->id)
        ->and($activity->created_at)->not->toBeNull();
});
```

Pastikan blok `use` di atas berkas memuat `App\Models\BudgetComponent`,
`App\Models\BudgetLineItem`, `App\Models\FormSubmission`, `App\Models\User`,
`App\States\Submission\NeedsRevision`, dan `Spatie\Activitylog\Models\Activity` —
sebagian sudah ada; tambahkan yang belum.

`causedBy(auth()->user())` di model berarti test **wajib** `actingAs` sebelum `update()`,
kalau tidak `causer_id` bernilai null dan assertion-nya gagal dengan benar.

- [ ] **Step 4: Jalankan test**

Run PEST `tests/Feature/BudgetAuditTrailTest.php`. Expected: PASS.

- [ ] **Step 5: Keluarkan ID dari pending**

`pending.txt`: hapus `BUD-AUDIT-01` dan `BUD-AUDIT-02`; sesuaikan plafon dengan jumlah
entri sesungguhnya (seharusnya `# max: 114`).

- [ ] **Step 6: Tinjau kosakata langkah**

Bandingkan frasa Given/When/Then yang benar-benar dipakai di sepuluh berkas `.feature`
M1 dengan tabel *Kosakata langkah* di `docs/requirement-analysis/bdd/README.md`:

```bash
find docs/requirement-analysis/bdd -name "*.feature" -exec cat {} + \
  | grep -E "^\s+(Given|When|Then|And)" \
  | sed -E 's/"[^"]*"/"X"/g; s/\b[0-9]+\b/N/g; s/^\s+//' \
  | sort | uniq -c | sort -rn | head -30
```

Frasa yang muncul ≥3 kali tapi belum ada di tabel → **tambahkan barisnya**. Ini inti
Fase B: kosakata yang terbukti dipakai, bukan yang diperkirakan.

- [ ] **Step 7: Verifikasi akhir**

```bash
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt    # 114
grep -vcE '^\s*(#|$)' docs/requirement-analysis/bdd/uncovered.txt  # 59
```

Run QUALITY. Expected hijau seluruhnya.

- [ ] **Step 8: Commit**

```bash
git add tests docs/requirement-analysis/bdd
git commit -m "test(budget): tie audit trail tests to scenario ids and refresh step vocabulary"
```

---

## Self-Review

**Cakupan spec.** Sepuluh berkas `.feature` di [spec §1](../design/bdd-coverage/m1-submission-budget.md)
punya task: wizard (T1), submit (T2), state + access (T3), members + ownership +
withdrawal (T4), archival + target_output (T5), budget (T6). Kedua puluh tiga BR M1
keluar dari `uncovered.txt` di T1–T6 — diverifikasi langkah `grep -E '^BR-(SM|BUD)-'`
di T6 Step 4. Irisan vertikal spec §"kedalaman" dikerjakan T7–T10.

**Penyimpangan dari spec yang disengaja.** Satu: spec §10 menyebut `BUD-LOCK-01` sebagai
`Scenario Outline` dengan tiga status. Plan menguji penguncian itu **dua kali** — di
Policy (T7) dan di Action (T9) — karena penguncian yang hanya dijaga Policy bocor lewat
jalur yang tidak memanggil Gate. ID-nya tetap satu; dua test boleh menyebut ID yang sama.

**Yang sengaja TIDAK dikerjakan.** Skenario M1 di luar Budget tidak diimplementasikan —
mereka tetap di `pending.txt` (114 entri). Itu memang rencananya: irisan vertikal ada
untuk membuktikan pola, bukan untuk menyelesaikan M1. Menurunkan sisa `pending.txt`
adalah pekerjaan M1 lanjutan yang punya jalurnya sendiri.

**Konsistensi tipe.** `BudgetLineItemPolicy::update(User, FormSubmission): bool`
didefinisikan T7, dipakai T8 lewat `$user->can('update-budget', $submission)` dan
diverifikasi T9. `SaveBudgetLineItem::run(FormSubmission, User, array): BudgetLineItem`
didefinisikan T8, dipakai T9 dan T10. Kunci `$attributes` sama persis di ketiga task:
`budget_component_id`, `item_name`, `volume`, `unit`, `unit_price`.

**Aritmetika ledger.** `pending` 48 → 59 → 76 → 87 → 99 → 114 → 128, lalu turun
128 → 125 → 118 → 116 → 114. `uncovered` 83 → 82 → 78 → 75 → 71 → 67 → 59. Selisih
`uncovered` 24 = 23 BR M1 + `BR-IAM-10` yang ikut lunas lewat tag kembar di `SM-OWNER-*`.

**Risiko yang sudah ditangani.** T1 Step 3 menyuruh berhenti kalau assertion 3 menolak
`BR-SM-17` — tanda DDD belum sinkron, bukan tanda tag salah. T4 Step 5 mengingatkan
assertion 9 akan menangkap `BR-IAM-10` yang lupa dihapus. T9 Step 2 menegaskan kegagalan
berarti Action melewati Policy — perbaiki Action, jangan longgarkan test. T10 Step 2
menyuruh membaca model sebelum menebak nama relasi jejak audit.
