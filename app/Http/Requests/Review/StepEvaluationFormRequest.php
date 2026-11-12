<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/** Wajib dan aktif satu pemasangan Form Evaluasi (G2-W2, BR-FE-22). */
class StepEvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'is_required' => 'required|boolean',
            'is_active' => 'required|boolean',
        ];
    }
}
