<?php

// tests/Unit/Quality/CommentMetricsTest.php

use App\Support\Quality\CommentMetrics;

function writeCommented(string $contents): string
{
    $path = sys_get_temp_dir().'/comments_'.uniqid().'.php';
    file_put_contents($path, $contents);

    return $path;
}

it('menghitung baris prosa terpanjang', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    // baris prosa pertama yang cukup panjang
    // baris prosa kedua yang cukup panjang
    // baris prosa ketiga yang cukup panjang
    // baris prosa keempat yang cukup panjang
    class Sample {}
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(4);

    unlink($path);
});

it('tidak menghitung anotasi sebagai prosa', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    /**
     * @param  array<string, int>  $a
     * @param  array<string, int>  $b
     * @return array<string, int>
     * @throws RuntimeException
     */
    class Sample {}
    PHP);

    // Level 10 menuntut docblock kaya; membatasinya akan membuat dua aturan
    // repo ini berkelahi.
    expect(CommentMetrics::longestProseRun($path))->toBe(0);

    unlink($path);
});

it('menghitung prosa di dalam docblock yang juga punya anotasi', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    /**
     * Kalimat penjelas pertama yang panjang.
     * Kalimat penjelas kedua yang panjang.
     *
     * @return void
     */
    class Sample {}
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(2);

    unlink($path);
});

it('memulai hitungan baru setelah baris kode menyela', function () {
    $path = writeCommented(<<<'PHP'
    <?php
    // prosa pertama yang cukup panjang
    // prosa kedua yang cukup panjang
    class Sample {}
    // prosa ketiga yang cukup panjang
    PHP);

    expect(CommentMetrics::longestProseRun($path))->toBe(2);

    unlink($path);
});
