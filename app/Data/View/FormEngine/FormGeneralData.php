<?php

namespace App\Data\View\FormEngine;

use App\Queries\Form\FormPublishImpact;
use Spatie\LaravelData\Data;

/**
 * Isi tab General & Fields. `fields` = isi draf bila ada, kalau tidak field terbit (BR-FE-34);
 * `publishedFields` pembanding peringatan label (G3-W9). `impact.runningDrafts` = penerima BR-FE-13.
 *
 * @phpstan-import-type Impact from FormPublishImpact
 */
final class FormGeneralData extends Data
{
    /**
     * @param  list<FormFieldEditData>  $fields
     * @param  list<FormFieldEditData>  $publishedFields
     * @param  list<ArchivedFieldData>  $archivedFields
     * @param  Impact  $impact
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public ?int $formTypeId,
        public bool $isActive,
        public bool $hasDraft,
        public ?string $publishedAt,
        public array $fields,
        public array $publishedFields,
        public array $archivedFields,
        public array $impact,
    ) {}
}
