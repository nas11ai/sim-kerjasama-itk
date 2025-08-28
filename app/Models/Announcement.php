<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'type',
        'expired_at',
        'created_by',
    ];

    protected $casts = [
        'expired_at' => 'datetime',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function files()
    {
        return $this->hasMany(AnnouncementFile::class);
    }

    public function readers()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
            ->withTimestamps();
    }
}
