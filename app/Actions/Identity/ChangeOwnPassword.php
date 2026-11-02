<?php

namespace App\Actions\Identity;

use App\Models\User;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W66 — user mengganti kata sandinya sendiri. Diaudit (BR-RPT-09) tanpa properti apa pun,
 * jadi hash tidak pernah masuk jejak; tanpa notifikasi (BR-IAM-15). Sesi lain tidak
 * diakhiri: jalur itu milik `users.manage` (BR-IAM-21).
 *
 * @method static void run(User $user, string $plainPassword)
 */
class ChangeOwnPassword
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'Kata sandi diganti';

    public function handle(User $user, string $plainPassword): void
    {
        DB::transaction(function () use ($user, $plainPassword): void {
            $user->update(['password' => Hash::make($plainPassword)]);
            AuditTrail::record(self::AUDIT_DESCRIPTION, $user, $user);
        });
    }
}
