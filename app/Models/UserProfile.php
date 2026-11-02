<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Models\Role;

/**
 * @property string|null $nidn
 * @property VerificationStatus $verification_status
 * @property string|null $rejection_reason
 * @property string|null $phone
 * @property string|null $expertise
 * @property string|null $photo_path
 * @property-read User|null $user
 * @property-read Organization|null $organization
 */
class UserProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'role_id',
        'organization_id',
        'nidn',
        'verification_status',
        'rejection_reason',
        'phone',
        'expertise',
        'photo_path',
    ];

    protected $casts = [
        'verification_status' => VerificationStatus::class,
    ];

    public function isComplete(): bool
    {
        return $this->missingDetails() === [];
    }

    /**
     * BR-IAM-03 — kelengkapan profil, gerbang berbeda dari status verifikasi
     * dan keaktifan akun. Ketiganya wajib terisi; berhenti di field pertama
     * meloloskan profil yang baru sepertiga jadi, ketahuan jauh di hilir.
     *
     * @return list<'phone'|'expertise'|'photo'>
     */
    public function missingDetails(): array
    {
        $missing = [];

        if (blank($this->phone)) {
            $missing[] = 'phone';
        }

        if (blank($this->expertise)) {
            $missing[] = 'expertise';
        }

        if (blank($this->photo_path)) {
            $missing[] = 'photo';
        }

        return $missing;
    }

    /**
     * BR-IAM-12 — antrian verifikasi NIDN, dibangun dari status `pending`,
     * **bukan** negasi "belum diverifikasi": akun undangan lahir `active`
     * tanpa NIDN, dan negasi memuat baris yang tak bisa diverifikasi jujur.
     *
     * @param  Builder<UserProfile>  $query
     * @return Builder<UserProfile>
     */
    public function scopeAwaitingVerification($query)
    {
        return $query->where('verification_status', VerificationStatus::Pending->value);
    }

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

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
