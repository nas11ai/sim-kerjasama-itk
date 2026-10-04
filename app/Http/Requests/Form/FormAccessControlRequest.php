<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormAccessInput;
use App\Models\FormAccessControl;
use Illuminate\Database\Query\Builder;
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
            'form_id' => ['required', 'integer', 'exists:forms,id'],
            'permission' => ['required', 'string', Rule::in(FormAccessControl::ALLOWED_PERMISSIONS)],
            // ADR 0013 — organisasi aktif bertipe apa pun; kuncinya nama lama layar Vue.
            'study_program_id' => ['required', 'integer', $this->activeOrCurrentOrganization()],
        ];
    }

    /**
     * Organisasi yang sudah dipasang pada kontrol akses yang diubah boleh tetap walau
     * kini nonaktif, supaya permission-nya masih bisa diubah; hanya pilihan baru yang
     * wajib aktif.
     */
    private function activeOrCurrentOrganization(): Exists
    {
        $control = $this->route('form_access_control');
        $current = $control instanceof FormAccessControl ? $control->organization_id : null;

        return Rule::exists('organizations', 'id')->where(
            fn (Builder $query) => $query->where('is_active', true)->orWhere('id', $current),
        );
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['study_program_id.exists' => 'Organisasi tidak ditemukan atau sudah nonaktif.'];
    }

    public function toInput(): FormAccessInput
    {
        return new FormAccessInput(
            formId: $this->integer('form_id'),
            permission: $this->string('permission')->value(),
            organizationId: $this->integer('study_program_id'),
        );
    }
}
