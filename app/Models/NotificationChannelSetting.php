<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BR-NOT-06 — deklarasi channel untuk satu tipe event.
 *
 * @property string $event_type
 * @property array<int, string> $channels
 */
class NotificationChannelSetting extends Model
{
    protected $fillable = ['event_type', 'channels', 'updated_by'];

    protected $casts = ['channels' => 'array'];

    /** @return BelongsTo<User, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
