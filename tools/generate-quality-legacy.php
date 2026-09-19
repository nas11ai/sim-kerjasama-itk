<?php

// tools/generate-quality-legacy.php
//
// Dijalankan SEKALI saat pagar dipasang. Setelah itu daftarnya hanya menyusut
// lewat tangan manusia. Definisi pelanggarannya milik HardLimits, bukan skrip ini.

require __DIR__.'/../vendor/autoload.php';

use App\Support\Quality\BudgetFile;
use App\Support\Quality\HardLimits;
use App\Support\Quality\SourceTree;

$root = dirname(__DIR__);
$paths = SourceTree::measured($root);

$limits = new HardLimits(
    new BudgetFile("{$root}/quality-budget.txt"),
    array_map(static fn (string $path): string => "{$root}/{$path}", $paths),
);

$legacy = array_values(array_filter(
    $paths,
    static fn (string $path): bool => $limits->violations("{$root}/{$path}") !== [],
));

$header = <<<'TXT'
# Berkas yang belum memenuhi ambang keras di quality-budget.txt, atau yang
# berbagi blok enam baris dengan berkas lain.
#
# Daftar ini HANYA BOLEH MENYUSUT. Hapus satu baris setiap kali berkasnya selesai
# dibedah, lalu turunkan max.legacy.files. Assertion 2 menolak berkas yang sudah
# memenuhi ambang tapi masih terdaftar, jadi keduanya tidak bisa berselisih.
#
# Inilah antrean kerja refactor. Ambil dari atas.

TXT;

file_put_contents("{$root}/quality-legacy.txt", $header.implode("\n", $legacy)."\n");

echo count($legacy)." berkas masuk daftar\n";
