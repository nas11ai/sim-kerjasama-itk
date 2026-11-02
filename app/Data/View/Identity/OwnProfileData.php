<?php

namespace App\Data\View\Identity;

use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * SHARED-01-06/07 — profil milik sendiri. `missing` adalah satu-satunya jawaban
 * "apa yang belum lengkap" bagi frontend (BR-IAM-03); path foto privat tak pernah dikirim.
 */
final class OwnProfileData extends Data
{
    private const LABELS = [
        'phone' => 'Nomor Telepon',
        'expertise' => 'Bidang Keahlian',
        'photo' => 'Foto Profil',
    ];

    /** @param  list<string>  $missing */
    public function __construct(
        public string $name,
        public string $email,
        public bool $hasProfile,
        public ?string $nidn,
        public ?string $organization,
        public ?string $phone,
        public ?string $expertise,
        public ?string $photoUrl,
        public array $missing,
    ) {}

    public static function of(User $user): self
    {
        $profile = $user->userProfile;
        $photo = $profile?->photo_path;

        return new self(
            name: $user->name,
            email: $user->email,
            hasProfile: $profile !== null,
            nidn: $profile?->nidn,
            organization: $user->organization?->name,
            phone: $profile?->phone,
            expertise: $profile?->expertise,
            photoUrl: $photo === null ? null : route('profile.photo.show').'?v='.md5($photo),
            missing: self::labels($profile?->missingDetails() ?? []),
        );
    }

    /**
     * @param  list<'phone'|'expertise'|'photo'>  $details
     * @return list<string>
     */
    public static function labels(array $details): array
    {
        return array_map(fn (string $detail): string => self::LABELS[$detail], $details);
    }
}
