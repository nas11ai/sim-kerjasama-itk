<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $form_submission_id
 * @property int $user_profile_id
 * @property string $role
 * @property string $status
 * @property-read FormSubmission $formSubmission
 * @property-read UserProfile $userProfile
 */
class ResearchMember extends Model
{
    use HasFactory;

    public const ROLE_CO_INVESTIGATOR = 'co_investigator';

    public const ROLE_MEMBER = 'member';

    /** Peran yang bisa diberikan kepada anggota, dengan label ubiquitous language. */
    public const ROLE_LABELS = [
        self::ROLE_CO_INVESTIGATOR => 'Co-Investigator',
        self::ROLE_MEMBER => 'Anggota',
    ];

    protected $fillable = [
        'form_submission_id',
        'user_profile_id',
        'role',
        'status',
    ];

    /** @return BelongsTo<FormSubmission, $this> */
    public function formSubmission(): BelongsTo
    {
        return $this->belongsTo(FormSubmission::class);
    }

    /** @return BelongsTo<UserProfile, $this> */
    public function userProfile(): BelongsTo
    {
        return $this->belongsTo(UserProfile::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canEditOutputs(): bool
    {
        // BR-RO-07 — hanya co_investigator yang boleh tambah/edit luaran.
        return $this->isActive() && $this->role === self::ROLE_CO_INVESTIGATOR;
    }
}
