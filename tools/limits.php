<?php

// tools/limits.php — pelanggaran HardLimits untuk berkas yang disebut. Di luar korpus terukur.

require __DIR__.'/../vendor/autoload.php';

use App\Support\Quality\BudgetFile;
use App\Support\Quality\CodeMetrics;
use App\Support\Quality\HardLimits;
use App\Support\Quality\SourceTree;

$root = dirname(__DIR__);
$budget = new BudgetFile("{$root}/quality-budget.txt");
$limits = new HardLimits(
    $budget,
    array_map(static fn (string $p): string => "{$root}/{$p}", SourceTree::measured($root)),
);

$masihMelanggar = 0;

foreach (array_slice($argv, 1) as $path) {
    $violations = $limits->violations("{$root}/{$path}");
    echo $path.($violations === [] ? '  ✓' : '')."\n";

    foreach ($violations as $v) {
        echo "   ✗ {$v}\n";
    }

    foreach (CodeMetrics::forFile("{$root}/{$path}")['methods'] as $m) {
        $lewat = $m->loc > $budget->value('hard.method.loc')
            || $m->complexity > $budget->value('hard.method.cc')
            || $m->returns > $budget->value('hard.method.returns')
            || ($m->parameters > $budget->value('hard.method.params') && !$m->onlyPromotesProperties());

        if ($lewat) {
            printf("      %s() loc=%d cc=%d params=%d returns=%d\n", $m->name, $m->loc, $m->complexity, $m->parameters, $m->returns);
        }
    }

    $masihMelanggar += $violations === [] ? 0 : 1;
}

echo $masihMelanggar === 0 ? "\nBERSIH\n" : "\n{$masihMelanggar} berkas masih melanggar\n";
