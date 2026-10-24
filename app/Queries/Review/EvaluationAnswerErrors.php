<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewFormField;
use App\Models\ReviewFormFieldResponse;
use App\Models\ReviewFormResponse;
use App\Support\Review\FieldResponseValidator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Galat yang menahan sebuah evaluasi dikirim: field wajib yang kosong, lalu aturan tipe dan
 * aturan kustom setiap jawaban yang terisi. Field opsional ikut diperiksa — dulu hanya field
 * wajib, sehingga isian email atau angka yang keliru di field opsional lolos.
 *
 * @method static list<string> run(ReviewFormResponse $response)
 */
class EvaluationAnswerErrors implements ChangesNoState
{
    use AsAction;

    /** @return list<string> */
    public function handle(ReviewFormResponse $response): array
    {
        $answers = $response->reviewFormFieldResponses()
            ->with('reviewFormField.fieldType')
            ->get()
            ->keyBy('review_form_field_id');

        $fields = $response->reviewerFormAssignment->reviewEvaluationForm
            ->reviewFormFields()
            ->orderBy('order')
            ->get();

        $errors = [];

        foreach ($fields as $field) {
            array_push($errors, ...$this->errorsOf($field, $answers->get($field->id)));
        }

        return $errors;
    }

    /** @return list<string> */
    private function errorsOf(ReviewFormField $field, ?ReviewFormFieldResponse $answer): array
    {
        if ($answer !== null && $answer->hasValue()) {
            return array_values(FieldResponseValidator::errorsFor($answer));
        }

        return $field->is_required ? ["Field '{$field->label}' wajib diisi."] : [];
    }
}
