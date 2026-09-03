<?php

// tests/Unit/Bdd/LedgerFileTest.php

use App\Support\Bdd\LedgerFile;

function writeLedger(string $body): string
{
    $path = sys_get_temp_dir().'/ledger-'.uniqid().'.txt';
    file_put_contents($path, $body);

    return $path;
}

it('reads entries and ignores comments and blank lines', function () {
    $path = writeLedger("# catatan\n# max: 5\n\nBR-FE-01\nBR-FE-02\n");

    $ledger = new LedgerFile($path);

    expect($ledger->entries())->toBe(['BR-FE-01', 'BR-FE-02'])
        ->and($ledger->ceiling())->toBe(5);

    unlink($path);
});

it('treats a missing ceiling as zero', function () {
    $path = writeLedger("# tanpa plafon\nBR-FE-01\n");

    expect((new LedgerFile($path))->ceiling())->toBe(0);

    unlink($path);
});

it('throws when the file does not exist', function () {
    expect(fn () => (new LedgerFile('/tmp/tidak-ada-'.uniqid().'.txt'))->entries())
        ->toThrow(RuntimeException::class);
});
