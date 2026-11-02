<?php

namespace App\Data\View\Dashboard;

use App\Data\View\Identity\OwnProfileData;
use App\Models\User;
use Spatie\LaravelData\Data;

/**
 * BR-IAM-03 — banner "Lengkapi profil" di dasbor (state IAM-VERIFY-04 frame SHARED-01-06).
 * Hanya bagi pembuat pengajuan: gerbang ini menahan pembuatan, bukan menu lain.
 */
final class ProfileCompletionData extends Data
{
    /** @param  list<string>  $missing */
    public function __construct(public array $missing) {}

    public static function of(User $user): ?self
    {
        $profile = $user->userProfile;

        if (!$user->can('submissions.create') || $profile === null || $profile->isComplete()) {
            return null;
        }

        return new self(OwnProfileData::labels($profile->missingDetails()));
    }
}
