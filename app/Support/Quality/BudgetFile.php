<?php

namespace App\Support\Quality;

use RuntimeException;

/**
 * Membaca `quality-budget.txt`. Kunci yang hilang dilempar, bukan dikembalikan
 * sebagai nol: nol diam-diam adalah cara plafon jadi longgar tanpa diputuskan.
 */
final class BudgetFile
{
    private const LINE_PATTERN = '/^([a-z][a-z0-9.]*)\s*:\s*(\d+)$/';

    /** @var array<string, int>|null */
    private ?array $values = null;

    public function __construct(private readonly string $path) {}

    public function value(string $key): int
    {
        $values = $this->values ??= $this->parse();

        if (!array_key_exists($key, $values)) {
            throw new RuntimeException(
                "Kunci \"{$key}\" tidak ada di {$this->path}. Tambahkan barisnya secara sadar."
            );
        }

        return $values[$key];
    }

    /** @return array<string, int> */
    private function parse(): array
    {
        $values = [];

        foreach (SourceFile::lines(SourceFile::read($this->path)) as $line) {
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            if (preg_match(self::LINE_PATTERN, $trimmed, $matches) !== 1) {
                throw new RuntimeException("Baris tidak dikenali di {$this->path}: \"{$trimmed}\"");
            }

            $values[$matches[1]] = (int) $matches[2];
        }

        return $values;
    }
}
