<?php

namespace App\Support\Bdd;

use RuntimeException;

/**
 * Daftar utang berplafon — pending.txt dan uncovered.txt.
 *
 * Formatnya sengaja sesederhana mungkin: baris '# max: N' menetapkan plafon,
 * baris komentar dan kosong diabaikan, sisanya entri.
 */
final class LedgerFile
{
    private const CEILING_PATTERN = '/^#\s*max:\s*(\d+)\s*$/m';

    public function __construct(private readonly string $path) {}

    /**
     * @return list<string>
     */
    public function entries(): array
    {
        $lines = preg_split('/\R/', $this->contents());

        if ($lines === false) {
            throw new RuntimeException("Gagal memecah baris {$this->path}");
        }

        $entries = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            $entries[] = $trimmed;
        }

        return $entries;
    }

    public function ceiling(): int
    {
        if (preg_match(self::CEILING_PATTERN, $this->contents(), $matches) !== 1) {
            return 0;
        }

        return (int) $matches[1];
    }

    private function contents(): string
    {
        if (!is_readable($this->path)) {
            throw new RuntimeException("Daftar tidak terbaca: {$this->path}");
        }

        $contents = file_get_contents($this->path);

        if ($contents === false) {
            throw new RuntimeException("Daftar tidak terbaca: {$this->path}");
        }

        return $contents;
    }
}
