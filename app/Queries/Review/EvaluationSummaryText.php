<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormResponse;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Rangkuman teks polos satu evaluasi yang sudah dikirim, untuk diunduh reviewernya.
 *
 * @method static string run(ReviewerFormAssignment $assignment, ReviewFormResponse $response)
 */
class EvaluationSummaryText implements ChangesNoState
{
    use AsAction;

    public function handle(ReviewerFormAssignment $assignment, ReviewFormResponse $response): string
    {
        $content = $this->header($assignment, $response).$this->answers($assignment, $response);

        if ($response->final_notes) {
            $content .= str_repeat('-', 50)."\n";
            $content .= "Catatan Tambahan:\n";
            $content .= $response->final_notes."\n\n";
        }

        $content .= str_repeat('=', 50)."\n";

        return $content.'Generated on: '.now()->format('d M Y H:i:s')."\n";
    }

    private function header(ReviewerFormAssignment $assignment, ReviewFormResponse $response): string
    {
        $submission = $assignment->submissionReviewer->formSubmission;
        $reviewer = $assignment->submissionReviewer->reviewer;

        $content = "RANGKUMAN EVALUASI\n";
        $content .= str_repeat('=', 50)."\n\n";
        $content .= "Pengajuan: {$submission->form->title}\n";
        $content .= "Dikirim oleh: {$submission->submittedBy->name}\n";
        $content .= 'Reviewer: '.($reviewer->user->name ?? '-')." ({$reviewer->reviewer_type})\n";
        $content .= "Formulir Evaluasi: {$assignment->reviewEvaluationForm->title}\n";
        $content .= 'Diselesaikan pada: '.$response->submitted_at?->format('d M Y H:i')."\n\n";

        return $content.str_repeat('-', 50)."\n\n";
    }

    private function answers(ReviewerFormAssignment $assignment, ReviewFormResponse $response): string
    {
        $answers = $response->reviewFormFieldResponses()->with('reviewFormField')->get()->keyBy('review_form_field_id');
        $content = '';

        foreach ($assignment->reviewEvaluationForm->reviewFormFields()->orderBy('order')->get() as $field) {
            $answer = $answers->get($field->id);

            $content .= "{$field->label}\n";
            $content .= $field->description ? "Deskripsi: {$field->description}\n" : '';
            $content .= $answer !== null && $answer->hasValue()
                ? "Response: {$answer->formatted_value}\n\n"
                : "Response: [No response]\n\n";
        }

        return $content;
    }
}
