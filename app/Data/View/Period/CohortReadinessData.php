<?php

namespace App\Data\View\Period;

use Spatie\LaravelData\Data;

/**
 * BR-FE-36 — kesiapan satu angkatan (Periode × Tahap) pada versi strukturnya (BR-FE-32).
 * Angkatan berjalan (sudah terpaku) tidak pernah diblokir; `status` ikut terkirim ke layar.
 */
final class CohortReadinessData extends Data
{
    /** @param  list<ReadinessCheckData>  $checks */
    public function __construct(
        public int $periodId,
        public int $phaseId,
        public string $phaseTitle,
        public ?string $versionLabel,
        public bool $running,
        public array $checks,
    ) {}

    public function hasBlockingIssues(): bool
    {
        return array_any($this->checks, static fn (ReadinessCheckData $check): bool => $check->hasBlockingIssues());
    }

    public function blocksNewSubmissions(): bool
    {
        return !$this->running && $this->hasBlockingIssues();
    }

    /** @return 'ready'|'pending'|'blocked'|'needs_fix' */
    public function status(): string
    {
        return match (true) {
            $this->hasBlockingIssues() => $this->running ? 'needs_fix' : 'blocked',
            array_any($this->checks, static fn (ReadinessCheckData $check): bool => $check->issues !== []) => 'pending',
            default => 'ready',
        };
    }

    /** @return array{status: string} */
    public function with(): array
    {
        return ['status' => $this->status()];
    }
}
