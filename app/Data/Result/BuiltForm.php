<?php

namespace App\Data\Result;

use App\Models\Form;
use App\Models\SubmissionDate;

/** Hasil wizard form builder: form-nya dan tenggat yang dipakai seluruh detail barunya. */
final class BuiltForm
{
    public function __construct(
        public readonly Form $form,
        public readonly SubmissionDate $deadline,
    ) {}
}
