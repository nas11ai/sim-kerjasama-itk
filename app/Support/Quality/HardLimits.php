<?php

namespace App\Support\Quality;

/**
 * Satu-satunya definisi "melewati ambang keras". Pembangkit daftar legacy dan
 * QualityBudgetTest memakai kelas yang sama, jadi keduanya tidak bisa berselisih.
 */
final class HardLimits
{
    /** @var array<string, int>|null path => jumlah bloknya yang juga ada di berkas lain */
    private ?array $shared = null;

    /** @param list<string> $corpus seluruh berkas yang diukur, path absolut */
    public function __construct(
        private readonly BudgetFile $budget,
        private readonly array $corpus,
    ) {}

    /** @return list<string> seluruh alasan berkas masih legacy; kosong berarti boleh keluar daftar */
    public function violations(string $path): array
    {
        $shared = $this->sharedBlocks($path);
        $reasons = $this->metricViolations($path);

        return $shared === 0 ? $reasons : [...$reasons, "berbagi {$shared} blok dengan berkas lain"];
    }

    /** Spec §3.1 — berkas tidak boleh memuat blok enam baris yang juga ada di berkas lain. */
    public function sharedBlocks(string $path): int
    {
        $this->shared ??= DuplicationScanner::filesSharing($this->corpus);

        return $this->shared[$path] ?? 0;
    }

    /** @return list<string> alasan pelanggaran ukuran dan bentuk, tanpa duplikasi */
    public function metricViolations(string $path): array
    {
        if (str_ends_with($path, '.vue')) {
            return VueMetrics::loc($path) > $this->budget->value('hard.vue.file.loc') ? ['ukuran berkas'] : [];
        }

        $metrics = CodeMetrics::forFile($path);
        $reasons = [];

        if ($metrics['loc'] > $this->budget->value('hard.php.file.loc')) {
            $reasons[] = 'ukuran berkas';
        }

        if (CommentMetrics::longestProseRun($path) > $this->budget->value('hard.comment.prose')) {
            $reasons[] = 'prosa komentar terlalu panjang';
        }

        foreach ($metrics['methods'] as $method) {
            array_push($reasons, ...$this->methodViolations($method));
        }

        return array_values(array_unique($reasons));
    }

    /** @return list<string> */
    private function methodViolations(MethodMetric $method): array
    {
        $tooManyParameters = $method->parameters > $this->budget->value('hard.method.params')
            && !$method->onlyPromotesProperties();

        $checks = [
            'terlalu panjang' => $method->loc > $this->budget->value('hard.method.loc'),
            'terlalu kompleks' => $method->complexity > $this->budget->value('hard.method.cc'),
            'terlalu banyak parameter' => $tooManyParameters,
            'terlalu banyak return' => $method->returns > $this->budget->value('hard.method.returns'),
        ];

        return array_map(
            static fn (string $reason): string => "{$method->name}() {$reason}",
            array_keys(array_filter($checks)),
        );
    }
}
