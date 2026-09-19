<?php

namespace App\Data\Request;

use Illuminate\Support\ValidatedInput;

/** BR-SC-02 — masukan entity acuan; hanya nama, karena kodenya diturunkan (BR-SC-03). */
final class ReferenceEntityInput
{
    public function __construct(public readonly string $name) {}

    public static function fromValidated(ValidatedInput $input): self
    {
        return new self($input->string('name')->value());
    }
}
