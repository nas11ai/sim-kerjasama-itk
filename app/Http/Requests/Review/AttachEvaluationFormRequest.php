<?php

namespace App\Http\Requests\Review;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Pasang Form Evaluasi pustaka di satu langkah (BR-FE-30). Langkahnya dari route, bukan kiriman
 * (W100); sudah-terbit dan belum-terpasang diperiksa AttachEvaluationForm.
 */
class AttachEvaluationFormRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'review_evaluation_form_id' => 'required|integer|exists:review_evaluation_forms,id',
            'is_required' => 'boolean',
        ];
    }
}
