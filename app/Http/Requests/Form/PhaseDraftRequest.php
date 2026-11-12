<?php

namespace App\Http\Requests\Form;

use App\Http\Requests\Concerns\ReadsPhaseSteps;
use Illuminate\Foundation\Http\FormRequest;

/** BR-FE-33 — isi draf struktur tahap; route dijaga `can:schemes.manage`. */
class PhaseDraftRequest extends FormRequest
{
    use ReadsPhaseSteps;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return $this->stepRules();
    }
}
