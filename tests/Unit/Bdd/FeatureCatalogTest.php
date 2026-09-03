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
