<?php

namespace App\Actions\Identity;

use App\Enums\VerificationStatus;
use App\Models\Organization;
use App\Models\User;
use App\Models\UserProfile;
use App\Notifications\NewRegistrationAwaitingVerification;
use App\Support\Audit\AuditTrail;
use App\Support\EmailDomainGuard;
use App\Support\Notification\Deliver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;
use Spatie\Permission\Models\Permission;

/**
 * BR-IAM-01, BR-IAM-02, BR-IAM-12 — registrasi mandiri dosen ITK. Empat bekal
 * akunnya ditentukan jalurnya, bukan pendaftar: role `researcher`, organisasi
 * OrgTreePicker, permission role itu, `pending` sampai NIDN terverifikasi.
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
        $organization = $this->guardRegistrable($data);

        $user = DB::transaction(fn (): User => $this->createAccount($data, $organization));

        // BR-IAM-15 — pendaftar masuk antrian verifikasi dan Operator
        // diberi tahu; antrian yang tak diketahui isinya sama dengan
        // menunggu selamanya.
        $this->notifyVerifiers($user);

        $tercatat = $user->refresh();

        // BR-RPT-09 — satu Action, satu entri audit.
        AuditTrail::record('Akun peneliti didaftarkan mandiri', $tercatat, $tercatat);

        return $tercatat;
    }

    /**
     * @param  array{name: string, email: string, password: string, nidn: string, organization_id: int}  $data
     *
     * @throws ValidationException
     */
    private function guardRegistrable(array $data): Organization
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

        return $organization;
    }

    /** @param  array{name: string, email: string, password: string, nidn: string, organization_id: int}  $data */
    private function createAccount(array $data, Organization $organization): User
    {
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
    }

    private function notifyVerifiers(User $registrant): void
    {
        // BR-NOT-03 — fire-and-forget: `permission()` Spatie melempar kalau
        // permission-nya belum dibuat, dan registrasi tidak boleh gagal
        // gara-gara antrian Operator belum punya penghuni.
        if (Permission::where('name', 'users.verify')->where('guard_name', 'web')->doesntExist()) {
            return;
        }

        $operators = User::query()->permission('users.verify')->get();

        if ($operators->isEmpty()) {
            return;
        }

        Deliver::to($operators, new NewRegistrationAwaitingVerification($registrant));
    }
}
