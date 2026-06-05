<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property int $reviewer_role_id
 * @property-read \App\Models\User $user
 * @property-read \App\Models\ReviewerRole $reviewerRole
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
