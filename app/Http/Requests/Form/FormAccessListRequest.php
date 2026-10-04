<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormAccessListInput;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Sengaja tanpa aturan: filter yang tak dikenal cukup tidak menemukan apa pun, supaya
 * tautan lama tidak berujung galat validasi di layar daftar.
 */
class FormAccessListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [];
    }

    public function toInput(): FormAccessListInput
    {
        return new FormAccessListInput(
            formId: $this->string('form_id')->value(),
            permission: $this->string('permission')->value(),
            facultyId: $this->string('faculty_id')->value(),
            organizationId: $this->string('study_program_id')->value(),
            search: $this->string('search')->value(),
        );
    }
}
