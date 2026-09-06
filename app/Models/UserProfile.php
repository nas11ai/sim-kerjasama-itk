<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

/**
 * @property-read User|null $user
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'organization_id',
    ];

    /**
     * Keanggotaan penelitian yang dipegang profil ini.
     *
     * ResearchMember menunjuk UserProfile, bukan User — itu sebabnya
     * penyaringan calon anggota harus lewat sini.
     *
     * @return HasMany<ResearchMember, $this>
     */
    public function researchMembers(): HasMany
    {
        return $this->hasMany(ResearchMember::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
