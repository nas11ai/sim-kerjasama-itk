<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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

    public function announcementCreator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function announcementFiles()
    {
        return $this->hasMany(AnnouncementFile::class);
    }

    public function announcementReader()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
            ->withTimestamps();
    }
}
