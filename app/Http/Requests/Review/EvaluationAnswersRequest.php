<?php

namespace App\Http\Requests\Review;

use App\Data\Request\EvaluationAnswersInput;
use App\Models\ReviewerFormAssignment;
use Illuminate\Foundation\Http\FormRequest;

/** Jawaban form evaluasi dari layar reviewer, untuk simpan draft maupun kirim. */
class EvaluationAnswersRequest extends FormRequest
{
    public function authorize(): bool
    {
        $assignment = $this->route('assignment');

        // BR-SM-07, BR-REV-10 — hanya reviewer yang penugasannya masih berlaku; admin cuma membaca.
        return $assignment instanceof ReviewerFormAssignment && $this->user()?->can('evaluate', $assignment) === true;
    }

    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*' => 'nullable|string',
            'final_notes' => 'nullable|string|max:2000',
        ];
    }

    public function toInput(): EvaluationAnswersInput
    {
        $answers = [];

        // Kunci yang bukan angka menjadi 0, lalu ditolak penulisnya sebagai field asing.
        foreach ($this->array('responses') as $fieldId => $value) {
            $answers[(int) $fieldId] = is_string($value) ? $value : null;
        }

        return new EvaluationAnswersInput(
            $answers,
            $this->filled('final_notes') ? $this->string('final_notes')->toString() : null,
        );
    }
}
