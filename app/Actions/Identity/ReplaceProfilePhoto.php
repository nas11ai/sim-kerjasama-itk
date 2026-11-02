<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Throwable;

/**
 * W65 — user mengganti foto profilnya sendiri (BR-IAM-03: foto salah satu data tambahan).
 * Disimpan di disk privat (W15); tanpa notifikasi karena perubahan oleh diri sendiri
 * (BR-IAM-15). Jejak audit tidak memuat path berkas privat.
 *
 * @method static UserProfile run(User $user, UploadedFile $photo)
 */
class ReplaceProfilePhoto
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'Foto profil diganti';

    private const DIRECTORY = 'profile-photos';

    /**
     * @throws ValidationException
     * @throws Throwable
     */
    public function handle(User $user, UploadedFile $photo): UserProfile
    {
        $profile = $user->userProfile;

        if ($profile === null) {
            throw ValidationException::withMessages(['photo' => 'Akun ini tidak memiliki profil peneliti.']);
        }

        $disk = Storage::disk(PrivateUploadDisk::NAME);
        $old = $profile->photo_path;
        $path = $photo->store(self::DIRECTORY, PrivateUploadDisk::NAME);

        if ($path === false) {
            throw ValidationException::withMessages(['photo' => 'Foto gagal disimpan. Coba lagi.']);
        }

        try {
            DB::transaction(function () use ($profile, $user, $path): void {
                $profile->update(['photo_path' => $path]);
                AuditTrail::record(self::AUDIT_DESCRIPTION, $profile, $user);
            });
        } catch (Throwable $e) {
            $disk->delete($path);

            throw $e;
        }

        // Berkas lama baru dibuang setelah barisnya menunjuk foto baru.
        if ($old !== null && $old !== $path) {
            $disk->delete($old);
        }

        return $profile;
    }
}
