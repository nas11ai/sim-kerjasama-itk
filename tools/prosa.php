<?php

// tools/prosa.php — laporan run prosa komentar. Di luar korpus terukur.

require __DIR__.'/../vendor/autoload.php';

use App\Support\Quality\SourceFile;
use App\Support\Quality\SourceTree;
use Illuminate\Contracts\Console\Kernel;

$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @return list<array{int, int}> pasangan [baris awal, panjang run] untuk run > 3 */
function runProsa(string $path): array
{
    $hasil = [];
    $run = 0;
    $awal = null;
    $nomor = 0;

    foreach (SourceFile::lines(SourceFile::read($path)) as $line) {
        $nomor++;
        $t = trim($line);
        $komentar = str_starts_with($t, '//') || str_starts_with($t, '*') || str_starts_with($t, '/*');

        if (!$komentar) {
            if ($run > 3) {
                $hasil[] = [$awal, $run];
            }
            $run = 0;
            $awal = null;

            continue;
        }

        $body = trim(preg_replace('#^(//+|/\*+|\*+/?)#', '', $t) ?? '');

        if (strlen($body) >= 4 && !str_starts_with($body, '@')) {
            $run++;
            $awal ??= $nomor;
        }
    }

    if ($run > 3) {
        $hasil[] = [$awal, $run];
    }

    return $hasil;
}

$paths = array_slice($argv, 1);

if ($paths === []) {
    $paths = SourceTree::paths(base_path(), 'app', 'php');
}

$total = 0;

foreach ($paths as $path) {
    $abs = str_starts_with($path, '/') ? $path : base_path($path);

    foreach (runProsa($abs) as [$baris, $run]) {
        printf("%s:%d  run=%d\n", $path, $baris, $run);
        $total++;
    }
}

printf("\n%d run melewati plafon 3.\n", $total);
