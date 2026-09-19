<?php

// tests/Unit/Quality/DuplicationScannerTest.php

use App\Support\Quality\DuplicationScanner;

function writeBlock(string $name, string $contents): string
{
    $path = sys_get_temp_dir().'/dup_'.uniqid().'_'.$name;
    file_put_contents($path, $contents);

    return $path;
}

function sixIdenticalLines(): string
{
    return <<<'TXT'
    const first = computeSomethingLong(input);
    const second = computeSomethingElse(input);
    const third = combineBothResults(first, second);
    const fourth = filterTheResult(third, threshold);
    const fifth = sortTheResult(fourth, sortKey);
    const sixth = returnTheResult(fifth);
    TXT;
}

it('menemukan blok yang muncul di dua berkas', function () {
    $a = writeBlock('a.js', sixIdenticalLines());
    $b = writeBlock('b.js', sixIdenticalLines());

    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(1);

    unlink($a);
    unlink($b);
});

it('tidak menghitung blok yang hanya ada di satu berkas', function () {
    $a = writeBlock('a.js', sixIdenticalLines());

    expect(DuplicationScanner::countSharedBlocks([$a]))->toBe(0);

    unlink($a);
});

it('mengabaikan baris pendek', function () {
    // Enam baris identik, jadi tanpa penyaring ia terhitung satu blok.
    $short = "});\nreturn;\n}\nconst x = 1;\n});\n    }\n";
    $a = writeBlock('a.js', $short);
    $b = writeBlock('b.js', $short);

    // `});` dan `return;` muncul di mana-mana; itu tanda baca, bukan duplikasi.
    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(0);

    unlink($a);
    unlink($b);
});

it('mengabaikan deklarasi impor, karena urutan impor identik bukan logika yang disalin', function () {
    $header = <<<'PHP'
    namespace App\Notifications;
    use App\Data\Contract\NotificationPayload;
    use App\Notifications\Concerns\UsesConfiguredChannels;
    use Illuminate\Bus\Queueable;
    use Illuminate\Notifications\Messages\MailMessage;
    use Illuminate\Notifications\Notification;
    import { computed, ref } from 'vue';
    PHP;

    $a = writeBlock('a.php', $header);
    $b = writeBlock('b.php', $header);

    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(0);

    unlink($a);
    unlink($b);
});

it('mengabaikan isi impor yang ditulis lintas baris', function () {
    $header = <<<'TS'
    import type {
        FormPhaseStatistics,
        FormSubmissionStatistics,
        SubmissionReviewerStatistics,
        OrganizationStatistics,
        ReviewerWorkloadStatistics,
    } from '@/types/statistics'
    TS;

    $a = writeBlock('a.vue', $header);
    $b = writeBlock('b.vue', $header);

    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(0);

    unlink($a);
    unlink($b);
});

it('memutus blok di impor alih-alih menyambung baris di sekitarnya', function () {
    // Kalau impor sekadar dibuang, dua potongan tiga baris ini menyatu jadi blok
    // enam baris yang tidak pernah ditulis berdampingan.
    [$top, $bottom] = array_chunk(explode("\n", sixIdenticalLines()), 3);

    $a = writeBlock('a.ts', implode("\n", [...$top, "import { helper } from './helper'", ...$bottom]));
    $b = writeBlock('b.ts', sixIdenticalLines());

    expect(DuplicationScanner::countSharedBlocks([$a, $b]))->toBe(0);

    unlink($a);
    unlink($b);
});

it('menyebut berkas mana saja yang ikut berduplikasi', function () {
    $a = writeBlock('a.js', sixIdenticalLines());
    $b = writeBlock('b.js', sixIdenticalLines());

    $shared = DuplicationScanner::filesSharing([$a, $b]);

    expect($shared)->toHaveKey($a)
        ->and($shared[$a])->toBe(1);

    unlink($a);
    unlink($b);
});
