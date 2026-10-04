<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Review\ReviewerAssignmentListing;
use App\Models\Reviewer;
use App\Models\ReviewerFormAssignment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar form evaluasi milik satu reviewer: tersaring, terurut, dan bersama statistiknya.
 * Statistik dihitung di SQL dengan definisi selesai/terlambat yang sama dengan filternya.
 *
 * @method static ReviewerAssignmentListing run(Reviewer $reviewer, ?string $status, ?string $search)
 */
class ReviewerAssignmentList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    public function handle(Reviewer $reviewer, ?string $status, ?string $search): ReviewerAssignmentListing
    {
        $mine = $this->ownedBy($reviewer);

        $filtered = (clone $mine)->with([
            'reviewEvaluationForm:id,title,description',
            'submissionReviewer.formSubmission.form:id,title',
            'submissionReviewer.formSubmission.submittedBy:id,name',
            'reviewFormResponse:id,reviewer_form_assignment_id,status,submitted_at',
        ]);
        $this->filterByStatus($filtered, $status);
        $this->filterBySearch($filtered, $search);

        // Terlambat dulu, lalu yang bertenggat, lalu yang tanpa tenggat.
        $page = $filtered->orderByRaw('
            CASE
                WHEN due_date IS NOT NULL AND due_date < NOW() THEN 1
                WHEN due_date IS NOT NULL THEN 2
                ELSE 3
            END
        ')
            ->orderBy('due_date', 'asc')
            ->paginate(self::PER_PAGE);

        return new ReviewerAssignmentListing($page, $this->statsFor($mine));
    }

    /** @return Builder<ReviewerFormAssignment> */
    private function ownedBy(Reviewer $reviewer): Builder
    {
        // BR-SM-07, BR-REV-10 — form milik penugasan yang sudah diganti tidak terdaftar.
        // `whereIn`, bukan `=`: reviewer biasanya bertugas di lebih dari satu pengajuan.
        return ReviewerFormAssignment::query()
            ->whereIn('submission_reviewer_id', function (QueryBuilder $subQuery) use ($reviewer): void {
                $subQuery->select('id')
                    ->from('submission_reviewers')
                    ->where('reviewer_id', $reviewer->id)
                    ->where('status', 'active');
            });
    }

    /** @param  Builder<ReviewerFormAssignment>  $query */
    private function filterByStatus(Builder $query, ?string $status): void
    {
        match ($status) {
            'pending' => $this->notSubmitted($query),
            'completed' => $this->submitted($query),
            'overdue' => $this->notSubmitted($query->where('due_date', '<', now())),
            default => null,
        };
    }

    /** @param  Builder<ReviewerFormAssignment>  $query */
    private function filterBySearch(Builder $query, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $query->where(function (Builder $q) use ($search): void {
            $q->whereHas('reviewEvaluationForm', fn (Builder $form) => $form->whereLike('title', "%{$search}%"))
                ->orWhereHas(
                    'submissionReviewer.formSubmission.form',
                    fn (Builder $form) => $form->whereLike('title', "%{$search}%"),
                );
        });
    }

    /**
     * @param  Builder<ReviewerFormAssignment>  $mine
     * @return array{total: int, pending: int, completed: int, overdue: int}
     */
    private function statsFor(Builder $mine): array
    {
        $total = (clone $mine)->count();
        $completed = $this->submitted(clone $mine)->count();
        $overdue = $this->notSubmitted((clone $mine)->where('due_date', '<', now()))->count();

        return [
            'total' => $total,
            'pending' => $total - $completed,
            'completed' => $completed,
            'overdue' => $overdue,
        ];
    }

    /**
     * @param  Builder<ReviewerFormAssignment>  $query
     * @return Builder<ReviewerFormAssignment>
     */
    private function submitted(Builder $query): Builder
    {
        return $query->whereHas('reviewFormResponse', fn (Builder $q) => $q->where('status', 'submitted'));
    }

    /**
     * @param  Builder<ReviewerFormAssignment>  $query
     * @return Builder<ReviewerFormAssignment>
     */
    private function notSubmitted(Builder $query): Builder
    {
        return $query->whereDoesntHave('reviewFormResponse', fn (Builder $q) => $q->where('status', 'submitted'));
    }
}
