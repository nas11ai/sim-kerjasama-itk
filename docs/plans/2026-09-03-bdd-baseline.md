# BDD Baseline — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Memasang infrastruktur BDD — katalog business rule, parser Gherkin, dan guard test berisi delapan assertion — lalu membuktikannya dengan menulis Wave 1a (Form Engine + Scheme, 18 BR, ~70 skenario).

**Architecture:** File `.feature` menjadi spesifikasi yang dibaca manusia dan agent; sebuah Pest test mem-parse seluruh file itu, mencocokkannya dengan tabel business rule di dokumen DDD dan dengan test yang ada di `tests/`, lalu gagal kalau ada yang tidak nyambung. Tidak ada runner Gherkin dan tidak ada step CI baru — guard-nya Pest biasa yang ikut `composer run quality`. Utang dilacak dua daftar berplafon (`pending.txt`, `uncovered.txt`) yang hanya boleh menyusut, meniru `excludePaths` di `phpstan-strict.neon`.

**Tech Stack:** Laravel 13 · PHP 8.4 · PostgreSQL · Pest 4 · `behat/gherkin` v4.17 (parser saja, bukan runner)

**Spec:** [`docs/design/bdd-gherkin-baseline.md`](../design/bdd-gherkin-baseline.md)

## Global Constraints

- PHP **8.4** — bukan 8.3, bukan 8.5 ([ADR 0003](../adr/0003-pin-php-84-across-environments.md)). Host development saat ini PHP 8.5.4, jadi seluruh tooling **wajib** lewat container (lihat "Menjalankan perintah").
- Database **PostgreSQL**. Dilarang menulis SQL berasumsi MySQL. Test tidak boleh pakai SQLite.
- Kode baru di `app/` selain `Console`, `Exports`, `Http`, `Mail`, `Models`, `Providers`, `Services` otomatis kena **PHPStan level 9** ([ADR 0001](../adr/0001-two-tier-static-analysis.md)). `app/Support/Bdd/` termasuk. Dilarang `@phpstan-ignore`, entri baseline, `assert()`, inline `@var`, cast, atau melebarkan tipe untuk membungkam error.
- Penamaan kode **English**, komentar **Bahasa Indonesia**. Sebut kode business rule di komentar.
- Keyword Gherkin **English**, prosa skenario **Bahasa Indonesia**, istilah domain mengikuti [ubiquitous language](../requirement-analysis/ddd/02_ubiquitous_language.md).
- Setiap task berakhir dengan `composer run quality` hijau sebelum commit.
- Commit memakai Conventional Commits, **tanpa trailer co-author**.
- **Dilarang menambah dependency** selain `behat/gherkin` di Task 3.
- **Dilarang mengubah `docs/requirement-analysis/ddd/`** di luar Task 1, yang cakupannya sudah disetujui eksplisit.

### Menjalankan perintah

```bash
# Postgres untuk test
docker run -d --name simpas_dev_pg -e POSTGRES_DB=simpas_test \
  -e POSTGRES_USER=postgres -e POSTGRES_PASSWORD=postgres \
  -p 55432:5432 postgres:18-alpine

# Image PHP 8.4 milik proyek — WAJIB dibangun sekali sebelum task mana pun.
# `php:8.4-cli` polos TIDAK punya pdo_pgsql (hanya pdo_sqlite), sehingga suite
# penuh dan `composer run quality` mustahil jalan di sana. Juga tidak punya
# unzip/git yang dibutuhkan Composer.
docker build -t simpas-php84:local - <<'DOCKERFILE'
FROM php:8.4-cli
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
RUN apt-get update \
 && apt-get install -y --no-install-recommends libpq-dev unzip git \
 && docker-php-ext-install pdo_pgsql \
 && apt-get purge -y --auto-remove \
 && rm -rf /var/lib/apt/lists/*
DOCKERFILE

# Test / artisan / composer (host PHP 8.5.4 — container wajib)
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest
```

`phpunit.xml` meng-hardcode `DB_PORT=5432` dan `DB_PASSWORD=root`; environment variable menimpanya.

**Kenapa bukan `php:8.4-cli` langsung.** `AGENTS.md` menganjurkan image itu, tapi
anjuran tersebut tidak lengkap: Pest butuh `pdo_pgsql` dan image resminya tidak
membawanya. Test ber-scope yang tidak menyentuh database tetap jalan di sana —
itulah kenapa kekurangannya baru ketahuan di Task 3. Untuk `composer run quality`,
`simpas-php84:local` wajib.

---

## Cakupan Plan Ini

Spec mendefinisikan enam wave berjumlah ~363 skenario. Plan ini **tidak** mencakup semuanya — ia mencakup **infrastruktur (Task 1–5) + Wave 1a (Task 6–10)**, lalu berhenti.

Alasannya diambil dari spec §7 sendiri: Form Engine dan Scheme adalah bagian yang paling sudah jadi di codebase, sehingga skenarionya paling cepat berubah dari `pending` menjadi hijau — dan itu memvalidasi mekanismenya sebelum 275 skenario sisanya ditulis di atas fondasi yang belum terbukti. Wave 1b sampai 5 mendapat plan sendiri-sendiri, yang jauh lebih murah ditulis setelah pola di sini terbukti.

### Penyesuaian terhadap spec

Dua penyimpangan yang disengaja, dicatat di sini supaya tidak terlihat seperti kelalaian:

| Penyesuaian | Alasan |
| ----------- | ------ |
| **`uncovered.txt` ditambahkan** — daftar BR yang belum punya skenario, berplafon, hanya boleh menyusut (92 → 0) | Assertion 7 ("setiap BR punya skenario") akan gagal untuk 92 BR sejak commit pertama, jauh sebelum wave terakhir mendarat. Tanpa daftar ini, assertion 7 baru bisa dinyalakan di akhir — dan assertion yang menganggur berbulan-bulan sama saja dengan tidak ada. Task 11 Step 5 memperbarui spec. |
| **BR-FE-05 dan BR-FE-06 pindah dari Wave 1a ke Wave 2** | Keduanya mengatur `ReviewFormResponse` dan `ReviewSummary` — mereka saling mengunci dengan BR-REV-08 dan BR-REV-06. Menulisnya terpisah dari rule Review berarti menulis separuh aturan tanpa pasangannya. Wave 1a jadi 18 BR, Wave 2 jadi 15 BR; total tetap 92. |
| **Guard pindah dari `tests/Feature/` ke `tests/Unit/Bdd/`** | `tests/Pest.php` memasang `RefreshDatabase` ke **seluruh** `tests/Feature` (`pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature')`). Guard ini membaca berkas, bukan tabel — menaruhnya di `Feature` berarti menjalankan migrasi Postgres setiap kali `composer run quality` dipanggil, tanpa satu pun query. Di `tests/Unit` ia cukup `uses(TestCase::class)` untuk mendapat container yang dibutuhkan `base_path()`. Spec §5 menyebut path lama; T11 Step 5 memperbaikinya. |

---

## File Structure

| File | Tanggung jawab |
| ---- | -------------- |
| `docs/requirement-analysis/ddd/core/01_submission.md` | + agregat `SubmissionTargetOutput`, + BR-SM-14, BR-SM-15 |
| `docs/requirement-analysis/ddd/supporting/01_budget.md` | − `SubmissionOuter`/`OuterType`, cabut BR-BUD-06 |
| `docs/requirement-analysis/ddd/02_ubiquitous_language.md` | + istilah **Target Luaran** |
| `app/Support/Bdd/BusinessRuleCatalog.php` | Baca definisi BR dari tabel dokumen DDD |
| `app/Support/Bdd/ScenarioDescriptor.php` | Value object satu skenario: id, tag, br, milestone, kelas, file, baris |
| `app/Support/Bdd/FeatureCatalog.php` | Parse seluruh `.feature` → `list<ScenarioDescriptor>` |
| `app/Support/Bdd/LedgerFile.php` | Baca `pending.txt` / `uncovered.txt` berikut plafonnya |
| `tests/Unit/Bdd/BddCoverageTest.php` | Delapan assertion guard |
| `docs/requirement-analysis/bdd/README.md` | Konvensi ID, tag, cara menambah skenario |
| `docs/requirement-analysis/bdd/pending.txt` | Skenario belum diimplementasi + plafon |
| `docs/requirement-analysis/bdd/uncovered.txt` | BR belum punya skenario + plafon |
| `docs/requirement-analysis/bdd/non-behavioral.md` | 9 BR yang bukan perilaku + penegaknya |
| `docs/requirement-analysis/bdd/open-questions.md` | Register kontradiksi |
| `docs/requirement-analysis/bdd/generic/form_engine/*.feature` | Wave 1a — 11 BR Form Engine |
| `docs/requirement-analysis/bdd/supporting/scheme.feature` | Wave 1a — 7 BR Scheme |
| `tests/Fixtures/bdd/*.feature` | Fixture untuk unit test parser |

---

## Task 1: Koreksi DDD — `SubmissionTargetOutput`

**Files:**
- Modify: `docs/requirement-analysis/ddd/core/01_submission.md`
- Modify: `docs/requirement-analysis/ddd/supporting/01_budget.md`
- Modify: `docs/requirement-analysis/ddd/02_ubiquitous_language.md`

**Interfaces:**
- Produces: kode BR `BR-SM-14` dan `BR-SM-15` sebagai baris tabel; `BR-BUD-06` tidak lagi ada sebagai baris tabel

Ini **prasyarat keras** semua task berikutnya yang menulis skenario ber-tag `@BR-SM-14`/`@BR-SM-15`: guard assertion 3 menolak tag yang BR-nya tidak terdefinisi. Alasan lengkap dan jejak penelusuran ke dump legacy ada di [spec §8.1](../design/bdd-gherkin-baseline.md).

Ringkasnya: `submission_outers` di dump legacy adalah pivot murni (`submission_id` × `submission_outer_type_id`) tanpa kolom uang, dengan taksonomi identik `research_outputs.output_type`. Ia luaran yang **dijanjikan**, bukan yang direalisasikan. Atribut `amount` dan `description` yang tertulis di BC Budget terbawa keliru dari entitas lain, `budget_plan_outers`.

- [ ] **Step 1: Verifikasi kondisi awal**

```bash
grep -c "BR-BUD-06" docs/requirement-analysis/ddd/supporting/01_budget.md
grep -c "SubmissionOuter" docs/requirement-analysis/ddd/supporting/01_budget.md
```
Expected: keduanya > 0. Kalau sudah 0, task ini sudah dikerjakan — hentikan dan lapor.

- [ ] **Step 2: Hapus `SubmissionOuter` dan `OuterType` dari diagram agregat Budget**

Di `supporting/01_budget.md`, pada blok ```` ```mermaid ```` bagian "Aggregates", hapus kedua class berikut beserta baris relasinya:

```
    class SubmissionOuter {
        +SubmissionOuterId id
        +FormSubmissionId form_submission_id
        +OuterTypeId outer_type_id
        +Money amount
        +string description
    }

    class OuterType {
        +OuterTypeId id
        +string name
    }
```

dan baris relasi:

```
    SubmissionOuter "*" --> "1" OuterType : typed_as
