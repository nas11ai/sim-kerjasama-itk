<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormInput;
use App\Http\Requests\Concerns\ReadsFormFields;
use App\Models\Form;
use App\Models\FormType;
use App\Queries\Form\FormTypeOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;

/**
 * Buat dan Ubah Form. Field hanya ikut saat buat, sebagai draf pertama (BR-FE-34); Ubah hanya
 * kartu General, field lewat FormDraftRequest.
 */
class FormDefinitionRequest extends FormRequest
{
    use ReadsFormFields;

    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|list<string|NotIn>> */
    public function rules(): array
    {
        $general = [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type_id' => $this->formTypeRules(),
            'is_active' => 'boolean',
            'continue' => 'boolean',
        ];

        return $this->route('form') instanceof Form ? $general : [...$general, ...$this->fieldRules()];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['form_type_id.not_in' => 'Tipe Biodata tidak tersedia untuk formulir ini.', ...$this->fieldMessages()];
    }

    /**
     * W80 — Biodata tidak bisa dipilih untuk Form baru maupun sebagai tipe pengganti;
     * Form yang sudah bertipe Biodata boleh disimpan dengan tipenya sendiri.
     *
     * @return list<string|NotIn>
     */
    private function formTypeRules(): array
    {
        $rules = ['required', 'integer', 'exists:form_types,id'];
        $form = $this->route('form');

        if (FormTypeOptions::keepsBiodata($form instanceof Form ? $form : null)) {
            return $rules;
        }

        return [...$rules, Rule::notIn([FormType::BIODATA_ID])];
    }

    public function toInput(): FormInput
    {
        return new FormInput(
            title: $this->string('title')->value(),
            description: $this->filled('description') ? $this->string('description')->value() : null,
            formTypeId: $this->integer('form_type_id'),
            isActive: $this->boolean('is_active', $this->currentIsActive()),
        );
    }

    /** Tanpa kiriman is_active: sunting mempertahankan status Form, buat baru aktif. */
    private function currentIsActive(): bool
    {
        $form = $this->route('form');

        return $form instanceof Form ? $form->is_active : true;
    }
}
