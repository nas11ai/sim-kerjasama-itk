<?php

namespace App\Data\View\ResearchOutput;

use Spatie\LaravelData\Data;

/**
 * Props ResearchOutput/FormPage (RESEARCHER-06-02 tambah, 06-03 sunting). `outputId` null =
 * tambah. Field metadata dirender dari `types[].fields`, bukan ditulis tangan di UI (BR-RO-09).
 */
final class OutputFormData extends Data
{
    /**
     * @param  list<OutputTypeData>  $types
     * @param  array<string, string|int|null>  $metadata
     * @param  list<OutputFileData>  $files
     */
    public function __construct(
        public int $submissionId,
        public string $submissionTitle,
        public ?string $scheme,
        public OutputWriteAccessData $access,
        public array $types,
        public ?int $outputId,
        public ?string $outputType,
        public array $metadata,
        public array $files,
    ) {}
}
