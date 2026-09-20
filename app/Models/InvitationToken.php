<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $token
 * @property int|null $organization_id
 * @property array<string> $permissions
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property Carbon|null $revoked_at
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
        'revoked_at',
        'created_by',
    ];

    protected $casts = [
        'permissions' => 'array',
        'max_uses' => 'integer',
        'used_count' => 'integer',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    /**
     * BR-IAM-06 — empat sebab sebuah undangan tidak berlaku. Dua yang baru:
     * token bocor bisa **dicabut** tanpa menunggu kedaluwarsa, dan token ke
     * organisasi yang dipensiunkan tidak boleh melahirkan akun (BR-IAM-14).
     */
    public function isValid(): bool
    {
        if ($this->revoked_at !== null) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        // Organisasi yang dihapus membuat relasinya null; yang dinonaktifkan
        // membuatnya ada tapi tertutup. Keduanya sama-sama berarti tidak ada
        // tempat yang sah bagi pendaftar baru.
        $organization = $this->organization;

        return $organization !== null && $organization->is_active;
    }

    public function revoke(): void
    {
        $this->update(['revoked_at' => now()]);
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
