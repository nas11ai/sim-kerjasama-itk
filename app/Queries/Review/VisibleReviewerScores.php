<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewFormField;
use App\Models\ReviewFormResponse;
use App\Models\SubmissionReviewer;
use App\Models\User;
use App\Support\ScoreVisibility;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-REV-11 dan BR-REV-12 — hasil evaluasi yang boleh dibaca pemanggil. Dua sumbu yang sengaja
 * tidak dicampur: rekan reviewer internal setelah semua evaluasi selesai, peneliti setelah
 * keputusan final. Yang tidak lolos menerima daftar kosong beserta alasannya.
 *
 * @phpstan-type Score array{reviewer: string, reviewer_type: string, submitted_at: string|null, answers: list<array{label: string, value: string|null}>}
 *
 * @method static array{visible: bool, reason: string|null, scores: list<Score>} run(User $user, FormSubmission $submission)
 */
class VisibleReviewerScores implements ChangesNoState
{
    use AsAction;

    /** @return array{visible: bool, reason: string|null, scores: list<Score>} */
    public function handle(User $user, FormSubmission $submission): array
    {
        $asPeer = ScoreVisibility::peerScoresVisibleTo($user, $submission);
        $asOwner = ScoreVisibility::visibleToResearcher($user, $submission);

        if (!$asPeer && !$asOwner && !$user->can('submissions.view-all')) {
            return ['visible' => false, 'reason' => $this->hiddenReason($user, $submission), 'scores' => []];
        }

        $scores = array_values(SubmissionReviewer::query()
            ->where('form_submission_id', $submission->id)
            ->where('status', 'active')
            ->with([
                'reviewer.user:id,name',
                'reviewerFormAssignments.reviewFormResponse.reviewFormFieldResponses.reviewFormField:id,label',
            ])
            ->get()
            ->flatMap(fn (SubmissionReviewer $assignment): array => $assignment->reviewerFormAssignments
                ->map(fn (ReviewerFormAssignment $form): ?array => $this->score($assignment, $form))
                ->filter()
                ->all())
            ->all());

        return ['visible' => true, 'reason' => null, 'scores' => $scores];
    }

    /** @return Score|null */
    private function score(SubmissionReviewer $assignment, ReviewerFormAssignment $form): ?array
    {
        $response = $form->reviewFormResponse;

        // Evaluasi yang belum disubmit belum menjadi penilaian sama sekali.
        if ($response === null || $response->status !== 'submitted') {
            return null;
        }

        return [
            'reviewer' => $assignment->reviewer->user->name ?? 'Reviewer',
            'reviewer_type' => $assignment->reviewer->reviewer_type,
            'submitted_at' => $response->submitted_at?->toDateTimeString(),
            'answers' => $this->answers($response),
        ];
    }

    /** @return list<array{label: string, value: string|null}> */
    private function answers(ReviewFormResponse $response): array
    {
        $answers = [];

        foreach ($response->reviewFormFieldResponses as $answer) {
            // Jawaban tanpa field-nya tidak punya label untuk dibaca; tidak ditampilkan.
            if ($answer->reviewFormField instanceof ReviewFormField) {
                $answers[] = ['label' => $answer->reviewFormField->label, 'value' => $answer->value];
            }
        }

        return $answers;
    }

    private function hiddenReason(User $user, FormSubmission $submission): string
    {
        if ($submission->submitted_by === $user->id) {
            // BR-REV-12 — bukan "review selesai", melainkan keputusan final.
            return 'Hasil review terbuka setelah keputusan akhir atas pengajuan ini diambil.';
        }

        return 'Skor reviewer lain terbuka bagi reviewer internal setelah seluruh evaluasi selesai.';
    }
}
