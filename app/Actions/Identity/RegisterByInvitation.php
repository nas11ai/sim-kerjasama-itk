<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\InvitationToken;
use App\Models\User;
use App\Models\UserProfile;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission;

/**
 * BR-IAM-06, BR-IAM-12 — jalur undangan mitra eksternal, tanpa NIDN: domain
 * email bebas, akun `active` langsung (alasan di createAccount()), dan
 * permission dari token, bukan role.
 *
 * @method static User run(InvitationToken $token, array{name: string, email: string, password: string} $data)
 */
class RegisterByInvitation
{
    use AsAction;

    /**
     * @param  array{name: string, email: string, password: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(InvitationToken $token, array $data): User
    {
        // BR-IAM-06 — empat sebab tidak berlaku, seluruhnya di isValid().
        if (!$token->isValid()) {
            throw ValidationException::withMessages([
                'token' => 'Tautan undangan ini sudah tidak berlaku. '
                    .'Hubungi Operator LPPM untuk meminta undangan baru.',
            ]);
        }

        $tercatat = DB::transaction(fn (): User => $this->createAccount($token, $data));

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Akun dibuat lewat undangan', $tercatat, $tercatat);

        return $tercatat;
    }

    /** @param  array{name: string, email: string, password: string}  $data */
    private function createAccount(InvitationToken $token, array $data): User
    {
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        // BR-IAM-12 — permission diwariskan token, bukan lewat role.
        // Direct permission karena additive (BR-IAM-09, default Spatie):
        // role yang kelak ditambah Operator menambah, bukan mengganti.
        foreach ($token->permissions ?? [] as $permission) {
            Permission::findOrCreate($permission, 'web');
            $user->givePermissionTo($permission);
        }

        UserProfile::create([
            'user_id' => $user->id,
            'organization_id' => $token->organization_id,
            // Tanpa NIDN, makanya langsung aktif: verifikasi memeriksa
            // NIDN, dan menahan akun tanpa NIDN di `pending` jadi antrian
            // yang tak bisa dibersihkan; saringannya saat token terbit.
            'nidn' => null,
            'verification_status' => VerificationStatus::Active->value,
        ]);

        $token->consume();

        return $user->refresh();
    }
}
