<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\Identity\SessionRegistry;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-21 — mengakhiri seluruh sesi seorang user tanpa menonaktifkannya (perangkat hilang,
 * kata sandi bocor). Remember-me ikut diputar, kalau tidak cookie lama memasukkannya kembali.
 *
 * @method static int run(User $actor, User $user)
 */
class EndUserSessions
{
    use AsAction;

    public function handle(User $actor, User $user): int
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
        $ended = SessionRegistry::of($user)->delete();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Sesi user diakhiri', $user, $actor, new AuditChange(new: ['sessions_ended' => $ended]));

        return $ended;
    }
}
