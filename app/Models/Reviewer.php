<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reviewer_role_id
 * @property Carbon|null $start_date
 * @property Carbon|null $end_date
 * @property-read User|null $user
 * @property-read ReviewerRole|null $reviewerRole
 * @property-read ReviewerRole|null $reviewer_role
 * @property-read Collection<int, SubmissionReviewer> $submissionReviewers
 * @property bool $is_active
 * @property int $total_reviews
 * @property int $pending_reviews
 * @property int $completed_reviews
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

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
