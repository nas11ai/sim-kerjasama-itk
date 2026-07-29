<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $reviewer_type
 * @property \Carbon\CarbonInterface|null $start_date
 * @property \Carbon\CarbonInterface|null $end_date
 * @property bool $is_active
 * @property int $total_reviews
 * @property int $pending_reviews
 * @property int $completed_reviews
 * @property-read \App\Models\User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SubmissionReviewer> $submissionReviewers
 */
class Reviewer extends Model
{
    protected $fillable = [
        'user_id',
        'reviewer_type',
        'start_date',
        'end_date',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submissionReviewers()
    {
        return $this->hasMany(SubmissionReviewer::class);
    }
}
