<?php

// tests/Unit/Architecture/SupportPurityTest.php
//
// BR-RPT-09 ditegakkan AuditCoverageTest yang hanya menyapu app/Actions/ dan
// app/Queries/. Sebuah kelas di app/Support/ yang menulis state karena itu
// mengubah data di luar jangkauan audit — bukan karena ada yang memutuskan
// begitu, melainkan karena direktorinya tidak diperiksa siapa pun.

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

uses(TestCase::class);

it('app/Support tidak menulis state — di sana tidak ada audit yang menjaganya', function () {
    // Guard menolak, Gate mengizinkan, Catalog membaca. Tidak satu pun
    // perlu menulis; yang perlu menulis tempatnya di app/Actions/.
    $penulis = [
        '->update(', '->delete(', '->save(', '->insert(',
        '->upsert(', '->increment(', '->decrement(',
        '->forceDelete(', '->restore(',
        '->create(', 'firstOrCreate(', 'updateOrCreate(',
    ];
    // Finder::create() hanya membaca sistem berkas, jadi bukan penulisan record.
    $penulisStatis = '/(?<!Finder)::create\(/';

    $pelanggar = [];

    foreach (Finder::create()->files()->in(app_path('Support'))->name('*.php')->sortByName() as $file) {
        $isi = (string) file_get_contents($file->getPathname());

        foreach ($penulis as $tanda) {
            if (str_contains($isi, $tanda)) {
                $pelanggar[] = 'app/Support/'.$file->getRelativePathname().' — '.$tanda;
            }
        }

        if (preg_match($penulisStatis, $isi) === 1) {
            $pelanggar[] = 'app/Support/'.$file->getRelativePathname().' — ::create(';
        }
    }

    expect($pelanggar)->toBe([], implode("\n", [
        'Berkas app/Support/ berikut menulis state, padahal tidak ada audit yang menyapu direktori itu:',
        '- '.implode("\n- ", $pelanggar),
        '',
        'Pindahkan ke app/Actions/ supaya AuditCoverageTest menjangkaunya.',
    ]));
});
