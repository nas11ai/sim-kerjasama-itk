<?php

// tests/Unit/Quality/HardLimitsTest.php

use App\Support\Quality\BudgetFile;
use App\Support\Quality\HardLimits;

function limitsFixture(string $name, string $contents): string
{
    $dir = sys_get_temp_dir().'/limits_'.getmypid();

    if (!is_dir($dir)) {
        mkdir($dir);
    }

    $path = "{$dir}/{$name}";
    file_put_contents($path, $contents);

    return $path;
}

function limitsBudget(): BudgetFile
{
    return new BudgetFile(limitsFixture('budget.txt', <<<'TXT'
    hard.php.file.loc:     200
    hard.vue.file.loc:       3
    hard.method.loc:        40
    hard.method.cc:          8
    hard.method.params:      4
    hard.method.returns:     4
    hard.comment.prose:      3
    TXT));
}

it('membebaskan constructor yang seluruh parameternya promoted property', function () {
    $path = limitsFixture('Carrier.php', <<<'PHP'
    <?php
    final class Carrier
    {
        public function __construct(
            public readonly int $a,
            public readonly int $b,
            public readonly int $c,
            public readonly int $d,
            public readonly int $e,
        ) {}
    }
    PHP);

    expect((new HardLimits(limitsBudget(), [$path]))->violations($path))->toBe([]);
});

it('tetap menolak constructor yang menyuntik lima dependensi', function () {
    $path = limitsFixture('Service.php', <<<'PHP'
    <?php
    final class Service
    {
        public function __construct(A $a, B $b, C $c, D $d, E $e) {}
    }
    PHP);

    expect((new HardLimits(limitsBudget(), [$path]))->violations($path))
        ->toBe(['__construct() terlalu banyak parameter']);
});

it('menganggap blok yang juga ada di berkas lain sebagai pelanggaran', function () {
    $block = <<<'VUE'
    <template>
      <div class="first-long-line-of-markup">
      <span class="second-long-line-of-markup" />
      <span class="third-long-line-of-markup" />
      <span class="fourth-long-line-of-markup" />
      <span class="fifth-long-line-of-markup" />
      <span class="sixth-long-line-of-markup" />
    </template>
    VUE;

    $a = limitsFixture('A.vue', $block);
    $b = limitsFixture('B.vue', $block);
    $limits = new HardLimits(limitsBudget(), [$a, $b]);

    expect($limits->metricViolations($a))->toBe(['ukuran berkas'])
        ->and($limits->sharedBlocks($a))->toBe(1)
        ->and($limits->violations($a))->toBe(['ukuran berkas', 'berbagi 1 blok dengan berkas lain']);
});
