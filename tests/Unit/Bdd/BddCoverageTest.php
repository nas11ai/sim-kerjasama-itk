<?php

// tests/Unit/Bdd/BddCoverageTest.php

use App\Support\Bdd\BusinessRuleCatalog;
use App\Support\Bdd\FeatureCatalog;
use App\Support\Bdd\LedgerFile;
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
