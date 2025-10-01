<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // App\Models\User.php
    protected $appends = ['is_reviewer'];

    public function formSubmissions()
    {
        return $this->hasMany(FormSubmission::class, 'submitted_by');
    }

    public function reviewers()
    {
        return $this->hasMany(Reviewer::class);
    }

    public function getIsReviewerAttribute()
    {
        return $this->reviewer()->exists();
    }

    public function reviewer()
    {
        return $this->hasOne(Reviewer::class);
    }

    public function announcementCreated()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function announcementReads()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->withTimestamps();
    }
}
