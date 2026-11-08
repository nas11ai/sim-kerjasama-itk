<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewComment;
use App\Models\ReviewSummary;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Ulir review sebuah pengajuan, komentar teratasnya beserta balasan, dan statistik ringkasnya.
 *
 * @phpstan-type ThreadStats array{total_reviewers: int, open_reviews: int, resolved_reviews: int, total_comments: int}
 *
 * @method static array{summaries: Collection<int, ReviewSummary>, comments: Collection<int, ReviewComment>, stats: ThreadStats} run(FormSubmission $submission)
 */
class ReviewThreads implements ChangesNoState
{
    use AsAction;

    /** @return array{summaries: Collection<int, ReviewSummary>, comments: Collection<int, ReviewComment>, stats: ThreadStats} */
    public function handle(FormSubmission $submission): array
    {
        // Kolom terpilih wajib memuat kunci relasi (id, user_id); tanpanya Eloquent tak bisa
        // mencocokkan baris, relasinya menjadi null, dan nama di ulir hilang dari layar.
        $summaries = $submission->reviewSummaries()
            ->with([
                'reviewer:id,user_id,reviewer_type',
                'reviewer.user:id,name',
                'author:id,user_id',
                'author.user:id,name',
                'attachments',
            ])
            ->get();

        $comments = ReviewComment::query()
            ->whereIn('review_summary_id', $summaries->modelKeys())
            ->whereNull('parent_comment_id')
            ->with([
                'user:id,name',
                'reviewer.user:id,name',
                'attachments',
                'replies' => fn (Relation $q) => $q->with(['user:id,name', 'reviewer.user:id,name', 'attachments'])
                    ->orderBy('created_at', 'asc'),
            ])
            ->orderBy('created_at', 'desc')
            ->get();

        return [
            'summaries' => $summaries,
            'comments' => $comments,
            'stats' => [
                'total_reviewers' => $submission->activeSubmissionReviewers()->count(),
                'open_reviews' => $summaries->where('status', 'open')->count(),
                'resolved_reviews' => $summaries->where('status', 'resolved')->count(),
                'total_comments' => $comments->count(),
            ],
        ];
    }
}
