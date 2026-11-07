<?php

namespace App\Data\View\ResearchOutput;

use App\Models\OutputTypeDefinition;
use Spatie\LaravelData\Data;

/** BR-RO-02/04/05/09 — tipe luaran yang ditawarkan layar, beserta spesifikasi isinya. */
final class OutputTypeData extends Data
{
    /** @param  list<OutputFieldSpecData>  $fields */
    public function __construct(
        public string $code,
        public string $name,
        public bool $requiresFiles,
        public array $fields,
    ) {}

    public static function of(OutputTypeDefinition $type): self
    {
        return new self($type->code, $type->name, $type->requires_files, $type->fieldSpecs());
    }
}
