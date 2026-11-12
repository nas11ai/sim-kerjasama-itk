<?php

namespace App\Http\Requests\Form;

use App\Http\Requests\Concerns\ReadsPhaseSteps;

/** BR-FE-07 + BR-FE-33 — tahap baru: bagian langsungnya beserta langkah draf pertamanya. */
class CreateFormPhaseRequest extends FormPhaseRequest
{
    use ReadsPhaseSteps;

    /** @return array<string, string> */
    public function rules(): array
    {
        return [...parent::rules(), ...$this->stepRules()];
    }
}
