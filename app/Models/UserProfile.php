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

    /**
     * BR-IAM-03 — kelengkapan profil, gerbang yang **berbeda** dari status
     * verifikasi dan dari keaktifan akun.
     *
     * Ketiganya harus terisi. Pemeriksaan yang berhenti di field pertama akan
     * meloloskan profil yang baru terisi sepertiga, dan kekurangannya baru
     * ketahuan jauh di hilir.
     */
    public function isComplete(): bool
    {
        return filled($this->phone)
            && filled($this->expertise)
            && filled($this->photo_path);
    }

    /**
     * BR-IAM-12 — antrian verifikasi NIDN.
     *
     * Dibangun dari status `pending`, **bukan** dari "belum diverifikasi".
     * Perbedaannya menentukan: akun jalur undangan lahir `active` tanpa NIDN,
     * dan antrian yang dibangun dari negasi akan memuat baris yang tidak akan
     * pernah bisa diverifikasi maupun ditolak dengan jujur oleh Operator.
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

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
