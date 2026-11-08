<?php

// tests/Unit/Support/LikePatternTest.php
//
// E2E-10 — input pencarian dicocokkan apa adanya: `%` dan `_` tidak menjadi wildcard.

use App\Support\Database\LikePattern;

it('membungkus istilah dengan % di kedua sisi', function () {
    expect(LikePattern::contains('biochar'))->toBe('%biochar%');
});

it('meng-escape wildcard dan karakter escape', function (string $term, string $expected) {
    expect(LikePattern::contains($term))->toBe($expected);
})->with([
    'persen' => ['100%', '%100\\%%'],
    'garis bawah' => ['kode_baru', '%kode\\_baru%'],
    'garis miring terbalik lebih dulu' => ['a\\%', '%a\\\\\\%%'],
]);
