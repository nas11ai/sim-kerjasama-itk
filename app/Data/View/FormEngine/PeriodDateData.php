<?php

namespace App\Data\View\FormEngine;

use App\Models\SubmissionDate;
use Spatie\LaravelData\Data;

/**
 * Satu tenggat periode. `inputValue` dalam zona aplikasi untuk `datetime-local`;
 * `phaseDetailCount` > 0 berarti tanggal ini menggerbangi detail tahap (BR-FE-07).
 */
final class PeriodDateData extends Data
{
    public function __construct(
        public int $id,
        public string $type,
        public ?int $cycle,
        public string $label,
        public string $at,
        public string $inputValue,
        public bool $passed,
        public int $phaseDetailCount,
    ) {}

    /** Hitungan detail per kolom tenggat dimuat SubmissionPeriodList (BR-FE-26). */
    public static function fromModel(SubmissionDate $date): self
    {
        // Cast `datetime` Eloquent sudah berada di zona aplikasi.
        $local = $date->datetime;

        return new self(
            id: $date->id,
            type: $date->type->value,
            cycle: $date->cycle,
            label: $date->label(),
            at: $local->toIso8601String(),
            inputValue: $local->format('Y-m-d\TH:i'),
            passed: $date->isPassed(),
            phaseDetailCount: $date->phaseDetailCount(),
        );
    }
}
