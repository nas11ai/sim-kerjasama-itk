<?php

namespace App\Data\View\ResearchOutput;

use Spatie\LaravelData\Data;

/** Props ResearchOutput/ShowPage (RESEARCHER-06-03). Tombol Edit mengikuti `access` (W146). */
final class OutputDetailData extends Data
{
    /**
     * @param  list<OutputDetailFieldData>  $fields
     * @param  list<OutputFileData>  $files
     */
    public function __construct(
        public int $id,
        public int $submissionId,
        public string $submissionTitle,
        public string $typeName,
        public string $headline,
        public array $fields,
        public array $files,
        public OutputWriteAccessData $access,
    ) {}
}
