<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\NewRegistrationAwaitingVerification;
use App\Support\EmailDomainGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission;

/**
 * BR-IAM-01, BR-IAM-02, BR-IAM-12 — jalur registrasi mandiri untuk dosen ITK.
 *
 * Bekal yang dibawa akun barunya ada empat, dan keempatnya ditentukan jalurnya
 * — bukan pilihan pendaftar: role `researcher`, organisasi yang ia pilih di
 * OrgTreePicker, permission dari role itu, dan status `pending` menunggu
 * verifikasi NIDN.
 *
 * Pendaftar mendarat di antrian verifikasi, dan Operator diberi tahu
 * (BR-IAM-15). Antrian yang tidak ada yang tahu isinya sama saja dengan
 * pendaftar yang menunggu selamanya.
 *
 * @method static User run(array{name: string, email: string, password: string, nidn: string, organization_id: int} $data)
 */
class RegisterResearcher
{
    use AsAction;

    public const ROLE = 'researcher';

    /**
     * @param  array{name: string, email: string, password: string, nidn: string, organization_id: int}  $data
     *
     * @throws ValidationException
     */
    public function handle(array $data): User
    {
        // BR-IAM-01 — domain persis, dan hanya di jalur mandiri.
        if (!EmailDomainGuard::isInternal($data['email'])) {
            throw ValidationException::withMessages([
                'email' => 'Registrasi mandiri hanya untuk email @itk.ac.id. '
                    .'Mitra eksternal mendaftar lewat tautan undangan dari Operator LPPM.',
            ]);
        }

        // BR-IAM-02 — NIDN unik. Diperiksa di sini supaya pesannya mendarat di
        // field yang benar, sementara index unik tetap menjaga lomba-balap.
        if (UserProfile::query()->where('nidn', $data['nidn'])->exists()) {
            throw ValidationException::withMessages([
                'nidn' => 'NIDN ini sudah terdaftar. Hubungi lppm@itk.ac.id kalau ini NIDN Anda.',
            ]);
        }

        $organization = Organization::find($data['organization_id']);

        if ($organization === null || !$organization->is_active) {
            throw ValidationException::withMessages([
                'organization_id' => 'Unit organisasi yang dipilih tidak tersedia.',
            ]);
        }

        $user = DB::transaction(function () use ($data, $organization): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
            ]);

            $user->assignRole(self::ROLE);

            UserProfile::create([
                'user_id' => $user->id,
                'organization_id' => $organization->id,
                'nidn' => $data['nidn'],
                'verification_status' => VerificationStatus::Pending->value,
            ]);

            return $user;
        });

        // BR-IAM-15 — pendaftaran baru memberi tahu pihak yang harus bertindak
        // atasnya, dan untuk jalur ini pihak itu Operator.
        //
        // BR-NOT-03 — pemberitahuannya fire-and-forget. `permission()` milik
        // Spatie melempar kalau permission-nya belum pernah dibuat, dan
        // registrasi seseorang tidak boleh gagal gara-gara antrian Operator
        // belum punya penghuni.
        $this->notifyVerifiers($user);

        return $user->refresh();
    }

    private function notifyVerifiers(User $registrant): void
    {
        if (Permission::where('name', 'users.verify')->where('guard_name', 'web')->doesntExist()) {
            return;
        }

        $operators = User::query()->permission('users.verify')->get();

        if ($operators->isEmpty()) {
            return;
        }

        Notification::send($operators, new NewRegistrationAwaitingVerification($registrant));
    }
}
