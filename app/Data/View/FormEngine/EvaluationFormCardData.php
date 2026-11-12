<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Satu kartu pustaka Form Evaluasi. `latestVersionLabel` null = belum pernah terbit, jadi belum
 * bisa dipasang (BR-FE-30); `hasDraft` = ada isi yang belum diterbitkan (BR-FE-31).
 */
final class EvaluationFormCardData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public ?string $latestVersionLabel,
        public bool $hasDraft,
        public int $stepCount,
        public int $assignmentCount,
    ) {}
}
