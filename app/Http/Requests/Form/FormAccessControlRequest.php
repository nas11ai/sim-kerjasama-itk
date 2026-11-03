<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormAccessInput;
use App\Models\Form;
use App\Models\FormAccessControl;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;
use Illuminate\Validation\Rules\In;

class FormAccessControlRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<string|In|Exists>> */
    public function rules(): array
    {
        return [
            'permission' => ['required', 'string', Rule::in(FormAccessControl::ALLOWED_PERMISSIONS)],
            // ADR 0013 — organisasi aktif bertipe apa pun.
            'organization_id' => ['required', 'integer', Rule::exists('organizations', 'id')->where('is_active', true)],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['organization_id.exists' => 'Organisasi tidak ditemukan atau sudah nonaktif.'];
    }

    public function toInput(Form $form): FormAccessInput
    {
        return new FormAccessInput(
            formId: $form->id,
            permission: $this->string('permission')->value(),
            organizationId: $this->integer('organization_id'),
        );
    }
}
