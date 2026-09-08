<?php

// tests/Unit/Audit/AuditCoverageTest.php
//
// BR-RPT-09 — cakupan audit diturunkan dari arsitektur, bukan dari daftar
// aksi yang dirawat tangan. Daftar semacam itu pasti membusuk begitu ada
// Action baru; yang tidak membusuk adalah aturan strukturalnya: satu Action =
// satu use case yang mengubah state, jadi setiap Action wajib menulis audit
// kecuali ia menyatakan dirinya tidak mengubah apa pun.

use App\Actions\Concerns\ChangesNoState;
use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

/**
 * @return array<int, array{class: class-string, path: string, source: string}>
 */
function actionClasses(): array
{
    $classes = [];

    foreach (Finder::create()->files()->in(app_path('Actions'))->name('*.php')->sortByName() as $file) {
        $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
        $class = 'App\\Actions\\'.$relative;

        if (!class_exists($class)) {
            continue;
        }

        $classes[] = [
            'class' => $class,
            'path' => 'app/Actions/'.$file->getRelativePathname(),
            'source' => (string) file_get_contents($file->getPathname()),
        ];
    }

    return $classes;
}

it('[RPT-AUDIT-07] setiap Action menulis audit atau menyatakan tidak mengubah state', function () {
    $pelanggar = [];

    foreach (actionClasses() as $action) {
        $menulisAudit = str_contains($action['source'], 'AuditTrail::record');
        $tanpaState = in_array(ChangesNoState::class, class_implements($action['class']) ?: [], true);

        if (!$menulisAudit && !$tanpaState) {
            $pelanggar[] = $action['path'];
        }
    }

    expect($pelanggar)->toBe([], implode("\n", [
        'Action berikut tidak menulis jejak audit dan tidak menyatakan dirinya tanpa perubahan state:',
        '- '.implode("\n- ", $pelanggar),
        '',
        'Tulis AuditTrail::record(...) di dalamnya, atau implement ChangesNoState kalau ia memang kueri murni.',
    ]));
});

it('[RPT-AUDIT-07] penanda ChangesNoState tidak dipakai Action yang tetap menulis audit', function () {
    // Kalau keduanya ada, salah satunya bohong — dan yang bohong itu penanda,
    // karena penanda dibaca manusia sedangkan audit dibaca mesin.
    $rancu = [];

    foreach (actionClasses() as $action) {
        $menulisAudit = str_contains($action['source'], 'AuditTrail::record');
        $tanpaState = in_array(ChangesNoState::class, class_implements($action['class']) ?: [], true);

        if ($menulisAudit && $tanpaState) {
            $rancu[] = $action['path'];
        }
    }

    expect($rancu)->toBe([], 'Menyatakan ChangesNoState tapi tetap menulis audit: '.implode(', ', $rancu));
});

it('AuditTrail dipakai lewat satu pintu, bukan activity() langsung di Action', function () {
    // Bentuk properti `old`/`new` yang seragam adalah yang membuat layar audit
    // bisa menampilkan "nilai lama → nilai baru" tanpa mengenali sekian bentuk.
    $langsung = [];

    foreach (actionClasses() as $action) {
        if (preg_match('/(?<![\w:>])activity\s*\(/', $action['source']) === 1) {
            $langsung[] = $action['path'];
        }
    }

    expect($langsung)->toBe([], 'Memanggil activity() langsung, bukan AuditTrail::record(): '.implode(', ', $langsung));
});
