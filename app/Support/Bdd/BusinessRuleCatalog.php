<?php

namespace App\Support\Bdd;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

/**
 * Membaca definisi business rule dari dokumen DDD. Hanya baris tabel yang
 * dihitung, penyebutan di prosa diabaikan: guard jadi bisa membedakan "rule
 * ini ada" dari "kebetulan disebut" — tanpa itu typo di tag lolos terus.
 */
final class BusinessRuleCatalog
{
    private const DEFINITION_PATTERN = '/^\|\s*(BR-[A-Z]+-\d+)\s*\|/m';

    public function __construct(private readonly string $docRoot) {}

    /**
     * @return list<string> kode BR, unik dan terurut
     */
    public function codes(): array
    {
        if (!is_dir($this->docRoot)) {
            throw new RuntimeException("Direktori DDD tidak ditemukan: {$this->docRoot}");
        }

        $codes = [];

        foreach ($this->markdownFiles() as $file) {
            $contents = file_get_contents($file);

            if ($contents === false) {
                throw new RuntimeException("Gagal membaca {$file}");
            }

            if (preg_match_all(self::DEFINITION_PATTERN, $contents, $matches) === 0) {
                continue;
            }

            foreach ($matches[1] as $code) {
                $codes[$code] = true;
            }
        }

        $result = array_keys($codes);
        sort($result);

        return $result;
    }

    /**
     * @return list<string> path absolut setiap berkas .md di bawah docRoot
     */
    private function markdownFiles(): array
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->docRoot, FilesystemIterator::SKIP_DOTS)
        );

        $files = [];

        foreach ($iterator as $entry) {
            // Guard eksplisit, bukan anotasi @var: iterator memberi `mixed`, dan
            // menyempitkannya lewat instanceof memasok tipe tanpa membungkam apa pun.
            if (!$entry instanceof SplFileInfo) {
                continue;
            }

            if ($entry->isFile() && $entry->getExtension() === 'md') {
                $files[] = $entry->getPathname();
            }
        }

        sort($files);

        return $files;
    }
}
