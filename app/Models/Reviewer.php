<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reviewer_role_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\ReviewerRole $reviewerRole
 */

/**
 * @property int $id
 * @property int $user_id
 * @property int $reviewer_role_id
 * @property CarbonInterface|null $start_date
 * @property CarbonInterface|null $end_date
 * @property bool $is_active
 * @property int $total_reviews
 * @property int $pending_reviews
 * @property int $completed_reviews
 * @property-read User|null $user
 * @property-read ReviewerRole|null $reviewerRole
 * @property-read ReviewerRole|null $reviewer_role
 * @property-read Collection<int, SubmissionReviewer> $submissionReviewers
 */
class Reviewer extends Model
{
    protected $fillable = [
        'user_id',
        'reviewer_role_id',
        'start_date',
        'end_date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewerRole(): BelongsTo
    {
        return $this->belongsTo(ReviewerRole::class);
    }

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
