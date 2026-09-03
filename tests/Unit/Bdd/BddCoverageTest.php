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
