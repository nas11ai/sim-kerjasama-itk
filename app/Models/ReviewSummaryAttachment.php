<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $file_path
 * @property-read ReviewSummary $reviewSummary
 */
class ReviewSummaryAttachment extends Model
{
    protected $fillable = [
        'review_summary_id',
        'file_path',
    ];

    /** @return BelongsTo<ReviewSummary, $this> */
    public function reviewSummary(): BelongsTo
    {
        return $this->belongsTo(ReviewSummary::class);
    }
}
