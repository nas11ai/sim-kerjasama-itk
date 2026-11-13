<?php

// K9 (pemilik 2026-11-13) — teks yang dilihat user tidak memuat "—". String PHP di app/ berakhir di
// layar (flash, validasi, label, subjek email), sedangkan komentar tidak, jadi yang diperiksa token string.

it('string literal di app/ tidak memuat em-dash', function () {
    $root = dirname(__DIR__, 3);
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app'));

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || $file->getExtension() !== 'php') {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file->getPathname())) as $token) {
            if (is_array($token)
                && in_array($token[0], [T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE], true)
                && str_contains($token[1], '—')) {
                $offenders[] = str_replace($root.'/', '', $file->getPathname()).':'.$token[2];
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('template Blade di resources/views tidak memuat em-dash di luar komentar', function () {
    $root = dirname(__DIR__, 3);
    $offenders = [];
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/resources/views'));

    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $source = (string) preg_replace('/\{\{--.*?--\}\}|<!--.*?-->/s', '', (string) file_get_contents($file->getPathname()));

        if (str_contains($source, '—')) {
            $offenders[] = str_replace($root.'/', '', $file->getPathname());
        }
    }

    expect($offenders)->toBe([]);
});
