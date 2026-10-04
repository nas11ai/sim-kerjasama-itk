<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormAccessInput;
use App\Models\FormAccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

class FormAccessBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In|Exists>> */
    public function rules(): array
    {
        return [
            'form_id' => ['required', 'integer', 'exists:forms,id'],
            'combinations' => ['required', 'array', 'min:1'],
            'combinations.*.permission' => ['required', 'string', Rule::in(FormAccessControl::ALLOWED_PERMISSIONS)],
            // ADR 0013 — sama dengan buat tunggal: organisasi aktif bertipe apa pun.
            'combinations.*.study_program_id' => [
                'required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true),
            ],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['combinations.*.study_program_id.exists' => 'Organisasi tidak ditemukan atau sudah nonaktif.'];
    }

    /** @return list<FormAccessInput> */
    public function toInputs(): array
    {
        $grants = [];

        foreach (array_keys($this->array('combinations')) as $index) {
            $grants[] = new FormAccessInput(
                formId: $this->integer('form_id'),
                permission: $this->string("combinations.{$index}.permission")->value(),
                organizationId: $this->integer("combinations.{$index}.study_program_id"),
            );
        }

        return $grants;
    }
}
