<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $file_path
 */
class ReviewCommentAttachment extends Model
{
    protected $fillable = [
        'review_comment_id',
        'file_path',
    ];

    public function reviewComment()
    {
        return $this->belongsTo(ReviewComment::class);
    }
}
