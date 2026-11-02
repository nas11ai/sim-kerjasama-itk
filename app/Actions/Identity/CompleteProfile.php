<?php

namespace App\Actions\Identity;

use App\Data\Request\ProfileDetailsInput;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * SHARED-01-06 — BR-IAM-03: user melengkapi telepon, bidang keahlian, dan foto, gerbang
 * pembuatan pengajuan. Bukan verifikasi: status, NIDN, dan organisasi tidak tersentuh
 * (BR-IAM-04). Perubahan oleh diri sendiri tidak memberi tahu siapa pun (BR-IAM-15, IAM-EVENT-05).
 *
 * @method static UserProfile run(User $user, ProfileDetailsInput $details, ?UploadedFile $photo)
 */
class CompleteProfile
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'Profil dilengkapi';

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(User $user, ProfileDetailsInput $details, ?UploadedFile $photo): UserProfile
    {
        $profile = $user->userProfile;

        if ($profile === null) {
            throw ValidationException::withMessages(['phone' => 'Akun ini tidak memiliki profil peneliti.']);
        }

        // BR-IAM-03 — ketiganya wajib; profil tanpa foto tidak boleh lolos sebagai "lengkap".
        if ($photo === null && blank($profile->photo_path)) {
            throw ValidationException::withMessages(['photo' => 'Pilih foto profil.']);
        }

        if ($photo !== null) {
            ReplaceProfilePhoto::run($user, $photo);
        }

        $old = ['phone' => $profile->phone, 'expertise' => $profile->expertise];
        $new = ['phone' => $details->phone, 'expertise' => $details->expertise];

        DB::transaction(function () use ($profile, $user, $old, $new): void {
            $profile->update($new);
            AuditTrail::record(self::AUDIT_DESCRIPTION, $profile, $user, new AuditChange(old: $old, new: $new));
        });

        return $profile;
    }
}
