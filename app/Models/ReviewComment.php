<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewComment extends Model
{
    protected $fillable = [
        'review_summary_id',
        'parent_comment_id',
        'user_id',
        'reviewer_id',
        'comment_text',
    ];

    public function reviewSummary()
    {
        return $this->belongsTo(ReviewSummary::class);
    }

    public function parentComment()
    {
        return $this->belongsTo(ReviewComment::class, 'parent_comment_id');
    }

    public function replies()
    {
        return $this->hasMany(ReviewComment::class, 'parent_comment_id');
    }

    public function attachments()
    {
        return $this->hasMany(ReviewCommentAttachment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer()
    {
        return $this->belongsTo(Reviewer::class);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_comment_id');
    }

    public function scopeReplies($query)
    {
        return $query->whereNotNull('parent_comment_id');
    }

    public function getAuthorAttribute()
    {
        return $this->user ?: $this->reviewer?->user;
    }

    public function getAuthorTypeAttribute()
    {
        return $this->reviewer_id ? 'reviewer' : 'submitter';
    }

    // Add accessor untuk serialization
    protected $appends = ['author_display'];

    public function getAuthorDisplayAttribute()
    {
        if ($this->reviewer && $this->reviewer->user) {
            return [
                'name' => $this->reviewer->user->name,
                'type' => 'reviewer',
                'is_current_user' => false // This will be handled in frontend
            ];
        } elseif ($this->user) {
            return [
                'name' => $this->user->name,
                'type' => 'submitter',
                'is_current_user' => false // This will be handled in frontend
            ];
        }

        return [
            'name' => 'Unknown',
            'type' => 'user',
            'is_current_user' => false
        ];
    }

    // Prevent circular reference issues
    protected $hidden = ['reviewer.reviewSummaries', 'user.submissions'];
}
