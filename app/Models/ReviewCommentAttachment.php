<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
