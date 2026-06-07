<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ReviewerRole, $this> */
    public function reviewerRole(): BelongsTo
    {
        return $this->belongsTo(ReviewerRole::class);
    }

    /** @return HasMany<SubmissionReviewer, $this> */
    public function submissionReviewers(): HasMany
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
