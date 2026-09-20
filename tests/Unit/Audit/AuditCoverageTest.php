<?php

// tests/Unit/Audit/AuditCoverageTest.php
//
// BR-RPT-09 — cakupan audit diturunkan dari arsitektur, bukan dari daftar
// aksi yang dirawat tangan. Daftar semacam itu pasti membusuk begitu ada
// Action baru; yang tidak membusuk adalah aturan strukturalnya: setiap kelas
// di app/Actions/ dan app/Queries/ wajib menulis audit kecuali ia menyatakan
// dirinya tidak mengubah apa pun.

use App\Actions\Concerns\ChangesNoState;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<int, array{class: class-string, path: string, source: string}>
 */
function auditedClasses(): array
{
    $classes = [];

    // BR-RPT-09 — cakupannya direktori tempat use case hidup, bukan satu nama
    // direktori. Query masuk karena ia bisa saja menulis, bukan karena ia
    // diharapkan menulis.
    foreach (['Actions' => 'App\\Actions\\', 'Queries' => 'App\\Queries\\'] as $dir => $namespace) {
        foreach (Finder::create()->files()->in(app_path($dir))->name('*.php')->sortByName() as $file) {
            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $class = $namespace.$relative;

            if (!class_exists($class)) {
                continue;
            }

            $classes[] = [
                'class' => $class,
                'path' => 'app/'.$dir.'/'.$file->getRelativePathname(),
                'source' => (string) file_get_contents($file->getPathname()),
            ];
        }
    }

    return $classes;
}

it('[RPT-AUDIT-07] setiap Action dan Query menulis audit atau menyatakan tidak mengubah state', function () {
    $violations = [];

    foreach (auditedClasses() as $candidate) {
        $writesAudit = str_contains($candidate['source'], 'AuditTrail::record');
        $declaresNoState = in_array(ChangesNoState::class, class_implements($candidate['class']) ?: [], true);

        if (!$writesAudit && !$declaresNoState) {
            $violations[] = $candidate['path'];
        }
    }

    expect($violations)->toBe([], implode("\n", [
        'Berkas berikut tidak menulis jejak audit dan tidak menyatakan dirinya tanpa perubahan state:',
        '- '.implode("\n- ", $violations),
        '',
        'Tulis AuditTrail::record(...) di dalamnya, atau implement ChangesNoState kalau ia memang kueri murni.',
    ]));
});

it('[RPT-AUDIT-07] penanda ChangesNoState tidak dipakai kelas yang tetap menulis audit', function () {
    // Kalau keduanya ada, salah satunya bohong — dan yang bohong itu penanda,
    // karena penanda dibaca manusia sedangkan audit dibaca mesin.
    $ambiguous = [];

    foreach (auditedClasses() as $candidate) {
        $writesAudit = str_contains($candidate['source'], 'AuditTrail::record');
        $declaresNoState = in_array(ChangesNoState::class, class_implements($candidate['class']) ?: [], true);

        if ($writesAudit && $declaresNoState) {
            $ambiguous[] = $candidate['path'];
        }
    }

    expect($ambiguous)->toBe([], 'Menyatakan ChangesNoState tapi tetap menulis audit: '.implode(', ', $ambiguous));
});

it('AuditTrail dipakai lewat satu pintu, bukan activity() langsung', function () {
    // Bentuk properti `old`/`new` yang seragam adalah yang membuat layar audit
    // bisa menampilkan "nilai lama → nilai baru" tanpa mengenali sekian bentuk.
    $direct = [];

    foreach (auditedClasses() as $candidate) {
        if (preg_match('/(?<![\w:>])activity\s*\(/', $candidate['source']) === 1) {
            $direct[] = $candidate['path'];
        }
    }

    expect($direct)->toBe([], 'Memanggil activity() langsung, bukan AuditTrail::record(): '.implode(', ', $direct));
});
