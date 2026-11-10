<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-REV-16 — satu reviewer dalam satu putaran, beserta tanda Selesai-nya. Tanda itu tidak
 * bisa dibatalkan: `finished_at` hanya diisi, tidak pernah dikosongkan.
 *
 * @property int $id
 * @property int $review_round_id
 * @property int $submission_reviewer_id
 * @property CarbonImmutable|null $finished_at
 * @property-read ReviewRound $reviewRound
 * @property-read SubmissionReviewer $submissionReviewer
 */
class ReviewRoundParticipant extends Model
{
    protected $fillable = [
        'review_round_id',
        'submission_reviewer_id',
        'finished_at',
    ];

    protected $casts = [
        'finished_at' => 'immutable_datetime',
    ];

    /** @return BelongsTo<ReviewRound, $this> */
    public function reviewRound(): BelongsTo
    {
        return $this->belongsTo(ReviewRound::class);
    }

    /** @return BelongsTo<SubmissionReviewer, $this> */
    public function submissionReviewer(): BelongsTo
    {
        return $this->belongsTo(SubmissionReviewer::class);
    }
}
