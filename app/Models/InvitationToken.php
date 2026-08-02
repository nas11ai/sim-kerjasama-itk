<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token
 * @property int $organization_id
 * @property array<string> $permissions
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property int $created_by
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class InvitationToken extends Model
{
    use HasFactory;

    protected $fillable = [
        'token',
        'organization_id',
        'permissions',
        'max_uses',
        'used_count',
        'expires_at',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'expires_at' => 'datetime',
    ];

    public function isValid(): bool
    {
        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return true;
    }

    public function consume(): void
    {
        $this->increment('used_count');
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
