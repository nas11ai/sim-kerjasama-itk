<?php

// tests/Unit/Architecture/QualityBudgetTest.php
//
// Pagar kualitas kode. Rancangannya di docs/design/refactor-plan.md §4.

use App\Support\Bdd\LedgerFile;
use App\Support\Quality\BudgetFile;
use App\Support\Quality\CommentMetrics;
use App\Support\Quality\DuplicationScanner;
use App\Support\Quality\HardLimits;
use App\Support\Quality\MixedMetrics;
use App\Support\Quality\SourceTree;
use Tests\TestCase;

// Butuh container untuk base_path(), tapi tidak butuh database — sama dengan
// BddCoverageTest, karena itu ia tinggal di tests/Unit.
uses(TestCase::class);

function qualityBudget(): BudgetFile
{
    return new BudgetFile(base_path('quality-budget.txt'));
}

/** @return list<string> */
function qualityLegacy(): array
{
    return (new LedgerFile(base_path('quality-legacy.txt')))->entries();
}

/** @return list<string> */
function qualityPaths(string $dir, string $extension): array
{
    return SourceTree::paths(base_path(), $dir, $extension);
}

/** @return list<string> seluruh berkas yang diukur, path relatif */
function qualityCorpus(): array
{
    return SourceTree::measured(base_path());
}

function qualityLimits(): HardLimits
{
    return new HardLimits(qualityBudget(), array_map(base_path(...), qualityCorpus()));
}

function qualitySource(string $path): string
{
    $source = file_get_contents(base_path($path));

    return $source === false ? '' : $source;
}

it('assertion 1 — berkas di luar daftar memenuhi seluruh ambang keras', function () {
    $limits = qualityLimits();
    $legacy = qualityLegacy();
    $problems = [];

    foreach (qualityCorpus() as $path) {
        $reasons = in_array($path, $legacy, true) ? [] : $limits->metricViolations(base_path($path));

        if ($reasons !== []) {
            $problems[] = $path.' — '.implode(', ', $reasons);
        }
    }

    expect($problems)->toBe([], "Melewati ambang keras:\n".implode("\n", $problems));
});

it('assertion 2 — daftar tidak memuat berkas yang sudah memenuhi ambang', function () {
    $limits = qualityLimits();

    $resolved = array_values(array_filter(
        qualityLegacy(),
        static fn (string $path): bool => file_exists(base_path($path))
            && $limits->violations(base_path($path)) === [],
    ));

    expect($resolved)->toBe(
        [],
        "Sudah memenuhi ambang. Hapus dari quality-legacy.txt dan turunkan max.legacy.files:\n"
            .implode("\n", $resolved)
    );
});

it('assertion 3 — daftar tidak memuat berkas yang tidak ada', function () {
    $ghosts = array_values(array_filter(
        qualityLegacy(),
        static fn (string $path): bool => !file_exists(base_path($path)),
    ));

    expect($ghosts)->toBe([], 'Berkas tidak ada, hapus barisnya: '.implode(', ', $ghosts));
});

it('assertion 4 — daftar legacy tidak melewati plafonnya', function () {
    expect(count(qualityLegacy()))
        ->toBeLessThanOrEqual(qualityBudget()->value('max.legacy.files'));
});

it('assertion 5 — mixed tidak melewati plafonnya', function () {
    $total = array_sum(array_map(
        static fn (string $path): int => MixedMetrics::count(base_path($path)),
        qualityPaths('app', 'php'),
    ));

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.mixed'));
});

it('assertion 6 — blok duplikat tidak melewati plafonnya', function () {
    expect(DuplicationScanner::countSharedBlocks(array_map(base_path(...), qualityCorpus())))
        ->toBeLessThanOrEqual(qualityBudget()->value('max.duplicate.blocks'));
});

it('assertion 7 — berkas di luar daftar tidak berbagi blok dengan berkas mana pun', function () {
    $limits = qualityLimits();
    $legacy = qualityLegacy();
    $duplicated = [];

    foreach (qualityCorpus() as $path) {
        $shared = in_array($path, $legacy, true) ? 0 : $limits->sharedBlocks(base_path($path));

        if ($shared > 0) {
            $duplicated[] = "{$path} — {$shared} blok juga ada di berkas lain";
        }
    }

    // Spec §3.1: berkas baru tidak boleh memperkenalkan blok yang sudah ada di berkas lain.
    expect($duplicated)->toBe([], "Blok duplikat di berkas non-legacy:\n".implode("\n", $duplicated));
});

it('assertion 8 — Data\\Contract tidak membocorkan model', function () {
    $leaks = [];

    foreach (qualityPaths('app/Data', 'php') as $path) {
        if (!str_contains($path, '/Contract/')) {
            continue;
        }

        if (str_contains(qualitySource($path), 'App\\Models\\')) {
            $leaks[] = "{$path} — mengimpor Model";
        }

        if (preg_match('/->(toArray|only)\s*\(/', qualitySource($path)) === 1) {
            $leaks[] = "{$path} — menyebar atribut model";
        }
    }

    expect($leaks)->toBe([], implode("\n", $leaks));
});

it('assertion 9 — Data\\Contract tidak menyusun Data\\View', function () {
    $inverted = array_values(array_filter(
        qualityPaths('app/Data', 'php'),
        static fn (string $path): bool => str_contains($path, '/Contract/')
            && str_contains(qualitySource($path), 'App\\Data\\View\\'),
    ));

    expect($inverted)->toBe(
        [],
        'Kontrak menyusun payload layar; arahnya harus sebaliknya: '.implode(', ', $inverted)
    );
});

it('assertion 10 — response()->json tidak melewati plafonnya', function () {
    $total = array_sum(array_map(
        static fn (string $path): int => substr_count(qualitySource($path), 'response()->json'),
        qualityPaths('app/Http', 'php'),
    ));

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.untyped.json'));
});

it('assertion 11 — prosa komentar berlebih tidak melewati plafonnya', function () {
    $budget = qualityBudget();

    $overflow = count(array_filter(
        qualityPaths('app', 'php'),
        static fn (string $path): bool => CommentMetrics::longestProseRun(base_path($path))
            > $budget->value('hard.comment.prose'),
    ));

    expect($overflow)->toBeLessThanOrEqual($budget->value('max.comment.overflow'));
});

it('assertion 12 — identifier non-Inggris tidak melewati plafonnya', function () {
    // Daftar kata, bukan deteksi bahasa: ia mencegah kemunduran pada kata yang
    // sudah dikenal. Penamaan selebihnya urusan review.
    $words = [
        'tercatat', 'terdampak', 'sebelum', 'sesudah', 'pemakai', 'penghalang',
        'rincian', 'institusi', 'sudahAda', 'hasil', 'jumlah', 'berkas', 'daftar',
        'melanggar', 'alasan', 'bersih', 'bocor', 'gemuk', 'beres', 'masalah',
        'undangan', 'anggota', 'mitra', 'keadaan', 'cakupan', 'kuota', 'tujuan',
    ];

    $pattern = '/\$('.implode('|', $words).')\b/i';

    $total = array_sum(array_map(
        static fn (string $path): int => preg_match_all($pattern, qualitySource($path)) ?: 0,
        qualityPaths('app', 'php'),
    ));

    expect($total)->toBeLessThanOrEqual(qualityBudget()->value('max.id.nonenglish'));
});
