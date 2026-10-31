<?php

namespace App\Data\View\Submission\Wizard;

use Spatie\LaravelData\Data;

/**
 * Satu field Form Engine di langkah wizard, beserta jawaban tersimpannya. `required` sudah
 * disaring Temporal Field Binding (BR-FE-11); berkas tidak dikirim balik, hanya nama dan tautannya.
 */
final class WizardFieldData extends Data
{
    /** @param  list<array{value: string, label: string}>  $options */
    public function __construct(
        public int $id,
        public string $label,
        public string $type,
        public bool $required,
        public ?string $value,
        public array $options,
        public ?string $fileName,
        public ?string $downloadUrl,
    ) {}
}
