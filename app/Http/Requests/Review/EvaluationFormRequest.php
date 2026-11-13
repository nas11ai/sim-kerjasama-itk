<?php

namespace App\Http\Requests\Review;

use App\Data\Request\EvaluationFormDetailsInput;
use Illuminate\Foundation\Http\FormRequest;

/** Buat dan ubah nama pustaka Formulir Evaluasi: judul dan deskripsi, tak berversi (BR-FE-31). */
class EvaluationFormRequest extends FormRequest
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
        ];
    }

    public function toInput(): EvaluationFormDetailsInput
    {
        return new EvaluationFormDetailsInput(
            title: $this->string('title')->value(),
            description: $this->filled('description') ? $this->string('description')->value() : null,
        );
    }
}
