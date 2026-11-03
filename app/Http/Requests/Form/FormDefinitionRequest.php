<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormFieldInput;
use App\Data\Request\FormInput;
use App\Models\Form;
use App\Models\FormType;
use App\Queries\Form\FormTypeOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\NotIn;

/**
 * Buat dan Ubah Form. `id` field dan opsi hanya diperiksa keberadaannya di sini;
 * kepemilikannya oleh Form yang disunting diperiksa ReviseForm.
 */
class FormDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string|list<string|NotIn>> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'form_type_id' => $this->formTypeRules(),
            'is_active' => 'boolean',
            'continue' => 'boolean',
            'fields' => 'array',
            'fields.*.id' => 'nullable|integer|exists:form_fields,id',
            'fields.*.field_type_id' => 'required|integer|exists:field_types,id',
            'fields.*.label' => 'required|string|max:255',
            'fields.*.is_required' => 'boolean',
            'fields.*.options' => 'array',
            'fields.*.options.*.id' => 'nullable|integer|exists:form_field_options,id',
            'fields.*.options.*.label' => 'required|string|max:255',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['form_type_id.not_in' => 'Tipe Biodata tidak tersedia untuk form ini.'];
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
            isActive: $this->boolean('is_active', true),
            fields: $this->has('fields') ? array_map($this->field(...), $this->indexesOf('fields')) : null,
        );
    }

    private function field(int|string $i): FormFieldInput
    {
        $key = "fields.{$i}";

        return new FormFieldInput(
            id: $this->optionalId("{$key}.id"),
            fieldTypeId: $this->integer("{$key}.field_type_id"),
            label: $this->string("{$key}.label")->value(),
            isRequired: $this->boolean("{$key}.is_required"),
            options: $this->has("{$key}.options") ? array_map(fn (int|string $o): array => [
                'id' => $this->optionalId("{$key}.options.{$o}.id"),
                'label' => $this->string("{$key}.options.{$o}.label")->value(),
            ], $this->indexesOf("{$key}.options")) : null,
        );
    }

    private function optionalId(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }

    /** @return list<int|string> */
    private function indexesOf(string $key): array
    {
        return array_keys($this->array($key));
    }
}