```

Sisakan `BudgetLineItem` dan `BudgetComponent` beserta relasinya.

- [ ] **Step 3: Cabut BR-BUD-06 dari tabel Business Rules Budget**

Hapus baris ini dari tabel:

```
| BR-BUD-06 | SubmissionOuter hanya bisa diubah jika period config mengizinkan                                                                                |
```

Lalu tambahkan catatan tepat di bawah tabel:

```markdown
> **BR-BUD-06 dicabut 2026-09-03.** Entitas `SubmissionOuter` ternyata luaran yang
> dijanjikan peneliti — bukan data anggaran — dan tidak punya kolom uang di tabel
> legacy manapun. Ia pindah ke BC Submission sebagai `SubmissionTargetOutput`,
> diatur BR-SM-14 dan BR-SM-15. Nomor BR-BUD-06 pensiun dan tidak dipakai ulang.
> Alasan lengkap: `docs/design/bdd-gherkin-baseline.md` §8.1.
```

- [ ] **Step 4: Tambah agregat `SubmissionTargetOutput` di BC Submission**

Di `core/01_submission.md`, dalam blok ```` ```mermaid ```` bagian "Aggregates", tambahkan class berikut setelah `Partner`:

```
    class SubmissionTargetOutput {
        +SubmissionTargetOutputId id
        +FormSubmissionId form_submission_id
        +string output_type
        note "output_type mengikuti output_type_definitions — taksonomi yang sama dengan ResearchOutput"
    }
```

dan tambahkan baris relasi di bawah relasi `AdditionalFile`:

```
    FormSubmission "1" --> "0..*" SubmissionTargetOutput : promises
```

- [ ] **Step 5: Tambah BR-SM-14 dan BR-SM-15**

Di `core/01_submission.md`, tambahkan dua baris di akhir tabel Business Rules:

```
| BR-SM-14 | Target luaran hanya boleh dari `output_type` yang terdaftar di `output_type_definitions`. Jika submission punya scheme, pilihan dibatasi lagi oleh `scheme.rules.allowed_output_types`; scheme tanpa key itu tidak membatasi apa pun |
| BR-SM-15 | Target luaran bisa diubah sampai submission REJECTED atau WITHDRAWN — termasuk setelah APPROVED, karena target menyesuaikan jalannya kegiatan. Hanya `submitted_by` yang boleh mengubahnya, dan setiap perubahan setelah APPROVED wajib tercatat di audit trail dengan nilai lama dan baru |
```

- [ ] **Step 6: Tambah istilah Target Luaran ke ubiquitous language**

Di `02_ubiquitous_language.md`, pada tabel bagian **Submission (SIMPAS-specific)**, tambahkan baris:

```
| **Target Luaran**           | Luaran yang **dijanjikan** peneliti saat mengajukan — tabel `submission_target_outputs`. Taksonominya sama dengan Research Output, bedanya ini janji dan itu realisasi. Jangan disebut "SubmissionOuter" (nama warisan fork yang menyesatkan). |
```

Lalu di tabel **Istilah yang Tidak Dipakai di Kode**, tambahkan:

```
| `SubmissionOuter` / `submission_outers` | `SubmissionTargetOutput` / `submission_target_outputs` | Nama lama menyesatkan — "outer" dibaca sebagai data anggaran |
```

- [ ] **Step 7: Verifikasi hasil**

```bash
# BR baru terdefinisi sebagai baris tabel
grep -E "^\| BR-SM-1[45] +\|" docs/requirement-analysis/ddd/core/01_submission.md | wc -l
# BR-BUD-06 tidak lagi jadi baris tabel
grep -cE "^\| BR-BUD-06 +\|" docs/requirement-analysis/ddd/supporting/01_budget.md
# total definisi BR
grep -rhoE "^\| (BR-[A-Z]+-[0-9]+) +\|" docs/requirement-analysis/ddd/ | grep -oE "BR-[A-Z]+-[0-9]+" | sort -u | wc -l
```
Expected: `2`, lalu `0`, lalu `101`.

- [ ] **Step 8: Commit**

```bash
git add docs/requirement-analysis/ddd
git commit -m "docs(ddd): move SubmissionOuter to Submission BC as SubmissionTargetOutput

BR-BUD-06 dicabut — entitasnya luaran yang dijanjikan, bukan data anggaran.
Digantikan BR-SM-14 (batas tipe) dan BR-SM-15 (lifecycle + audit)."
```

---

## Task 2: `BusinessRuleCatalog` — baca definisi BR dari DDD

**Files:**
- Create: `app/Support/Bdd/BusinessRuleCatalog.php`
- Test: `tests/Unit/Bdd/BusinessRuleCatalogTest.php`

**Interfaces:**
- Produces: `BusinessRuleCatalog::__construct(string $docRoot)`, `->codes(): list<string>` — daftar kode BR terurut, unik

Katalog hanya menghitung **baris tabel** sebagai definisi. Penyebutan BR di prosa (mis. "melanggar BR-REV-07" di kalimat) sengaja diabaikan, supaya guard bisa membedakan "rule ini ada" dari "rule ini kebetulan disebut". Pola `^\|\s*(BR-[A-Z]+-\d+)\s*\|` sudah diverifikasi cocok tepat 100 kali di DDD sebelum Task 1, dan 101 kali sesudahnya.

- [ ] **Step 1: Tulis test yang gagal**

```php
<?php
// tests/Unit/Bdd/BusinessRuleCatalogTest.php

use App\Support\Bdd\BusinessRuleCatalog;
use Tests\TestCase;

// base_path() butuh container yang sudah di-boot. RefreshDatabase TIDAK dipakai —
// katalog ini membaca berkas, bukan tabel.
uses(TestCase::class);

it('reads business rule codes from ddd table rows', function () {
    $catalog = new BusinessRuleCatalog(base_path('docs/requirement-analysis/ddd'));

    $codes = $catalog->codes();

    expect($codes)->toContain('BR-SM-01')
        ->and($codes)->toContain('BR-SM-14')
        ->and($codes)->toContain('BR-SM-15')
        ->and($codes)->toContain('BR-FE-11')
        ->and($codes)->toContain('BR-SCH-09');
});

it('excludes retired rules that are no longer table rows', function () {
    $catalog = new BusinessRuleCatalog(base_path('docs/requirement-analysis/ddd'));

    expect($catalog->codes())->not->toContain('BR-BUD-06');
});

it('returns a unique sorted list', function () {
    $codes = (new BusinessRuleCatalog(base_path('docs/requirement-analysis/ddd')))->codes();

    $sorted = $codes;
    sort($sorted);

    expect($codes)->toBe($sorted)
        ->and($codes)->toBe(array_values(array_unique($codes)));
});

it('ignores prose mentions that are not table definitions', function () {
    $dir = sys_get_temp_dir().'/bdd-catalog-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/sample.md', <<<'MD'
        Kalimat ini menyebut BR-XX-99 tapi bukan definisi.

        | Kode | Rule |
        | ---- | ---- |
        | BR-XX-01 | Ini definisi sungguhan |
        MD);

    $codes = (new BusinessRuleCatalog($dir))->codes();

    expect($codes)->toBe(['BR-XX-01']);

    unlink($dir.'/sample.md');
    rmdir($dir);
});
```

- [ ] **Step 2: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BusinessRuleCatalogTest.php
```
Expected: FAIL — `Class "App\Support\Bdd\BusinessRuleCatalog" not found`.

- [ ] **Step 3: Tulis implementasi**

```php
<?php

namespace App\Support\Bdd;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Membaca definisi business rule dari dokumen DDD.
 *
 * Hanya baris tabel yang dihitung sebagai definisi. Penyebutan di prosa
 * diabaikan, supaya guard bisa membedakan "rule ini ada" dari "rule ini
 * kebetulan disebut" — tanpa itu, typo di tag tidak akan pernah ketahuan.
 */
final class BusinessRuleCatalog
{
    private const DEFINITION_PATTERN = '/^\|\s*(BR-[A-Z]+-\d+)\s*\|/m';

    public function __construct(private readonly string $docRoot) {}

    /**
     * @return list<string> kode BR, unik dan terurut
     */
    public function codes(): array
    {
        if (!is_dir($this->docRoot)) {
            throw new RuntimeException("Direktori DDD tidak ditemukan: {$this->docRoot}");
        }

        $codes = [];

        foreach ($this->markdownFiles() as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                throw new RuntimeException("Gagal membaca {$file}");
            }

            if (preg_match_all(self::DEFINITION_PATTERN, $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $code) {
                $codes[$code] = true;
            }
        }

        $result = array_keys($codes);
        sort($result);

        return $result;
    }

