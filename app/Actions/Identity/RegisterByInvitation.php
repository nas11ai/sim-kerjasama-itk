<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\InvitationToken;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission;

/**
 * BR-IAM-06, BR-IAM-12 — jalur undangan untuk mitra eksternal.
 *
 * Tiga hal yang membedakannya dari jalur mandiri, dan ketiganya konsekuensi
 * dari satu fakta: **mitra eksternal tidak punya NIDN**.
 *
 *   1. Domain email bebas — batas @itk.ac.id milik jalur mandiri saja.
 *   2. Akunnya `active` **langsung**. Verifikasi memverifikasi NIDN; menahan
 *      akun tanpa NIDN di status `pending` berarti antrian yang tidak bisa
 *      dibersihkan siapa pun secara jujur. Penyaringannya sudah terjadi saat
 *      Operator menerbitkan token.
 *   3. Permission datang dari token, bukan dari role.
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

        return DB::transaction(function () use ($token, $data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            // BR-IAM-12 — permission diwariskan token, bukan lewat role.
            // Diberikan sebagai direct permission justru karena itu additive
            // (BR-IAM-09): role apa pun yang kelak ditambahkan Operator
            // menambah, bukan menggantikan, bekal awal ini.
            foreach ($token->permissions ?? [] as $permission) {
                Permission::findOrCreate($permission, 'web');
                $user->givePermissionTo($permission);
            }

            UserProfile::create([
                'user_id' => $user->id,
                'organization_id' => $token->organization_id,
                // Tidak ada NIDN — dan itu sebabnya statusnya langsung aktif.
                'nidn' => null,
                'verification_status' => VerificationStatus::Active->value,
            ]);

            $token->consume();

            return $user->refresh();
        });
    }
}
