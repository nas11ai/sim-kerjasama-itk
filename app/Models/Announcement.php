<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read User|null $announcementCreator
 * @property-read Collection<int, AnnouncementFile> $announcementFiles
 */
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function announcementCreator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AnnouncementFile, $this>
     */
    public function announcementFiles(): HasMany
    {
        return $this->hasMany(AnnouncementFile::class);
    }

    public function announcementReader()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
            ->withTimestamps();
    }
}
