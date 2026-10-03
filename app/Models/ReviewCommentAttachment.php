<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $review_comment_id
 * @property string $file_path
 * @property-read ReviewComment $reviewComment
 */
class ReviewCommentAttachment extends Model
{
    protected $fillable = [
        'review_comment_id',
        'file_path',
    ];

    /** @return BelongsTo<ReviewComment, $this> */
    public function reviewComment(): BelongsTo
    {
        return $this->belongsTo(ReviewComment::class);
    }
}
