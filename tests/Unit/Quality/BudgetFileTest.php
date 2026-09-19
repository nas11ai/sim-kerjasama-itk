<?php

// tests/Unit/Quality/BudgetFileTest.php

use App\Support\Quality\BudgetFile;

function writeBudget(string $contents): string
{
    $path = sys_get_temp_dir().'/budget_'.uniqid().'.txt';
    file_put_contents($path, $contents);

    return $path;
}

it('membaca angka dan mengabaikan komentar', function () {
    $path = writeBudget(<<<'TXT'
    # komentar
    hard.php.file.loc:     200

    max.legacy.files:      112
    TXT);

    $budget = new BudgetFile($path);

    expect($budget->value('hard.php.file.loc'))->toBe(200)
        ->and($budget->value('max.legacy.files'))->toBe(112);

    unlink($path);
});

it('menolak kunci yang tidak ada alih-alih mengembalikan nol', function () {
    // Nol diam-diam adalah cara plafon jadi longgar tanpa ada yang memutuskan.
    $path = writeBudget("hard.php.file.loc: 200\n");

    expect(fn () => (new BudgetFile($path))->value('missing.key'))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

it('menolak baris yang bentuknya tidak dikenali', function () {
    $path = writeBudget("hard.php.file.loc = 200\n");

    expect(fn () => (new BudgetFile($path))->value('hard.php.file.loc'))
        ->toThrow(RuntimeException::class);

    unlink($path);
});

it('menolak berkas yang tidak terbaca', function () {
    expect(fn () => (new BudgetFile('/tidak/ada.txt'))->value('any.key'))
        ->toThrow(RuntimeException::class);
});
