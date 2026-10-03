<?php

namespace App\Queries\Review;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\ReviewCommentAttachment;
use App\Models\ReviewSummaryAttachment;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-07 — pengajuan pemilik satu lampiran review. `null` bila berkas itu bukan
 * lampiran ulir atau komentar mana pun.
 *
 * @method static FormSubmission|null run(string $path)
 */
class ReviewAttachmentSubmission implements ChangesNoState
{
    use AsAction;

    public function handle(string $path): ?FormSubmission
    {
        $summaryAttachment = ReviewSummaryAttachment::query()->where('file_path', $path)->first();

        if ($summaryAttachment !== null) {
            return $summaryAttachment->reviewSummary->formSubmission;
        }

        $commentAttachment = ReviewCommentAttachment::query()->where('file_path', $path)->first();

        return $commentAttachment?->reviewComment->reviewSummary->formSubmission;
    }
}
