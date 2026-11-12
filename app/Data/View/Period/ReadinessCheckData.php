<?php

namespace App\Data\View\Period;

use App\Enums\ReadinessCheck;
use Spatie\LaravelData\Data;

/** Satu baris cek kesiapan angkatan (BR-FE-36); `state` ikut terkirim ke layar. */
final class ReadinessCheckData extends Data
{
    /** @param  list<ReadinessIssueData>  $issues */
    public function __construct(
        public string $check,
        public string $title,
        public array $issues,
    ) {}

    /** @param  list<ReadinessIssueData>  $issues */
    public static function of(ReadinessCheck $check, array $issues): self
    {
        return new self(check: $check->value, title: $check->title(), issues: $issues);
    }

    public function hasBlockingIssues(): bool
    {
        return array_any($this->issues, static fn (ReadinessIssueData $issue): bool => $issue->blocking);
    }

    /** @return 'passed'|'warning'|'failed' */
    public function state(): string
    {
        return match (true) {
            $this->issues === [] => 'passed',
            $this->hasBlockingIssues() => 'failed',
            default => 'warning',
        };
    }

    /** @return array{state: string} */
    public function with(): array
    {
        return ['state' => $this->state()];
    }
}