    /**
     * @return list<string> path absolut setiap berkas .md di bawah docRoot
     */
    private function markdownFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->docRoot, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $entry) {
            // Guard eksplisit, bukan anotasi @var: iterator memberi `mixed`, dan
            // menyempitkannya lewat instanceof memasok tipe tanpa membungkam apa pun.
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if ($entry->isFile() && $entry->getExtension() === 'md') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
```

- [ ] **Step 4: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BusinessRuleCatalogTest.php
```
Expected: PASS, 4 test.

- [ ] **Step 5: Jalankan PHPStan level 9**

Run:
```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  simpas-php84:local ./vendor/bin/phpstan analyse -c phpstan-strict.neon --memory-limit=1G
```
Expected: `[OK] No errors`. Kalau ada error di `app/Support/Bdd/`, perbaiki penyebabnya — dilarang menambah `excludePaths` atau `@phpstan-ignore`.

- [ ] **Step 6: Commit**

```bash
git add app/Support/Bdd/BusinessRuleCatalog.php tests/Unit/Bdd/BusinessRuleCatalogTest.php
git commit -m "feat(bdd): read business rule definitions from ddd tables"
```

---

## Task 3: Parser Gherkin — `ScenarioDescriptor` + `FeatureCatalog`

**Files:**
- Modify: `composer.json` (require-dev `behat/gherkin`)
- Create: `app/Support/Bdd/ScenarioDescriptor.php`
- Create: `app/Support/Bdd/FeatureCatalog.php`
- Create: `tests/Fixtures/bdd/valid.feature`
- Test: `tests/Unit/Bdd/FeatureCatalogTest.php`

**Interfaces:**
- Consumes: —
- Produces:
  - `ScenarioDescriptor` readonly: `->id: string`, `->tags: list<string>`, `->businessRules: list<string>`, `->milestone: ?string`, `->class: ?string`, `->file: string`, `->line: int`
  - `FeatureCatalog::__construct(string $featureRoot)`, `->scenarios(): list<ScenarioDescriptor>`

**Catatan API yang sudah diverifikasi terhadap `behat/gherkin` v4.17.0** — jangan diubah tanpa menguji ulang:

| Fakta | Konsekuensi |
| ----- | ----------- |
| `Parser::parseFile()` menuntut **path absolut** | Wajib `realpath()`; path relatif melempar `InvalidArgumentException: The file should be an absolute path` |
| `getTags()` mengembalikan tag **tanpa** `@` | Cocokkan `BR-SM-14`, bukan `@BR-SM-14` |
| Namespace kelas ada di `Behat\Gherkin\...` (bukan `Behat\Gherkin\src\...`) | `use Behat\Gherkin\Parser;` |
| `Scenario Outline` menghasilkan `OutlineNode`, `Scenario` menghasilkan `ScenarioNode` | Keduanya punya `getTitle()` dan `getTags()`; ID diambil sama |
| `ArrayKeywords` butuh key lengkap `feature/background/scenario/scenario_outline/examples/given/when/then/and/but` | Kurang satu key → parser gagal diam-diam |

- [ ] **Step 1: Pasang dependency**

```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e COMPOSER_HOME=/tmp/composer composer:2 \
  require --dev behat/gherkin --no-interaction
```
Expected: `Locking behat/gherkin (v4.17.0)` atau lebih baru pada seri v4.

- [ ] **Step 2: Buat fixture**

```gherkin
# tests/Fixtures/bdd/valid.feature
Feature: Fixture Parser
  Dipakai unit test FeatureCatalog. Bukan spesifikasi sungguhan.

  Background:
    Given kondisi awal apa pun

  @BR-FE-11 @M0 @RESEARCHER-02-02 @happy
  Scenario: [FX-SAMPLE-01] Skenario biasa dengan tiga jenis tag
    When sesuatu terjadi
    Then hasilnya terlihat

  @BR-FE-11 @BR-FE-15 @M0 @denial
  Scenario Outline: [FX-SAMPLE-02] Outline dengan dua business rule
    Given submission berstatus <status>
    When sesuatu terjadi
    Then ditolak

    Examples:
      | status    |
      | APPROVED  |
      | WITHDRAWN |
```

- [ ] **Step 3: Tulis test yang gagal**

```php
<?php
// tests/Unit/Bdd/FeatureCatalogTest.php

use App\Support\Bdd\FeatureCatalog;
use Tests\TestCase;

// base_path() butuh container. Tanpa RefreshDatabase — parser tidak menyentuh DB.
uses(TestCase::class);

it('parses scenarios and outlines alike', function () {
    $scenarios = (new FeatureCatalog(base_path('tests/Fixtures/bdd')))->scenarios();

    expect($scenarios)->toHaveCount(2)
        ->and(array_map(fn ($s) => $s->id, $scenarios))
        ->toBe(['FX-SAMPLE-01', 'FX-SAMPLE-02']);
});

it('extracts business rules, milestone and class from tags', function () {
    $scenarios = (new FeatureCatalog(base_path('tests/Fixtures/bdd')))->scenarios();

    expect($scenarios[0]->businessRules)->toBe(['BR-FE-11'])
        ->and($scenarios[0]->milestone)->toBe('M0')
        ->and($scenarios[0]->class)->toBe('happy')
        ->and($scenarios[1]->businessRules)->toBe(['BR-FE-11', 'BR-FE-15'])
        ->and($scenarios[1]->class)->toBe('denial');
});

it('records file and line for error messages', function () {
    $scenarios = (new FeatureCatalog(base_path('tests/Fixtures/bdd')))->scenarios();

    expect($scenarios[0]->file)->toEndWith('valid.feature')
        ->and($scenarios[0]->line)->toBeGreaterThan(0);
});

it('returns an empty list when no feature files exist', function () {
    $dir = sys_get_temp_dir().'/bdd-empty-'.uniqid();
    mkdir($dir);

    expect((new FeatureCatalog($dir))->scenarios())->toBe([]);

    rmdir($dir);
});

it('rejects a scenario whose title has no bracketed id', function () {
    $dir = sys_get_temp_dir().'/bdd-noid-'.uniqid();
    mkdir($dir);
    file_put_contents($dir.'/bad.feature', <<<'GHERKIN'
        Feature: Tanpa ID

          @BR-FE-11 @M0 @happy
          Scenario: Judul tanpa kurung siku
            When sesuatu terjadi
            Then hasilnya terlihat
        GHERKIN);

    expect(fn () => (new FeatureCatalog($dir))->scenarios())
        ->toThrow(RuntimeException::class, 'tanpa ID');

    unlink($dir.'/bad.feature');
    rmdir($dir);
});
```

- [ ] **Step 4: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/FeatureCatalogTest.php
```
Expected: FAIL — `Class "App\Support\Bdd\FeatureCatalog" not found`.

- [ ] **Step 5: Tulis `ScenarioDescriptor`**

```php
<?php

namespace App\Support\Bdd;

/**
 * Satu skenario Gherkin, sudah diurai jadi bagian yang dipakai guard.
 */
final readonly class ScenarioDescriptor
{
    /**
     * @param list<string> $tags          seluruh tag, tanpa '@'
     * @param list<string> $businessRules tag yang berbentuk BR-XX-NN
     */
    public function __construct(
        public string $id,
        public array $tags,
        public array $businessRules,
        public ?string $milestone,
        public ?string $class,
        public string $file,
        public int $line,
    ) {}

    /**
     * Lokasi ringkas untuk pesan kegagalan guard.
     */
    public function location(): string
    {
        return basename($this->file).':'.$this->line;
    }
}
```

- [ ] **Step 6: Tulis `FeatureCatalog`**

```php
<?php

namespace App\Support\Bdd;

use Behat\Gherkin\Keywords\ArrayKeywords;
use Behat\Gherkin\Lexer;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Parser;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Mengurai seluruh berkas .feature di bawah satu direktori.
 *
 * Parser dipakai sebagai pustaka saja — tidak ada runner Gherkin di repo ini.
 */
final class FeatureCatalog
{
    private const ID_PATTERN = '/^\[([A-Z]+-[A-Z]+-\d+)\]/';

    private const BR_PATTERN = '/^BR-[A-Z]+-\d+$/';

    private const MILESTONE_PATTERN = '/^M[0-5]$/';

    private const CLASSES = ['happy', 'boundary', 'denial'];

    public function __construct(private readonly string $featureRoot) {}

    /**
     * @return list<ScenarioDescriptor>
     */
    public function scenarios(): array
    {
        $parser = $this->parser();
        $descriptors = [];

        foreach ($this->featureFiles() as $file) {
            $feature = $parser->parseFile($file);

            if (!$feature instanceof FeatureNode) {
                continue;
            }

            foreach ($feature->getScenarios() as $scenario) {
                $title = (string) $scenario->getTitle();

                if (preg_match(self::ID_PATTERN, $title, $matches) !== 1) {
                    throw new RuntimeException(
                        "Skenario tanpa ID di {$file}:{$scenario->getLine()} — "
                        ."judul harus diawali [AREA-TOPIK-NN], ditemukan: \"{$title}\""
                    );
                }

                $tags = array_values($scenario->getTags());

                $descriptors[] = new ScenarioDescriptor(
                    id: $matches[1],
                    tags: $tags,
                    businessRules: array_values(array_filter(
                        $tags,
                        static fn (string $tag): bool => preg_match(self::BR_PATTERN, $tag) === 1
                    )),
                    milestone: $this->firstMatching($tags, self::MILESTONE_PATTERN),
                    class: $this->firstIn($tags, self::CLASSES),
                    file: $file,
                    line: $scenario->getLine(),
                );
            }
        }

        return $descriptors;
    }

    private function parser(): Parser
    {
        // Keyword English eksplisit — repo ini tidak memakai header '# language:'.
        $keywords = new ArrayKeywords(['en' => [
            'feature' => 'Feature',
            'background' => 'Background',
            'scenario' => 'Scenario',
            'scenario_outline' => 'Scenario Outline|Scenario Template',
            'examples' => 'Examples|Scenarios',
            'given' => 'Given',
            'when' => 'When',
            'then' => 'Then',
            'and' => 'And',
            'but' => 'But',
        ]]);

        return new Parser(new Lexer($keywords));
    }

    /**
     * @param list<string> $tags
     */
    private function firstMatching(array $tags, string $pattern): ?string
    {
        foreach ($tags as $tag) {
            if (preg_match($pattern, $tag) === 1) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @param list<string> $tags
     * @param list<string> $allowed
     */
    private function firstIn(array $tags, array $allowed): ?string
    {
        foreach ($tags as $tag) {
            if (in_array($tag, $allowed, true)) {
                return $tag;
            }
        }

        return null;
    }

    /**
     * @return list<string> path ABSOLUT — parseFile() menolak path relatif
     */
    private function featureFiles(): array
    {
        if (!is_dir($this->featureRoot)) {
            throw new RuntimeException("Direktori feature tidak ditemukan: {$this->featureRoot}");
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->featureRoot, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $entry) {
            // Guard eksplisit, bukan anotasi @var — lihat catatan yang sama di
            // BusinessRuleCatalog::markdownFiles().
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if (!$entry->isFile() || $entry->getExtension() !== 'feature') {
                continue;
            }

            $real = realpath($entry->getPathname());

            if ($real === false) {
                throw new RuntimeException("Gagal me-resolve path {$entry->getPathname()}");
            }

            $files[] = $real;
        }

        sort($files);

        return $files;
    }
}
```

- [ ] **Step 7: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/FeatureCatalogTest.php
```
Expected: PASS, 5 test.

- [ ] **Step 8: PHPStan level 9 + Pint**

```bash
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  simpas-php84:local ./vendor/bin/phpstan analyse -c phpstan-strict.neon --memory-limit=1G
docker run --rm -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  simpas-php84:local ./vendor/bin/pint
```
Expected: `[OK] No errors`, lalu Pint bersih.

- [ ] **Step 9: Commit**

```bash
git add composer.json composer.lock app/Support/Bdd tests/Unit/Bdd tests/Fixtures/bdd
git commit -m "feat(bdd): parse gherkin feature files into scenario descriptors"
```

---

## Task 4: Scaffold `bdd/` + guard assertion 1–3

**Files:**
- Create: `docs/requirement-analysis/bdd/README.md`
- Create: `docs/requirement-analysis/bdd/non-behavioral.md`
- Create: `docs/requirement-analysis/bdd/open-questions.md`
- Create: `docs/requirement-analysis/bdd/pending.txt`
- Create: `docs/requirement-analysis/bdd/uncovered.txt`
- Create: `docs/requirement-analysis/bdd/generic/form_engine/.gitkeep`
- Create: `app/Support/Bdd/LedgerFile.php`
- Test: `tests/Unit/Bdd/BddCoverageTest.php`
- Test: `tests/Unit/Bdd/LedgerFileTest.php`

**Interfaces:**
- Consumes: `FeatureCatalog::scenarios()`, `BusinessRuleCatalog::codes()`
- Produces: `LedgerFile::__construct(string $path)`, `->entries(): list<string>`, `->ceiling(): int`

Assertion 1–3 memeriksa **bentuk** skenario dan bisa dinyalakan sebelum ada satu pun skenario nyata — pada katalog kosong ketiganya lulus secara hampa, dan mulai berguna begitu Task 6 menulis file pertama.

- [ ] **Step 1: Tulis `pending.txt` dan `uncovered.txt`**

`docs/requirement-analysis/bdd/pending.txt`:

```
# Skenario yang sudah ditulis sebagai spec tapi belum punya test Pest.
#
# Plafon di bawah ditegakkan mesin (BddCoverageTest assertion 8). Bahwa angkanya
# hanya boleh TURUN ditegakkan review PR — sama persis pembagian kerja yang
# berlaku untuk excludePaths di phpstan-strict.neon.
#
# max: 0
```

`docs/requirement-analysis/bdd/uncovered.txt`:

```
# Business rule perilaku yang BELUM punya skenario. Menyusut ke nol saat wave
# terakhir mendarat. Aturan plafonnya sama dengan pending.txt.
#
# BR yang bukan perilaku TIDAK ditulis di sini — tempatnya non-behavioral.md.
#
# max: 92
BR-BUD-01
BR-BUD-02
BR-BUD-03
BR-BUD-04
BR-BUD-05
BR-BUD-08
BR-FE-01
BR-FE-02
BR-FE-04
BR-FE-05
BR-FE-06
BR-FE-08
BR-FE-09
BR-FE-10
BR-FE-11
BR-FE-12
BR-FE-13
BR-FE-14
BR-FE-15
BR-IAM-01
BR-IAM-02
BR-IAM-03
BR-IAM-04
BR-IAM-05
BR-IAM-06
BR-IAM-07
BR-IAM-08
BR-IAM-09
BR-IAM-10
BR-MON-01
BR-MON-02
BR-MON-03
BR-MON-04
BR-MON-05
BR-MON-06
BR-MON-07
BR-MON-08
BR-NOT-01
BR-NOT-02
BR-NOT-03
BR-NOT-04
BR-REV-01
BR-REV-02
BR-REV-03
BR-REV-04
BR-REV-05
BR-REV-06
BR-REV-07
BR-REV-08
BR-REV-09
BR-REV-10
BR-REV-11
BR-REV-12
BR-REV-14
BR-RO-01
BR-RO-02
BR-RO-03
BR-RO-04
BR-RO-05
BR-RO-06
BR-RO-07
BR-RPT-02
BR-RPT-03
BR-RPT-04
BR-RPT-05
BR-RPT-07
BR-RPT-08
BR-SC-01
BR-SC-02
BR-SC-03
BR-SCH-01
BR-SCH-02
BR-SCH-03
BR-SCH-04
BR-SCH-05
BR-SCH-07
BR-SCH-08
BR-SM-01
BR-SM-02
BR-SM-03
BR-SM-04
BR-SM-05
BR-SM-06
BR-SM-07
BR-SM-08
BR-SM-09
BR-SM-10
BR-SM-11
BR-SM-12
BR-SM-13
BR-SM-14
BR-SM-15
```

Verifikasi jumlahnya sebelum lanjut:

```bash
grep -vE '^\s*(#|$)' docs/requirement-analysis/bdd/uncovered.txt | wc -l
```
Expected: `92`.

- [ ] **Step 2: Tulis `non-behavioral.md`**

```markdown
# Business Rule yang Bukan Perilaku

Sembilan BR di bawah tidak bisa ditulis sebagai perubahan state yang teramati dari
sudut pandang aktor. Memaksakan Gherkin untuknya hanya menghasilkan noise yang
menyamarkan skenario asli. Masing-masing tetap ditegakkan — kolomnya menyebut oleh apa.

Kriteria dan alasan lengkap: `docs/design/bdd-gherkin-baseline.md` §6.

| BR | Isi | Ditegakkan oleh |
| -- | --- | --------------- |
| BR-FE-03 | `FormFieldResponse` hanya menyimpan scalar | Skema kolom + `ModelSchemaDriftTest` |
| BR-FE-07 | `FormPhaseDetail.submission_date_id` NOT NULL | Constraint database + test migrasi |
| BR-BUD-07 | Grand total selalu computed, tak ada kolom | `ModelSchemaDriftTest` |
| BR-REV-13 | `ReviewSummary.status` hanya `open`/`resolved` | Tipe enum + schema drift |
| BR-SCH-06 | Menambah rule ke JSONB tanpa migration | Sifat kolom JSONB — tidak ada yang bisa gagal |
| BR-SCH-09 | `scheme_selector` menulis ke kolom, bukan EAV | ADR 0005 + schema drift |
| BR-RPT-01 | Reporting read-only | Review arsitektur |
| BR-RPT-06 | Statistik live query, tanpa cache | Keputusan arsitektur |
| BR-NOT-05 | Unread count di-cache | Implementation detail |

## Memindahkan BR ke sini

Boleh, tapi wajib disertai alasan tertulis di baris tabelnya dan dibahas di PR.
Perpindahan ke arah sebaliknya — dari sini ke skenario — tidak butuh pembahasan.
```

- [ ] **Step 3: Tulis `open-questions.md`**

```markdown
# Register Kontradiksi & Pertanyaan Terbuka

Kontradiksi antara DDD, wireframe, dan kode legacy **tidak diputuskan sepihak** saat
menulis skenario. Ia dicatat di sini dengan opsi dan rekomendasi, diputuskan pemilik
produk, baru skenarionya mengunci keputusan itu.

## Terbuka

| # | Kontradiksi | Sumber | Memblokir |
| - | ----------- | ------ | --------- |
| 1 | `Gate::before` memberi Super Admin keempat ability `FormSubmissionPolicy` tanpa syarat, sementara `SubmitFormSubmission::run()` menegakkan BR-SM-04 sendiri | `SuperAdminSubmitDivergenceTest` mendokumentasikan tanpa menyetujui | Wave 1b |
| 2 | Auto-reject di kode warisan fork vs BR-REV-07/BR-REV-14 | ADR 0006 sudah memutuskan arah; skenario harus menguncinya | Wave 2 |
| 3 | Enum `SubmissionStatus` frontend 5 nilai vs state backend 8 nilai | Utang tercatat di CODING_STANDARDS | Wave 1b |
| 4 | BR-SM-01 ditegakkan unique index parsial — perilaku user-facing saat constraint violation belum ditetapkan | Tidak terdefinisi di sumber mana pun | Wave 1b |

## Terselesaikan

| # | Perkara | Keputusan | Tanggal |
| - | ------- | --------- | ------- |
| 5 | BR-BUD-06 menyebut "period config" yang tidak terdefinisi; entitas `SubmissionOuter` ternyata luaran yang dijanjikan, bukan data anggaran | Pindah ke BC Submission sebagai `SubmissionTargetOutput`; BR-BUD-06 dicabut, diganti BR-SM-14 dan BR-SM-15; pembatas tipe memakai `scheme.rules.allowed_output_types` yang sudah ada — tidak ada config baru. Target tetap bisa diubah pasca-APPROVED dengan audit trail wajib; hanya `submitted_by` yang boleh mengubah. Jejak lengkap: spec §8.1 | 2026-09-03 |
```

- [ ] **Step 4: Tulis `README.md`**

```markdown
# BDD — Skenario Bisnis SIMPAS v2

Spesifikasi perilaku sistem dalam format Gherkin. Berdampingan dengan `../ddd/`
(aturan domain) dan `../ux/` (bentuk antarmuka).

**Rancangan lengkap dan alasannya:** `docs/design/bdd-gherkin-baseline.md`.

## Aturan singkat

- Keyword Gherkin **English**, prosa **Bahasa Indonesia**, istilah domain mengikuti `../ddd/02_ubiquitous_language.md`.
- Skenario **deklaratif** — bicara aturan bisnis, bukan klik. Frame wireframe ikut sebagai tag, tidak sebagai langkah.
- Setiap skenario berjudul `[AREA-TOPIK-NN] Kalimat penjelas`. ID **permanen**: skenario yang dihapus tidak melepaskan nomornya untuk dipakai ulang.

## Tag wajib

| Tag | Kardinalitas |
| --- | ------------ |
| `@BR-XX-NN` | ≥ 1, dan BR-nya harus benar-benar terdefinisi sebagai baris tabel di `../ddd/` |
| `@M0`–`@M5` | tepat 1 |
| `@happy` / `@boundary` / `@denial` | tepat 1 |
| `@<FRAME-ID>` | 0..n |

## Menambah skenario

1. Tulis skenario di file `.feature` yang temanya cocok. Kalau file melewati ~25 skenario, temanya dua — pecah.
2. Tambahkan ID-nya ke `pending.txt` dan naikkan `# max:` di sana.
3. Kalau ini skenario pertama untuk sebuah BR, hapus BR itu dari `uncovered.txt` dan turunkan `# max:`-nya.
4. Jalankan `composer run quality`.

Saat test Pest-nya ditulis, sebutkan ID di deskripsi test:

```php
it('[FE-TEMPORAL-01] field yang lahir setelah submission tidak diwajibkan', function () {
```

lalu hapus ID itu dari `pending.txt` dan turunkan plafonnya.

## Daftar berplafon

`pending.txt` dan `uncovered.txt` adalah utang yang terlihat. Mesin menjaga plafonnya;
review PR menjaga arahnya **hanya turun** — pembagian kerja yang sama dengan
`excludePaths` di `phpstan-strict.neon`.
```

- [ ] **Step 5: Tulis test `LedgerFile` yang gagal**

```php
<?php
// tests/Unit/Bdd/LedgerFileTest.php

use App\Support\Bdd\LedgerFile;

function writeLedger(string $body): string
{
    $path = sys_get_temp_dir().'/ledger-'.uniqid().'.txt';
    file_put_contents($path, $body);

    return $path;
}

it('reads entries and ignores comments and blank lines', function () {
    $path = writeLedger("# catatan\n# max: 5\n\nBR-FE-01\nBR-FE-02\n");

    $ledger = new LedgerFile($path);

    expect($ledger->entries())->toBe(['BR-FE-01', 'BR-FE-02'])
        ->and($ledger->ceiling())->toBe(5);

    unlink($path);
});

it('treats a missing ceiling as zero', function () {
    $path = writeLedger("# tanpa plafon\nBR-FE-01\n");

    expect((new LedgerFile($path))->ceiling())->toBe(0);

    unlink($path);
});

it('throws when the file does not exist', function () {
    expect(fn () => (new LedgerFile('/tmp/tidak-ada-'.uniqid().'.txt'))->entries())
        ->toThrow(RuntimeException::class);
});
```

- [ ] **Step 6: Jalankan test, pastikan gagal**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/LedgerFileTest.php
```
Expected: FAIL — `Class "App\Support\Bdd\LedgerFile" not found`.

- [ ] **Step 7: Tulis `LedgerFile`**

```php
<?php

namespace App\Support\Bdd;

use RuntimeException;

/**
 * Daftar utang berplafon — pending.txt dan uncovered.txt.
 *
 * Formatnya sengaja sesederhana mungkin: baris '# max: N' menetapkan plafon,
 * baris komentar dan kosong diabaikan, sisanya entri.
 */
final class LedgerFile
{
    private const CEILING_PATTERN = '/^#\s*max:\s*(\d+)\s*$/m';

    public function __construct(private readonly string $path) {}

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        $lines = preg_split('/\R/', $this->contents());

        if ($lines === false) {
            throw new RuntimeException("Gagal memecah baris {$this->path}");
        }

        $entries = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $entries[] = $trimmed;
        }

        return $entries;
    }

    public function ceiling(): int
    {
        if (preg_match(self::CEILING_PATTERN, $this->contents(), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function contents(): string
    {
        // Guard dulu, bukan `@` — operator peredam tetap ditembus PHPUnit 11
        // dan warning-nya muncul di output test setiap kali dijalankan.
        if (!is_readable($this->path)) {
            throw new RuntimeException("Daftar tidak terbaca: {$this->path}");
        }

        $contents = file_get_contents($this->path);

        // Tetap diperiksa: berkas bisa hilang di antara pemeriksaan dan pembacaan.
        if ($contents === false) {
            throw new RuntimeException("Daftar tidak terbaca: {$this->path}");
        }

        return $contents;
    }
}
```

- [ ] **Step 8: Jalankan test, pastikan lulus**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/LedgerFileTest.php
```
Expected: PASS, 3 test.

- [ ] **Step 9: Tulis guard assertion 1–3**

```php
<?php
// tests/Unit/Bdd/BddCoverageTest.php

use App\Support\Bdd\BusinessRuleCatalog;
use App\Support\Bdd\FeatureCatalog;
use Tests\TestCase;

// Guard ini membaca berkas, bukan tabel — jadi ia meminta container (untuk
// base_path()) tapi TIDAK meminta RefreshDatabase. Itu sebabnya ia tinggal di
// tests/Unit: seluruh tests/Feature otomatis kena RefreshDatabase lewat
// tests/Pest.php, dan menyeret migrasi Postgres untuk test yang cuma membaca
// berkas hanya memperlambat `composer run quality`.
uses(TestCase::class);

/**
 * Guard spesifikasi BDD. Rancangan: docs/design/bdd-gherkin-baseline.md §5.
 */
function bddScenarios(): array
{
    return (new FeatureCatalog(base_path('docs/requirement-analysis/bdd')))->scenarios();
}

function bddRuleCodes(): array
{
    return (new BusinessRuleCatalog(base_path('docs/requirement-analysis/ddd')))->codes();
}

it('assertion 1 — setiap ID skenario unik', function () {
    $ids = array_map(fn ($s) => $s->id, bddScenarios());

    $duplicates = array_keys(array_filter(array_count_values($ids), fn (int $n) => $n > 1));

    expect($duplicates)->toBe([], 'ID skenario ganda: '.implode(', ', $duplicates));
});

it('assertion 2 — setiap skenario punya BR, milestone, dan kelas', function () {
    $problems = [];

    foreach (bddScenarios() as $scenario) {
        if ($scenario->businessRules === []) {
            $problems[] = "{$scenario->id} ({$scenario->location()}) tanpa tag @BR-";
        }

        if ($scenario->milestone === null) {
            $problems[] = "{$scenario->id} ({$scenario->location()}) tanpa tag milestone @M0-@M5";
        }

        if ($scenario->class === null) {
            $problems[] = "{$scenario->id} ({$scenario->location()}) tanpa @happy/@boundary/@denial";
        }
    }

    expect($problems)->toBe([], implode("\n", $problems));
});

it('assertion 3 — setiap tag @BR- merujuk rule yang benar-benar terdefinisi', function () {
    $known = bddRuleCodes();
    $problems = [];

    foreach (bddScenarios() as $scenario) {
        foreach ($scenario->businessRules as $code) {
            if (!in_array($code, $known, true)) {
                $problems[] = "{$scenario->id} ({$scenario->location()}) menyebut {$code} "
                    .'yang tidak ada sebagai baris tabel di docs/requirement-analysis/ddd/';
            }
        }
    }

    expect($problems)->toBe([], implode("\n", $problems));
});
```

- [ ] **Step 10: Jalankan guard, pastikan lulus pada katalog kosong**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 3 test. Belum ada `.feature`, jadi ketiganya lulus secara hampa — itu benar; Task 6 yang membuatnya berguna.

- [ ] **Step 11: Commit**

```bash
git add docs/requirement-analysis/bdd app/Support/Bdd/LedgerFile.php \
        tests/Unit/Bdd/BddCoverageTest.php tests/Unit/Bdd/LedgerFileTest.php
git commit -m "feat(bdd): scaffold bdd docs and guard assertions 1-3"
```

---

## Task 5: Guard assertion 4–8

**Files:**
- Modify: `tests/Unit/Bdd/BddCoverageTest.php`

**Interfaces:**
- Consumes: `LedgerFile::entries()`, `LedgerFile::ceiling()`, `FeatureCatalog::scenarios()`, `BusinessRuleCatalog::codes()`
- Produces: guard lengkap delapan assertion

Assertion 4 memindai `tests/**/*.php` mencari ID di dalam string. Ia sengaja tidak peduli test-nya lulus atau tidak — yang dijaga adalah **ada test yang mengklaim skenario itu**, bukan hasilnya; hasilnya dijaga Pest sendiri.

- [ ] **Step 1: Tulis test yang gagal**

Tambahkan ke `tests/Unit/Bdd/BddCoverageTest.php`:

```php
use App\Support\Bdd\LedgerFile;

function bddPending(): LedgerFile
{
    return new LedgerFile(base_path('docs/requirement-analysis/bdd/pending.txt'));
}

function bddUncovered(): LedgerFile
{
    return new LedgerFile(base_path('docs/requirement-analysis/bdd/uncovered.txt'));
}

function bddNonBehavioral(): array
{
    $contents = file_get_contents(base_path('docs/requirement-analysis/bdd/non-behavioral.md'));

    expect($contents)->not->toBeFalse();

    preg_match_all('/^\|\s*(BR-[A-Z]+-\d+)\s*\|/m', (string) $contents, $matches);

    return $matches[1];
}

/**
 * @return list<string> ID skenario yang disebut di dalam berkas test mana pun
 */
function bddIdsClaimedByTests(): array
{
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(base_path('tests'), FilesystemIterator::SKIP_DOTS)
    );

    $claimed = [];

    foreach ($iterator as $entry) {
        if (!$entry->isFile() || $entry->getExtension() !== 'php') {
            continue;
        }

        // Guard sendiri dikecualikan: ia menyebut ID sebagai data, bukan klaim.
        if (str_ends_with($entry->getPathname(), 'BddCoverageTest.php')) {
            continue;
        }

        preg_match_all('/\[([A-Z]+-[A-Z]+-\d+)\]/', (string) file_get_contents($entry->getPathname()), $m);

        foreach ($m[1] as $id) {
            $claimed[$id] = true;
        }
    }

    return array_keys($claimed);
}

it('assertion 4 — setiap skenario punya test, kecuali terdaftar pending', function () {
    $pending = bddPending()->entries();
    $claimed = bddIdsClaimedByTests();
    $problems = [];

    foreach (bddScenarios() as $scenario) {
        if (in_array($scenario->id, $claimed, true) || in_array($scenario->id, $pending, true)) {
            continue;
        }

        $problems[] = "{$scenario->id} ({$scenario->location()}) tidak punya test dan "
            .'tidak terdaftar di pending.txt';
    }

    expect($problems)->toBe([], implode("\n", $problems));
});

it('assertion 5 — pending.txt tidak memuat skenario yang sudah diimplementasi', function () {
    $claimed = bddIdsClaimedByTests();

    $stale = array_values(array_intersect(bddPending()->entries(), $claimed));

    expect($stale)->toBe([], 'Sudah ada test-nya, hapus dari pending.txt: '.implode(', ', $stale));
});

it('assertion 6 — pending.txt tidak memuat ID yang tidak ada di feature manapun', function () {
    $known = array_map(fn ($s) => $s->id, bddScenarios());

    $orphans = array_values(array_diff(bddPending()->entries(), $known));

    expect($orphans)->toBe([], 'ID tidak ada di .feature manapun: '.implode(', ', $orphans));
});

it('assertion 7 — setiap BR tercakup skenario, non-behavioral, atau uncovered', function () {
    $covered = [];

    foreach (bddScenarios() as $scenario) {
        foreach ($scenario->businessRules as $code) {
            $covered[$code] = true;
        }
    }

    $accounted = array_merge(array_keys($covered), bddNonBehavioral(), bddUncovered()->entries());

    $orphans = array_values(array_diff(bddRuleCodes(), $accounted));

    expect($orphans)->toBe(
        [],
        'BR tanpa skenario dan tidak dinyatakan di manapun: '.implode(', ', $orphans)
            ."\nTulis skenarionya, atau daftarkan di uncovered.txt / non-behavioral.md."
    );
});

it('assertion 8 — kedua daftar utang tidak melewati plafonnya', function () {
    expect(count(bddPending()->entries()))->toBeLessThanOrEqual(
        bddPending()->ceiling(),
        'pending.txt melewati plafon — naikkan "# max:" secara sadar, atau kurangi utangnya'
    );

    expect(count(bddUncovered()->entries()))->toBeLessThanOrEqual(
        bddUncovered()->ceiling(),
        'uncovered.txt melewati plafon'
    );
});
```

Tambahkan `use` berikut di bagian atas berkas:

```php
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
```

- [ ] **Step 2: Jalankan guard, pastikan lulus**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test. Assertion 7 lulus karena 92 BR ada di `uncovered.txt` dan 9 di `non-behavioral.md` — total 101, sama dengan katalog.

- [ ] **Step 3: Buktikan guard benar-benar menggigit**

Sengaja rusak `uncovered.txt` untuk memastikan assertion 7 bukan lulus semu:

```bash
sed -i '/^BR-FE-11$/d' docs/requirement-analysis/bdd/uncovered.txt
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: **FAIL** pada assertion 7 dengan pesan menyebut `BR-FE-11`.

Kembalikan:

```bash
git checkout docs/requirement-analysis/bdd/uncovered.txt
```

Kalau langkah ini **tidak** gagal, guard-nya tidak berfungsi — hentikan dan perbaiki sebelum lanjut. Guard yang lulus tanpa memeriksa apa pun lebih berbahaya daripada tidak ada guard.

- [ ] **Step 4: Quality gate penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  -e COMPOSER_HOME=/tmp/composer simpas-php84:local composer run quality
```
Expected: Pint, PHPStan level 5, PHPStan level 9, dan seluruh Pest hijau.

- [ ] **Step 5: Commit**

```bash
git add tests/Unit/Bdd/BddCoverageTest.php
git commit -m "feat(bdd): add coverage assertions 4-8 with ledger ceilings"
```

---

## Task 6: Wave 1a — `temporal_binding.feature` (BR-FE-11)

**Files:**
- Create: `docs/requirement-analysis/bdd/generic/form_engine/temporal_binding.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Consumes: guard delapan assertion dari Task 5
- Produces: ID `FE-TEMPORAL-01` … `FE-TEMPORAL-09`

Temporal Field Binding adalah aturan paling halus di Form Engine dan paling mahal kalau salah: ia menentukan apakah perubahan form memblokir draft yang sudah berjalan. Rujukan: `generic/01_form_engine.md` bagian "Temporal Field Binding" dan `isRequiredFor()`.

Dua sumbu yang harus dibedakan skenario — kapan **field lahir** (`created_at`) dan kapan field **jadi wajib** (`required_since`) — masing-masing relatif terhadap `submission.created_at`.

- [ ] **Step 1: Tulis file feature**

```gherkin
Feature: Temporal Field Binding
  Perubahan Form tidak boleh memblokir draft yang sudah berjalan. Sebuah field
  hanya diwajibkan untuk sebuah submission kalau field itu sudah ada DAN sudah
  berstatus wajib sebelum submission tersebut dibuat.

  Baris form_submissions lahir di langkah 1 wizard justru supaya jangkar waktu
  ini benar — lihat ADR 0012.

  Background:
    Given Form "Pengajuan Penelitian" aktif di periode berjalan

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-01] Field wajib yang lahir sebelum submission tetap diwajibkan
    Given FormField "Abstrak" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Abstrak"
    Then submit ditolak dengan error validasi pada field "Abstrak"

  @BR-FE-11 @M0 @denial
  Scenario: [FE-TEMPORAL-02] Field yang lahir setelah submission tidak diwajibkan
    Given submission "P-001" dibuat pada "2026-02-01"
    And FormField "Roadmap" dibuat pada "2026-03-01" dengan is_required true
    When Lead Researcher submit "P-001" tanpa mengisi "Roadmap"
    Then submit berhasil
    And status "P-001" menjadi SUBMITTED

  @BR-FE-11 @M0 @denial
  Scenario: [FE-TEMPORAL-03] Field yang baru jadi wajib setelah submission tidak diwajibkan
    Given FormField "Mitra" dibuat pada "2026-01-10" dengan is_required false
    And submission "P-001" dibuat pada "2026-02-01"
    And "Mitra" diubah menjadi wajib pada "2026-03-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Mitra"
    Then submit berhasil

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-04] Field yang jadi wajib tepat sebelum submission diwajibkan
    Given FormField "Mitra" dibuat pada "2026-01-10" dengan is_required false
    And "Mitra" diubah menjadi wajib pada "2026-01-20"
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Mitra"
    Then submit ditolak dengan error validasi pada field "Mitra"

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-05] required_since kosong berarti mengikuti is_required apa adanya
    Given FormField "Judul" dibuat pada "2026-01-10" dengan is_required true dan required_since kosong
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Judul"
    Then submit ditolak dengan error validasi pada field "Judul"

  @BR-FE-11 @M0 @denial
  Scenario: [FE-TEMPORAL-06] Field opsional tidak pernah diwajibkan
    Given FormField "Catatan" dibuat pada "2026-01-10" dengan is_required false
    And submission "P-001" dibuat pada "2026-02-01"
    When Lead Researcher submit "P-001" tanpa mengisi "Catatan"
    Then submit berhasil

  @BR-FE-11 @M0 @boundary
  Scenario: [FE-TEMPORAL-07] Field yang lahir tepat bersamaan dengan submission diwajibkan
    Given FormField "Luaran" dibuat pada "2026-02-01 09:00" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01 09:00"
    When Lead Researcher submit "P-001" tanpa mengisi "Luaran"
    Then submit ditolak dengan error validasi pada field "Luaran"

  @BR-FE-11 @BR-FE-12 @M0 @denial
  Scenario: [FE-TEMPORAL-08] Field yang sudah di-soft-delete tidak diwajibkan
    Given FormField "Lampiran Lama" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01"
    And "Lampiran Lama" dihapus pada "2026-02-15"
    When Lead Researcher submit "P-001" tanpa mengisi "Lampiran Lama"
    Then submit berhasil

  @BR-FE-11 @M0 @happy
  Scenario: [FE-TEMPORAL-09] Validasi penuh hanya berjalan di langkah tujuh wizard
    Given FormField "Abstrak" dibuat pada "2026-01-10" dengan is_required true
    And submission "P-001" dibuat pada "2026-02-01" dan berada di langkah 3
    When Lead Researcher menyimpan langkah 3 tanpa mengisi "Abstrak"
    Then penyimpanan berhasil
    And status "P-001" tetap DRAFT
```

- [ ] **Step 2: Daftarkan sembilan ID ke `pending.txt`**

Ubah header plafon menjadi `# max: 9` dan tambahkan:

```
FE-TEMPORAL-01
FE-TEMPORAL-02
FE-TEMPORAL-03
FE-TEMPORAL-04
FE-TEMPORAL-05
FE-TEMPORAL-06
FE-TEMPORAL-07
FE-TEMPORAL-08
FE-TEMPORAL-09
```

- [ ] **Step 3: Keluarkan BR-FE-11 dan BR-FE-12 dari `uncovered.txt`**

Hapus baris `BR-FE-11` dan `BR-FE-12`, lalu turunkan plafon dari `# max: 92` menjadi `# max: 90`.

- [ ] **Step 4: Jalankan guard**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test.

Kalau assertion 8 gagal, plafon di Step 2 atau Step 3 belum disesuaikan. Kalau assertion 3 gagal, ada typo di tag `@BR-`.

- [ ] **Step 5: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add temporal field binding scenarios (BR-FE-11)"
```

---

## Task 7: Wave 1a — `access_control.feature` (BR-FE-01, BR-FE-02)

**Files:**
- Create: `docs/requirement-analysis/bdd/generic/form_engine/access_control.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `FE-ACCESS-01` … `FE-ACCESS-08`

`FormAccessControl` mencocokkan **permission** (bukan role) dengan **org subtree**. Rujukan: `generic/02_identity_access.md` bagian "Access Check — Permission + Org", termasuk recursive CTE-nya. Ingat BR-SM-07: lolos di sini **belum** berarti boleh melihat submission spesifik — itu lapis kedua, dan skenarionya ada di Wave 1b.

- [ ] **Step 1: Tulis file feature**

```gherkin
Feature: Akses Form — Permission dan Organisasi
  Sebuah Form terbuka bagi user kalau ada FormAccessControl yang permission-nya
  dimiliki user DAN organization_id-nya berada di subtree organisasi user.
  Keduanya harus terpenuhi; salah satu saja tidak cukup.

  Background:
    Given Organization "ITK" membawahi "FTI", dan "FTI" membawahi "Informatika"
    And Form "Pengajuan Penelitian" punya FormAccessControl permission "submissions.create" pada organisasi "FTI"

  @BR-FE-02 @M0 @happy
  Scenario: [FE-ACCESS-01] User dengan permission dan organisasi yang cocok bisa akses
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    When "Budi" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-02 @M0 @happy
  Scenario: [FE-ACCESS-02] Organisasi anak ikut terjangkau lewat subtree
    Given "Sari" berorganisasi "Informatika" dan punya permission "submissions.create"
    When "Sari" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-03] Organisasi induk tidak ikut terjangkau
    Given "Rina" berorganisasi "ITK" dan punya permission "submissions.create"
    When "Rina" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-04] Permission cocok tapi organisasi di luar subtree
    Given Organization "FSAINS" berada di bawah "ITK"
    And "Andi" berorganisasi "FSAINS" dan punya permission "submissions.create"
    When "Andi" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @M0 @denial
  Scenario: [FE-ACCESS-05] Organisasi cocok tapi permission tidak dimiliki
    Given "Maya" berorganisasi "FTI" tanpa permission "submissions.create"
    When "Maya" membuka daftar Form
    Then "Pengajuan Penelitian" tidak muncul di daftar

  @BR-FE-02 @BR-IAM-09 @M0 @happy
  Scenario: [FE-ACCESS-06] Direct permission memberi akses tanpa lewat role
    Given "Hendra" berorganisasi "FTI" tanpa role apa pun
    And "Hendra" diberi direct permission "submissions.create"
    When "Hendra" membuka daftar Form
    Then "Pengajuan Penelitian" muncul di daftar

  @BR-FE-01 @M0 @denial
  Scenario: [FE-ACCESS-07] Submission tidak bisa dibuat saat periode force closed
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    And periode berjalan berstatus is_force_closed
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then pembuatan ditolak karena periode sudah ditutup

  @BR-FE-01 @M0 @happy
  Scenario: [FE-ACCESS-08] Submission bisa dibuat saat periode aktif
    Given "Budi" berorganisasi "FTI" dan punya permission "submissions.create"
    And periode berjalan aktif dan belum force closed
    When "Budi" memulai pengajuan baru pada "Pengajuan Penelitian"
    Then submission DRAFT terbentuk pada langkah 1
```

- [ ] **Step 2: Perbarui daftar**

Di `pending.txt`: ubah plafon menjadi `# max: 17` dan tambahkan `FE-ACCESS-01` sampai `FE-ACCESS-08`.

Di `uncovered.txt`: hapus `BR-FE-01` dan `BR-FE-02`, turunkan plafon menjadi `# max: 88`.

`BR-IAM-09` **tetap** di `uncovered.txt` — ia disinggung di `[FE-ACCESS-06]` tapi aturan utuhnya ditulis di Wave 3.

- [ ] **Step 3: Jalankan guard**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test.

- [ ] **Step 4: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add form access control scenarios (BR-FE-01, BR-FE-02)"
```

---

## Task 8: Wave 1a — `deadline_override.feature` (BR-FE-08, 09, 10, 15)

**Files:**
- Create: `docs/requirement-analysis/bdd/generic/form_engine/deadline_override.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `FE-OVERRIDE-01` … `FE-OVERRIDE-11`

Poin yang mudah terlewat: researcher boleh melihat **status** override, tapi **tidak** boleh melihat `reason`-nya. Rujukan: `generic/01_form_engine.md` bagian "FormSubmissionOverride — Visibility ke Researcher".

- [ ] **Step 1: Tulis file feature**

```gherkin
Feature: Deadline dan Override
  Lewat deadline, akses ke FormPhaseDetail ditutup keras kecuali ada
  FormSubmissionOverride yang masih berlaku. Fase yang sudah lewat tetap
  terlihat sebagai read-only supaya peneliti bisa membaca apa yang ia kirim.

  Background:
    Given FormPhaseDetail "Pengajuan" punya deadline "2026-03-01 23:59"
    And submission "P-001" milik "Budi" berada di fase tersebut

  @BR-FE-08 @M0 @happy
  Scenario: [FE-OVERRIDE-01] Akses terbuka sebelum deadline
    Given waktu sekarang "2026-02-20 10:00"
    When "Budi" membuka fase "Pengajuan"
    Then fase bisa diisi

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-02] Akses ditutup setelah deadline tanpa override
    Given waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403
    And pesan menyebut cara menghubungi Operator

  @BR-FE-08 @BR-FE-10 @M0 @happy
  Scenario: [FE-OVERRIDE-03] Override aktif membuka kembali akses
    Given waktu sekarang "2026-03-02 00:01"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    When "Budi" membuka fase "Pengajuan"
    Then fase bisa diisi

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-04] Override yang sudah kedaluwarsa tidak membuka akses
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-05"
    And waktu sekarang "2026-03-06 08:00"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-08 @M0 @denial
  Scenario: [FE-OVERRIDE-05] Override yang dicabut tidak membuka akses
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    And Operator mencabut override tersebut
    And waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-09 @M0 @denial
  Scenario: [FE-OVERRIDE-06] Override tanpa alasan ditolak
    When Operator membuat override untuk "P-001" pada fase "Pengajuan" dengan alasan kosong
    Then pembuatan ditolak dengan error validasi pada field alasan

  @BR-FE-10 @M0 @denial
  Scenario: [FE-OVERRIDE-07] Override tidak berlaku untuk fase lain
    Given FormPhaseDetail "Laporan Monev I" punya deadline "2026-06-01 23:59"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-07-01"
    And waktu sekarang "2026-06-02 08:00"
    When "Budi" membuka fase "Laporan Monev I"
    Then akses ditolak dengan 403

  @BR-FE-10 @M0 @denial
  Scenario: [FE-OVERRIDE-08] Override tidak berlaku untuk submission lain
    Given submission "P-002" milik "Sari" berada di fase "Pengajuan"
    And Operator membuat override untuk "P-001" pada fase "Pengajuan" yang berlaku sampai "2026-03-10"
    And waktu sekarang "2026-03-02 00:01"
    When "Sari" membuka fase "Pengajuan"
    Then akses ditolak dengan 403

  @BR-FE-09 @M0 @denial
  Scenario: [FE-OVERRIDE-09] Researcher tidak bisa melihat alasan override
    Given Operator membuat override untuk "P-001" pada fase "Pengajuan" dengan alasan "Kendala jaringan kampus"
    When "Budi" membuka detail "P-001"
    Then "Budi" melihat status override "Aktif"
    And "Budi" tidak melihat teks "Kendala jaringan kampus"

  @BR-FE-15 @M0 @happy
  Scenario: [FE-OVERRIDE-10] Fase yang lewat deadline tetap terlihat read-only
    Given waktu sekarang "2026-03-02 00:01"
    When "Budi" membuka detail "P-001"
    Then fase "Pengajuan" terlihat dengan jawaban yang sudah dikirim
    And fase "Pengajuan" tidak bisa diubah

  @BR-SCH-07 @M0 @denial
  Scenario: [FE-OVERRIDE-11] Periode force closed menutup semua fase meski deadline belum lewat
    Given waktu sekarang "2026-02-20 10:00"
    And periode berjalan berstatus is_force_closed
    When "Budi" membuka fase "Pengajuan"
    Then akses ditolak dengan 403
```

- [ ] **Step 2: Perbarui daftar**

Di `pending.txt`: plafon `# max: 28`, tambahkan `FE-OVERRIDE-01` sampai `FE-OVERRIDE-11`.

Di `uncovered.txt`: hapus `BR-FE-08`, `BR-FE-09`, `BR-FE-10`, `BR-FE-15`, `BR-SCH-07`; turunkan plafon menjadi `# max: 83`.

- [ ] **Step 3: Jalankan guard**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test.

- [ ] **Step 4: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add deadline and override scenarios (BR-FE-08/09/10/15)"
```

---

## Task 9: Wave 1a — perubahan form, concurrency, dan gating anak

**Files:**
- Create: `docs/requirement-analysis/bdd/generic/form_engine/form_changes.feature`
- Create: `docs/requirement-analysis/bdd/generic/form_engine/concurrency.feature`
- Create: `docs/requirement-analysis/bdd/generic/form_engine/submission_gating.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `FE-CHANGE-01`…`FE-CHANGE-04`, `FE-LOCK-01`…`FE-LOCK-04`, `FE-CHILD-01`…`FE-CHILD-03`

Tiga file kecil digabung dalam satu task karena masing-masing hanya menutup satu sampai dua BR dan tidak ada gunanya dipisah jadi tiga siklus review.

- [ ] **Step 1: Tulis `form_changes.feature`**

```gherkin
Feature: Perubahan Form dengan Draft Berjalan
  FormField tidak pernah dihapus permanen, dan operator diberi tahu berapa draft
  yang terdampak sebelum perubahan disebarkan.

  @BR-FE-12 @M0 @happy
  Scenario: [FE-CHANGE-01] Menghapus field menyisakan jawaban lama
    Given FormField "Lampiran Lama" punya jawaban di submission "P-001"
    When Admin menghapus FormField "Lampiran Lama"
    Then "Lampiran Lama" ditandai terhapus tanpa dihilangkan dari basis data
    And jawaban "P-001" untuk field itu tetap terbaca di tampilan read-only

  @BR-FE-13 @M0 @happy
  Scenario: [FE-CHANGE-02] Admin diberi tahu jumlah draft terdampak
    Given Form "Pengajuan Penelitian" punya 12 submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then Admin melihat peringatan bahwa 12 draft terdampak
    And Admin ditawari mengirim notifikasi ke peneliti terdampak

  @BR-FE-13 @M0 @happy
  Scenario: [FE-CHANGE-03] Admin memilih mengirim notifikasi
    Given Admin mengubah FormField "Mitra" menjadi wajib pada Form dengan 12 draft
    When Admin memilih mengirim notifikasi
    Then 12 peneliti pemilik draft menerima notifikasi perubahan form

  @BR-FE-13 @M0 @boundary
  Scenario: [FE-CHANGE-04] Tanpa draft berjalan, tidak ada peringatan
    Given Form "Pengajuan Penelitian" tidak punya submission berstatus DRAFT
    When Admin mengubah FormField "Mitra" menjadi wajib
    Then perubahan tersimpan tanpa peringatan draft terdampak
```

- [ ] **Step 2: Tulis `concurrency.feature`**

```gherkin
Feature: Optimistic Locking
  Dua orang yang menyunting submission yang sama tidak boleh saling menimpa
  diam-diam. Penyimpanan membawa updated_at yang dilihat penyunting; kalau
  nilainya sudah bergeser di basis data, penyimpanan ditolak.

  Background:
    Given submission "P-001" terakhir diubah pada "2026-02-01 10:00"

  @BR-FE-14 @M0 @happy
  Scenario: [FE-LOCK-01] Penyimpanan lolos saat tidak ada yang mendahului
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan berhasil

  @BR-FE-14 @M0 @denial
  Scenario: [FE-LOCK-02] Penyimpanan ditolak saat sudah didahului orang lain
    Given Operator mengubah "P-001" pada "2026-02-01 10:05"
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan ditolak dengan 409
    And pesan meminta memuat ulang halaman

  @BR-FE-14 @M0 @denial
  Scenario: [FE-LOCK-03] Penyimpanan tanpa last_updated_at ditolak
    When Lead Researcher menyimpan "P-001" tanpa menyertakan last_updated_at
    Then penyimpanan ditolak dengan error validasi

  @BR-FE-14 @M0 @happy
  Scenario: [FE-LOCK-04] Penolakan tidak mengubah data
    Given Operator mengubah judul "P-001" menjadi "Judul Operator" pada "2026-02-01 10:05"
    When Lead Researcher menyimpan "P-001" dengan last_updated_at "2026-02-01 10:00"
    Then penyimpanan ditolak dengan 409
    And judul "P-001" tetap "Judul Operator"
```

- [ ] **Step 3: Tulis `submission_gating.feature`**

```gherkin
Feature: Gating Child Submission
  Laporan monev dan kelengkapan berkas adalah child FormSubmission, dan hanya
  boleh lahir dari induk yang sudah disetujui.

  @BR-FE-04 @M0 @denial
  Scenario Outline: [FE-CHILD-01] Child tidak bisa dibuat dari induk yang belum disetujui
    Given submission "P-001" berstatus <status>
    When Lead Researcher membuat laporan monev untuk "P-001"
    Then pembuatan ditolak karena induk belum APPROVED

    Examples:
      | status         |
      | DRAFT          |
      | SUBMITTED      |
      | UNDER_REVIEW   |
      | NEEDS_REVISION |
      | RESUBMITTED    |
      | REJECTED       |

  @BR-FE-04 @M0 @happy
  Scenario: [FE-CHILD-02] Child bisa dibuat dari induk yang sudah disetujui
    Given submission "P-001" berstatus APPROVED
    When Lead Researcher membuat laporan monev untuk "P-001"
    Then child FormSubmission terbentuk dengan parent_submission_id "P-001"

  @BR-FE-04 @M0 @boundary
  Scenario: [FE-CHILD-03] Child tetap ada setelah induk ditarik
    Given submission "P-001" berstatus APPROVED dengan satu laporan monev
    When Operator menarik "P-001" menjadi WITHDRAWN
    Then laporan monev tersebut tetap ada sebagai data historis
```

- [ ] **Step 4: Perbarui daftar**

Di `pending.txt`: plafon `# max: 39`, tambahkan `FE-CHANGE-01`…`04`, `FE-LOCK-01`…`04`, `FE-CHILD-01`…`03`.

Di `uncovered.txt`: hapus `BR-FE-04`, `BR-FE-13`, `BR-FE-14`; turunkan plafon menjadi `# max: 80`. (`BR-FE-12` sudah dikeluarkan di Task 6.)

- [ ] **Step 5: Jalankan guard**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test.

- [ ] **Step 6: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add form change, locking and child gating scenarios"
```

---

## Task 10: Wave 1a — `scheme.feature` (7 BR Scheme)

**Files:**
- Create: `docs/requirement-analysis/bdd/supporting/scheme.feature`
- Modify: `docs/requirement-analysis/bdd/pending.txt`
- Modify: `docs/requirement-analysis/bdd/uncovered.txt`

**Interfaces:**
- Produces: ID `SCH-RULE-01` … `SCH-RULE-14`

Cakupan: BR-SCH-01, 02, 03, 04, 05, 08. BR-SCH-07 sudah tertutup `[FE-OVERRIDE-11]` di Task 8, jadi tidak diulang. BR-SCH-06 dan BR-SCH-09 ada di `non-behavioral.md`.

- [ ] **Step 1: Tulis file feature**

```gherkin
Feature: Skema Penelitian
  Skema membawa aturan yang mengikat pengajuan: batas anggaran, batas anggota,
  dan rule opsional di kolom JSONB. Skema sepenuhnya opsional per Form.

  @BR-SCH-03 @M0 @denial
  Scenario: [SCH-RULE-01] Kode skema harus unik
    Given sudah ada skema dengan code "DIPA-2026"
    When Admin membuat skema baru dengan code "DIPA-2026"
    Then pembuatan ditolak dengan error validasi pada field code

  @BR-SCH-02 @M0 @denial
  Scenario Outline: [SCH-RULE-02] max_budget dan max_members harus lebih dari nol
    When Admin membuat skema dengan max_budget <budget> dan max_members <members>
    Then pembuatan ditolak dengan error validasi

    Examples:
      | budget | members |
      | 0      | 5       |
      | -1     | 5       |
      | 100000 | 0       |
      | 100000 | -2      |

  @BR-SCH-02 @M0 @happy
  Scenario: [SCH-RULE-03] Skema dengan nilai sah tersimpan
    When Admin membuat skema dengan max_budget 50000000 dan max_members 5
    Then skema tersimpan dan aktif

  @BR-SCH-01 @M0 @denial
  Scenario: [SCH-RULE-04] Skema yang sedang dipakai tidak bisa dinonaktifkan
    Given skema "DIPA Internal" dipakai submission "P-001" yang masih aktif
    When Admin menonaktifkan skema "DIPA Internal"
    Then penonaktifan ditolak karena masih ada pengajuan aktif

  @BR-SCH-01 @M0 @happy
  Scenario: [SCH-RULE-05] Skema tanpa pengajuan aktif bisa dinonaktifkan
    Given skema "DIPA Lama" tidak dipakai submission aktif mana pun
    When Admin menonaktifkan skema "DIPA Lama"
    Then skema "DIPA Lama" menjadi tidak aktif

  @BR-SCH-01 @M0 @boundary
  Scenario: [SCH-RULE-06] Pengajuan yang sudah selesai tidak menghalangi penonaktifan
    Given skema "DIPA Lama" hanya dipakai submission berstatus APPROVED dan REJECTED
    When Admin menonaktifkan skema "DIPA Lama"
    Then skema "DIPA Lama" menjadi tidak aktif

  @BR-SCH-04 @M0 @happy
  Scenario: [SCH-RULE-07] Form tanpa scheme_selector berjalan tanpa skema
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    When Lead Researcher membuat pengajuan pada Form itu
    Then submission terbentuk dengan scheme_id kosong

  @BR-SCH-04 @M0 @happy
  Scenario: [SCH-RULE-08] Form dengan scheme_selector menulis ke kolom scheme_id
    Given Form "Pengajuan Penelitian" punya field bertipe scheme_selector
    When Lead Researcher memilih skema "DIPA Internal" di langkah 3
    Then scheme_id submission terisi skema "DIPA Internal"

  @BR-SCH-05 @M0 @denial
  Scenario: [SCH-RULE-09] trl_selector tanpa scheme_selector ditolak
    Given Form "Pengajuan Sederhana" tidak punya field bertipe scheme_selector
    When Admin menambahkan field bertipe trl_selector ke Form itu
    Then penambahan ditolak karena trl_selector butuh scheme_selector di Form yang sama

  @BR-SCH-05 @M0 @happy
  Scenario: [SCH-RULE-10] Pilihan TRL tersaring oleh skema terpilih
    Given skema "DIPA Internal" mengizinkan TRL 3, 4, dan 5
    And Lead Researcher sudah memilih skema "DIPA Internal"
    When Lead Researcher membuka pilihan TRL
    Then hanya TRL 3, 4, dan 5 yang tersedia

  @BR-SCH-08 @M0 @happy
  Scenario: [SCH-RULE-11] Perpanjangan deadline memberi tahu pemilik draft
    Given 7 peneliti punya submission DRAFT di periode berjalan
    When Operator memperpanjang SubmissionDate "Batas Submit" ke "2026-04-01"
    Then 7 peneliti tersebut menerima notifikasi perpanjangan

  @BR-SCH-08 @M0 @happy
  Scenario: [SCH-RULE-12] Deadline baru langsung berlaku sebagai gerbang akses
    Given deadline "Batas Submit" semula "2026-03-01 23:59"
    And waktu sekarang "2026-03-05 08:00"
    When Operator memperpanjang "Batas Submit" ke "2026-04-01 23:59"
    Then Lead Researcher bisa kembali mengisi fase yang terkait

  @BR-SCH-08 @M0 @boundary
  Scenario: [SCH-RULE-13] Tanpa draft berjalan, tidak ada notifikasi terkirim
    Given tidak ada submission DRAFT di periode berjalan
    When Operator memperpanjang SubmissionDate "Batas Submit" ke "2026-04-01"
    Then tidak ada notifikasi perpanjangan yang terkirim

  @BR-SCH-01 @M0 @denial
  Scenario: [SCH-RULE-14] Skema tidak aktif tidak bisa dipilih pengajuan baru
    Given skema "DIPA Lama" berstatus tidak aktif
    When Lead Researcher membuka pilihan skema di langkah 3
    Then "DIPA Lama" tidak muncul sebagai pilihan
```

- [ ] **Step 2: Perbarui daftar**

Di `pending.txt`: plafon `# max: 53`, tambahkan `SCH-RULE-01` sampai `SCH-RULE-14`.

Di `uncovered.txt`: hapus `BR-SCH-01`, `BR-SCH-02`, `BR-SCH-03`, `BR-SCH-04`, `BR-SCH-05`, `BR-SCH-08`; turunkan plafon menjadi `# max: 74`.

- [ ] **Step 3: Jalankan guard**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  simpas-php84:local ./vendor/bin/pest tests/Unit/Bdd/BddCoverageTest.php
```
Expected: PASS, 8 test.

- [ ] **Step 4: Commit**

```bash
git add docs/requirement-analysis/bdd
git commit -m "spec(bdd): add scheme rule scenarios (BR-SCH-01..05, 08)"
```

---

## Task 11: Definisi selesai Wave 1a

**Files:**
- Modify: `AGENTS.md`
- Modify: `docs/design/bdd-gherkin-baseline.md`

**Interfaces:**
- Consumes: seluruh keluaran Task 1–10

- [ ] **Step 1: Verifikasi cakupan Wave 1a**

```bash
grep -vE '^\s*(#|$)' docs/requirement-analysis/bdd/uncovered.txt | wc -l
grep -vE '^\s*(#|$)' docs/requirement-analysis/bdd/pending.txt | wc -l
```
Expected: `74`, lalu `53`.

Artinya 18 BR sudah keluar dari `uncovered.txt` (92 − 74) dan 53 skenario tertulis.

- [ ] **Step 2: Verifikasi tidak ada BR Form Engine/Scheme yang tertinggal**

```bash
grep -E '^BR-(FE|SCH)-' docs/requirement-analysis/bdd/uncovered.txt
```
Expected: hanya `BR-FE-05` dan `BR-FE-06` — keduanya sengaja dipindah ke Wave 2 (lihat "Penyesuaian terhadap spec"). Kalau ada baris lain, ada BR yang terlewat.

- [ ] **Step 3: Quality gate penuh**

Run:
```bash
docker run --rm --network host -v "$PWD":/app -w /app -u "$(id -u):$(id -g)" \
  -e DB_HOST=127.0.0.1 -e DB_PORT=55432 -e DB_PASSWORD=postgres \
  -e COMPOSER_HOME=/tmp/composer simpas-php84:local composer run quality
```
Expected: Pint, PHPStan level 5, PHPStan level 9, seluruh Pest hijau.

- [ ] **Step 4: Arahkan agent ke `bdd/` lewat `AGENTS.md`**

Di `AGENTS.md`, pada bagian **Stack**, setelah kalimat yang menyuruh membaca `docs/requirement-analysis/ddd/`, tambahkan paragraf:

```markdown
Skenario perilaku yang mengikat ada di
[`docs/requirement-analysis/bdd/`](docs/requirement-analysis/bdd/). Kalau kamu
mengerjakan sebuah business rule, **cari dulu skenarionya di sana** — itu yang
menetapkan perilaku yang teramati, sementara DDD menetapkan aturannya. Saat kamu
mengimplementasikan sebuah skenario, sebut ID-nya di deskripsi test
(`it('[FE-TEMPORAL-01] ...')`) lalu hapus ID itu dari `pending.txt` dan turunkan
plafonnya. `pending.txt` dan `uncovered.txt` **hanya boleh menyusut**.
```

- [ ] **Step 5: Catat `uncovered.txt` dan lokasi guard ke dalam spec**

Di `docs/design/bdd-gherkin-baseline.md` §5, ganti kalimat pembuka yang menyebut
`tests/Feature/BddCoverageTest.php` menjadi:

```markdown
`tests/Unit/Bdd/BddCoverageTest.php`. Berjalan otomatis di `composer run quality` —
tanpa runner Gherkin, tanpa step CI baru, tanpa perubahan gate yang sudah ada.
Ia tinggal di `tests/Unit` dan bukan `tests/Feature` karena `tests/Pest.php`
memasang `RefreshDatabase` ke seluruh `Feature`; guard ini membaca berkas, bukan
tabel, sehingga tidak ada alasan menyeret migrasi Postgres setiap kali ia jalan.
```

Lalu pada tabel delapan assertion, ubah baris assertion 7 menjadi:

```
| 7 | **Setiap BR di DDD tercakup ≥1 skenario, atau terdaftar di `non-behavioral.md`, atau di `uncovered.txt`** | BR baru ditambah ke DDD tanpa skenario |
```

Lalu tambahkan tepat setelah tabel:

```markdown
`uncovered.txt` adalah kelonggaran sementara untuk BR yang wave-nya belum tiba —
berplafon dan menyusut dari 92 ke 0. Tanpanya assertion 7 baru bisa dinyalakan
setelah wave terakhir, dan assertion yang menganggur berbulan-bulan sama saja
dengan tidak ada.
```

- [ ] **Step 6: Commit penutup**

```bash
git add AGENTS.md docs/design/bdd-gherkin-baseline.md
git commit -m "docs: point agents at bdd scenarios and record uncovered ledger"
```

---

## Self-Review

**Cakupan spec.** Setiap bagian spec punya task: struktur artefak §3 (T4), konvensi ID & tag §4 (T4 README + dipakai T6–T10), guard delapan assertion §5 (T4 assertion 1–3, T5 assertion 4–8), triase BR §6 (T4 `non-behavioral.md`), kedalaman & wave §7 (T6–T10), register kontradiksi §8 (T4 `open-questions.md`), koreksi DDD §8.1 (T1), DoD per wave §9 (T11). Dependency `behat/gherkin` §5 dipasang di T3.

**Penyimpangan dari spec yang disengaja.** Tiga, semuanya dijelaskan di bagian "Penyesuaian terhadap spec" dan dicatat balik ke spec di T11 Step 5: `uncovered.txt` ditambahkan supaya assertion 7 bisa hidup sejak hari pertama; BR-FE-05/BR-FE-06 dipindah ke Wave 2 supaya tidak ditulis terpisah dari rule Review yang mengunci dengannya; dan guard pindah ke `tests/Unit/Bdd/` karena `tests/Pest.php` memasang `RefreshDatabase` ke seluruh `tests/Feature`. Cakupan plan juga sengaja berhenti di Wave 1a — alasannya diambil dari argumen spec §7 sendiri.

**Lingkungan test sudah diperiksa, bukan diasumsikan.** `tests/Pest.php` hanya mengikat `TestCase` + `RefreshDatabase` ke `->in('Feature')`; `tests/Unit` tidak terikat apa pun, dan 15 dari 18 berkas di sana mendeklarasikan `uses(...)` sendiri. Karena itu ketiga test baru yang memanggil `base_path()` membawa `uses(TestCase::class)` eksplisit — tanpa baris itu container tidak ter-boot dan `base_path()` gagal. `LedgerFileTest` sengaja tidak membawanya: ia hanya memakai `sys_get_temp_dir()`.

**Ekspektasi Pest sudah dicocokkan dengan signature sungguhan.** `toBe`, `toBeLessThanOrEqual`, `toEndWith`, `toHaveCount`, dan `toBeGreaterThan` menerima `string $message` sebagai argumen kedua — dipakai di guard untuk memberi pesan kegagalan yang menyebut ID dan lokasi. `toContain` bersifat variadic dan **tidak** menerima pesan, jadi ia dipakai tanpa argumen kedua. `toThrow` menerima exception lalu penggalan pesannya.

**Konsistensi tipe.** `BusinessRuleCatalog::codes(): list<string>` dipakai di T2 dan dikonsumsi guard assertion 3 dan 7 dengan nama yang sama. `FeatureCatalog::scenarios(): list<ScenarioDescriptor>` didefinisikan T3, dipakai assertion 1–4 dan 6–7. Properti `ScenarioDescriptor` (`id`, `tags`, `businessRules`, `milestone`, `class`, `file`, `line`) plus method `location()` dipakai konsisten di seluruh guard. `LedgerFile::entries()`/`ceiling()` didefinisikan T4, dipakai assertion 4, 5, 6, 8.

**API pihak ketiga sudah diverifikasi, bukan diingat.** `behat/gherkin` v4.17.0 dipasang di direktori sementara dan diuji terhadap file `.feature` bergaya repo ini sebelum plan ditulis. Tiga jebakan yang ditemukan dan sudah tertulis di T3: `parseFile()` menolak path relatif, `getTags()` mengembalikan tag tanpa `@`, dan namespace-nya `Behat\Gherkin\` bukan `Behat\Gherkin\src\`. Pola `^\|\s*(BR-[A-Z]+-\d+)\s*\|` juga sudah diuji terhadap DDD sungguhan — cocok 100 kali, tanpa satu pun penyebutan prosa yang lolos.

**Aritmetika daftar.** Plafon `uncovered.txt` menurun 92 → 90 (T6) → 88 (T7) → 83 (T8) → 80 (T9) → 74 (T10); selisihnya 18, sama dengan jumlah BR Wave 1a. Plafon `pending.txt` menaik 0 → 9 → 17 → 28 → 39 → 53; selisihnya 53, sama dengan jumlah skenario yang ditulis (9 + 8 + 11 + 11 + 14).

**Risiko yang sudah ditangani di plan.** T5 Step 3 sengaja merusak `uncovered.txt` untuk membuktikan assertion 7 benar-benar menggigit — tanpa langkah itu, guard yang lulus tanpa memeriksa apa pun tidak akan pernah ketahuan. T1 Step 1 memeriksa kondisi awal supaya task tidak dijalankan dua kali. T4 Step 10 menjelaskan bahwa guard lulus secara hampa pada katalog kosong, supaya pelaksana tidak mengira sudah selesai.
