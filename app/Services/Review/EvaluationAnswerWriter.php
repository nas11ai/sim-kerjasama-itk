<?php

namespace App\Services\Review;

use App\Data\Request\EvaluationAnswersInput;
use App\Models\ReviewFormResponse;
use Illuminate\Validation\ValidationException;

/**
 * Jawaban reviewer ke draft evaluasinya. Dipanggil dari dalam transaksi SaveEvaluationDraft
 * dan SubmitEvaluationAnswers, yang menulis jejak auditnya.
 */
final class EvaluationAnswerWriter
{
    /**
     * @throws ValidationException
     */
    public static function write(ReviewFormResponse $response, EvaluationAnswersInput $input): void
    {
        // BR-FE-05 — evaluasi yang sudah disubmit, atau dikunci tenggatnya, tidak bisa disunting.
        if (!$response->canBeEdited()) {
            throw ValidationException::withMessages(['error' => 'Formulir ini tidak dapat diedit lagi.']);
        }

        self::assertOwnFields($response, $input);

        foreach ($input->answers as $fieldId => $value) {
            $response->saveFieldResponse($fieldId, $value);
        }

        $response->update(['final_notes' => $input->finalNotes]);
    }

    /**
     * Jawaban untuk field form lain dulu tersimpan diam-diam di draft ini dan ikut
     * menaikkan persentase kelengkapannya.
     *
     * @throws ValidationException
     */
    private static function assertOwnFields(ReviewFormResponse $response, EvaluationAnswersInput $input): void
    {
        $fieldIds = array_keys($input->answers);
        // BR-REV-18 — hanya field versi yang diisi jawaban ini.
        $owned = $response->reviewEvaluationFormVersion
            ->reviewFormFields()
            ->whereIn('id', $fieldIds)
            ->count();

        if ($owned === count($fieldIds)) {
            return;
        }

        throw ValidationException::withMessages([
            'responses' => 'Jawaban memuat field yang bukan milik formulir evaluasi ini.',
        ]);
    }
}
