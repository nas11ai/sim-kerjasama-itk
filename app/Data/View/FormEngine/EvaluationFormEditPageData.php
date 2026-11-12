<?php

namespace App\Data\View\FormEngine;

use Spatie\LaravelData\Data;

/**
 * Editor pustaka Form Evaluasi. `fields` = isi draf bila ada, kalau tidak isi versi terbit
 * terakhir (BR-FE-31). `impact` = kalimat konfirmasi Terbitkan (§4.5).
 */
final class EvaluationFormEditPageData extends Data
{
    /**
     * @param  list<EvaluationFieldEditData>  $fields
     * @param  list<array{label: string, publishedAt: string, publishedBy: string|null}>  $versions  terbaru dulu
     * @param  list<array{phaseId: int, phaseTitle: string, stepLabel: string}>  $usedBy
     * @param  array{nextLabel: string, pinnedCohorts: int}  $impact
     */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $description,
        public array $fields,
        public bool $hasDraft,
        public ?string $latestVersionLabel,
        public array $versions,
        public array $usedBy,
        public array $impact,
    ) {}
}
