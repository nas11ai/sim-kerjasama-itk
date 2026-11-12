<?php

namespace App\Http\Requests\Form;

use App\Http\Requests\Concerns\ReadsFormFields;
use Illuminate\Foundation\Http\FormRequest;

/** BR-FE-34 — isi draf field Form; route dijaga `can:schemes.manage`. */
class FormDraftRequest extends FormRequest
{
    use ReadsFormFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return $this->fieldRules();
    }
}
