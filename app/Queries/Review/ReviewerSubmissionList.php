<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-07, BR-REV-10 — pengajuan yang sedang ditugaskan kepada reviewer, tersaring status
 * evaluasinya dan nama pengaju atau judul form. Penugasan yang sudah diganti tidak terdaftar.
 *
 * @method static LengthAwarePaginator<int, FormSubmission> run(Reviewer $reviewer, ?string $status, ?string $search)
 */
class ReviewerSubmissionList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /** @return LengthAwarePaginator<int, FormSubmission> */
    public function handle(Reviewer $reviewer, ?string $status, ?string $search): LengthAwarePaginator
    {
        $mine = fn (Builder $q) => $q->where('reviewer_id', $reviewer->id)->where('status', 'active');

        return FormSubmission::query()
            ->whereHas('submissionReviewers', $mine)
            ->with(['form:id,title', 'submittedBy:id,name,email', 'reviewSummaries', 'submissionReviewers'])
            ->when($status !== null && $status !== '', fn (Builder $q) => $q->whereHas(
                'submissionReviewers',
                fn (Builder $sr) => $mine($sr)->where('evaluation_status', $status),
            ))
            ->when($search !== null && $search !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
                ->whereHas('submittedBy', fn (Builder $user) => $user->whereLike('name', "%{$search}%"))
                ->orWhereHas('form', fn (Builder $form) => $form->whereLike('title', "%{$search}%"))))
            ->orderBy('created_at', 'desc')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
