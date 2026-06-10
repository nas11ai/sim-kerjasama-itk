<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $file_path
 */
class ReviewSummaryAttachment extends Model
{
    protected $fillable = [
        'review_summary_id',
        'file_path',
    ];

    public function reviewSummary()
    {
        return $this->belongsTo(ReviewSummary::class);
    }
}
