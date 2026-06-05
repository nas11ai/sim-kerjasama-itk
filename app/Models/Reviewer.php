<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property-read User|null $user
 * @property-read ReviewerRole|null $reviewerRole
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
