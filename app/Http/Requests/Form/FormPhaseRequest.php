<?php

namespace App\Http\Requests\Form;

use App\Data\Request\FormPhaseInput;
use Illuminate\Foundation\Http\FormRequest;

/** BR-FE-33 — bagian tahap yang disunting langsung: judul, deskripsi, status aktif. */
class FormPhaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            // B5 — id Form tapis pustaka asal; hanya menentukan tujuan redirect.
            'form' => 'nullable|integer|exists:forms,id',
        ];
    }

    public function toInput(): FormPhaseInput
    {
        return new FormPhaseInput(
            title: $this->string('title')->value(),
            description: $this->filled('description') ? $this->string('description')->value() : null,
            isActive: $this->boolean('is_active'),
        );
    }
}
