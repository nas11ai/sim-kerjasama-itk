<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-13 (Jalur 1 "NIDN dikoreksi Operator") — Operator membetulkan NIDN pendaftar.
 * Koreksi bukan verifikasi: statusnya tidak bergerak, dan tanpa notifikasi karena
 * BR-IAM-15 tidak mendaftarnya (W52).
 *
 * @method static UserProfile run(User $actor, UserProfile $profile, string $nidn)
 */
class CorrectNidn
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'NIDN dikoreksi';

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, UserProfile $profile, string $nidn): UserProfile
    {
        $nidn = trim($nidn);

        if ($nidn === '') {
            throw ValidationException::withMessages(['nidn' => 'NIDN wajib diisi.']);
        }

        // BR-IAM-02 — NIDN unik di seluruh akun.
        if (UserProfile::query()->where('nidn', $nidn)->whereKeyNot($profile->id)->exists()) {
            throw ValidationException::withMessages(['nidn' => 'NIDN ini sudah terdaftar pada akun lain.']);
        }

        // Tidak ada yang berubah, jadi tidak ada keputusan untuk dicatat.
        if ($profile->nidn === $nidn) {
            return $profile;
        }

        $old = $profile->nidn;
        $profile->update(['nidn' => $nidn]);

        // BR-RPT-09 — satu Action, satu entri audit, dengan nilai lama dan baru.
        AuditTrail::record(self::AUDIT_DESCRIPTION, $profile, $actor, new AuditChange(
            old: ['nidn' => $old],
            new: ['nidn' => $nidn],
        ));

        return $profile;
    }
}
