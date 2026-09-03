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
