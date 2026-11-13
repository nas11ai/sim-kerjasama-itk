<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Review\ReviewHistoryRowData;
use App\Models\SubmissionReviewer;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * REVIEWER-03-02 — semua penugasan reviewer milik pemanggil, termasuk yang digantikan
 * (REV-REASSIGN-02) dan siklus monev lama (MON-REVIEW-04): record lama tak pernah dipakai ulang.
 *
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{history: LengthAwarePaginator<int, ReviewHistoryRowData>, yearOptions: list<Option>} run(User $viewer, string $search, ?int $year)
 */
class ReviewerHistory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /** @return array{history: LengthAwarePaginator<int, ReviewHistoryRowData>, yearOptions: list<Option>} */
    public function handle(User $viewer, string $search, ?int $year): array
    {
        $history = $this->mine($viewer)
            ->with([
                'formSubmission.form:id,title',
                'formSubmission.scheme:id,name',
                'formSubmission.submissionPeriod:id,name',
                'formSubmission.formPhaseDetail.formPhase:id,title',
                'reviewerFormAssignments.reviewFormResponse:id,reviewer_form_assignment_id,submitted_at',
            ])
            // BR-SM-22 — judul pengajuan atau, bila belum ada, judul Form-nya.
            ->when($search !== '', fn (Builder $q) => $q->whereHas('formSubmission', fn (Builder $submission) => $submission
                ->whereLike('title', "%{$search}%")
                ->orWhereHas('form', fn (Builder $form) => $form->whereLike('title', "%{$search}%"))))
            ->when($year !== null, fn (Builder $q) => $q->whereYear('created_at', $year))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(ReviewHistoryRowData::of(...));

        return ['history' => $history, 'yearOptions' => $this->yearOptions($viewer)];
    }

    /** @return Builder<SubmissionReviewer> */
    private function mine(User $viewer): Builder
    {
        return SubmissionReviewer::query()->whereHas('reviewer', fn (Builder $q) => $q->where('user_id', $viewer->id));
    }

    /** @return list<Option> */
    private function yearOptions(User $viewer): array
    {
        return array_values($this->mine($viewer)->get(['id', 'created_at'])
            ->map(fn (SubmissionReviewer $assignment): string => $assignment->created_at?->format('Y') ?? '')
            ->filter()
            ->unique()
            ->sortDesc()
            ->map(fn (string $year): array => ['value' => $year, 'label' => $year])
            ->all());
    }
}
