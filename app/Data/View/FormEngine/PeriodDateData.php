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
        public string $label,
        public string $at,
        public string $inputValue,
        public bool $passed,
        public int $phaseDetailCount,
    ) {}

    /** Relasi `submissionDateLabel` dan hitungan `form_phase_details_count` dimuat SubmissionPeriodList. */
    public static function fromModel(SubmissionDate $date): self
    {
        // Cast `datetime` Eloquent sudah berada di zona aplikasi.
        $local = $date->datetime;

        return new self(
            id: $date->id,
            label: $date->submissionDateLabel->name ?? '',
            at: $local->toIso8601String(),
            inputValue: $local->format('Y-m-d\TH:i'),
            passed: $date->isPassed(),
            phaseDetailCount: $date->form_phase_details_count,
        );
    }
}
