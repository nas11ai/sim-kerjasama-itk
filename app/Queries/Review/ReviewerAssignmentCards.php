<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Review\AssignmentCardData;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use App\Models\ReviewSummary;
use App\Models\SubmissionReviewer;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REVIEWER-03-01 — penugasan reviewer yang masih berlaku (BR-SM-07, BR-REV-10), satu kartu per
 * pengajuan, terbaru dulu. Perlu tindakan: evaluasi belum beres, atau ulir miliknya masih
 * terbuka sehingga peneliti mungkin menunggu tanggapan (BR-REV-06).
 *
 * @method static array{needsAction: list<AssignmentCardData>, done: list<AssignmentCardData>} run(Reviewer $reviewer)
 */
class ReviewerAssignmentCards implements ChangesNoState
{
    use AsAction;

    private const LABELS = [
        'evaluation_pending' => 'Evaluasi Belum Selesai',
        'discussion_open' => 'Butuh Respon Diskusi',
        'done' => 'Selesai',
    ];

    /** @return array{needsAction: list<AssignmentCardData>, done: list<AssignmentCardData>} */
    public function handle(Reviewer $reviewer): array
    {
        $assignments = SubmissionReviewer::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('status', 'active')
            ->with([
                'formSubmission.form:id,title',
                'formSubmission.scheme:id,name',
                'formSubmission.submittedBy:id,name',
                'reviewerFormAssignments.reviewFormResponse:id,reviewer_form_assignment_id,status',
            ])
            ->orderByDesc('id')
            ->get();

        // Ulir yang diwarisi dari reviewer yang digantikan sudah berpindah reviewer_id (BR-REV-15).
        $discussing = ReviewSummary::query()
            ->where('reviewer_id', $reviewer->id)
            ->where('status', 'open')
            ->whereIn('form_submission_id', $assignments->pluck('form_submission_id'))
            ->pluck('form_submission_id')
            ->all();

        $cards = ['needsAction' => [], 'done' => []];

        foreach ($assignments as $assignment) {
            $card = $this->card($assignment, in_array($assignment->form_submission_id, $discussing, true));
            $cards[$card->state === 'done' ? 'done' : 'needsAction'][] = $card;
        }

        return $cards;
    }

    private function card(SubmissionReviewer $assignment, bool $discussing): AssignmentCardData
    {
        $submission = $assignment->formSubmission;
        $state = match (true) {
            !$assignment->hasSettledEvaluation() => 'evaluation_pending',
            $discussing => 'discussion_open',
            default => 'done',
        };

        return new AssignmentCardData(
            submissionId: $submission->id,
            title: $submission->displayTitle(),
            leadName: $submission->submittedBy->name,
            scheme: $submission->scheme?->name,
            dueDate: $this->nearestDue($assignment),
            state: $state,
            stateLabel: self::LABELS[$state],
        );
    }

    /** Tenggat terdekat di antara form yang belum dikirim; form terkirim tidak ditunggu lagi. */
    private function nearestDue(SubmissionReviewer $assignment): ?string
    {
        return $assignment->reviewerFormAssignments
            ->reject(fn (ReviewerFormAssignment $form): bool => $form->isCompleted() || $form->due_date === null)
            ->sortBy('due_date')
            ->first()
            ?->due_date
            ?->toISOString();
    }
}
