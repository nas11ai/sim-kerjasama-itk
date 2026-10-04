<?php

namespace App\Models;

use App\Queries\Review\ReviewersWithinTenure;
use App\Support\Lifecycle\DeletionNotPermitted;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property bool $is_reviewer
 * @property-read UserProfile|null $userProfile
 * @property-read Organization|null $organization
 * @property-read Reviewer|null $reviewer
 */
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * BR-IAM-19 — akun tidak pernah dihapus, hanya dinonaktifkan (BR-IAM-05).
     * Dijaga di model: BR-IAM-10 menolak menonaktifkan lead researcher yang
     * masih memegang pengajuan aktif — percuma kalau hapus tetap terbuka.
     */
    protected static function booted(): void
    {
        static::deleting(function (): never {
            throw new DeletionNotPermitted(
                'Akun tidak dapat dihapus — user hanya dinonaktifkan (BR-IAM-19). '
                .'Deaktivasi mencabut kemampuan bertindak tanpa menghilangkan pengajuan, '
                .'profil, dan jejak yang menunjuk orang ini.'
            );
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    // App\Models\User.php
    protected $appends = ['is_reviewer'];

    public function formSubmissions()
    {
        return $this->hasMany(FormSubmission::class, 'submitted_by');
    }

    /**
     * @return HasMany<
     *     Reviewer,
     *     $this
     * >
     */
    public function reviewers()
    {
        return $this->hasMany(Reviewer::class);
    }

    // BR-REV-09 — reviewer = sedang dalam masa tugas, satu definisi (inklusif per hari).
    // Dulu cukup punya record, sehingga reviewer yang masa tugasnya habis tetap tampil.
    public function getIsReviewerAttribute(): bool
    {
        return ReviewersWithinTenure::run()->where('user_id', $this->id)->exists();
    }

    public function reviewer()
    {
        return $this->hasOne(Reviewer::class);
    }

    public function announcementCreated()
    {
        return $this->hasMany(Announcement::class, 'created_by');
    }

    public function announcementReads()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_users')
            ->withTimestamps();
    }

    /**
     * Get the user's profile.
     *
     * @return HasOne<UserProfile, $this>
     */
    public function userProfile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * Get the user's organization through their profile.
     */
    public function organization(): HasOneThrough
    {
        return $this->hasOneThrough(
            Organization::class,
            UserProfile::class,
            'user_id',            // Foreign key on user_profiles
            'id',                 // Foreign key on organizations
            'id',                 // Local key on users
            'organization_id'     // Local key on user_profiles
        );
    }

    public function getStudyProgramIdAttribute(): ?int
    {
        if ($this->organization === null) {
            return null;
        }

        /** @var array|null $meta */
        $meta = $this->organization->metadata;

        return isset($meta['legacy']['study_program_id']) ? (int) $meta['legacy']['study_program_id'] : null;
    }
}
