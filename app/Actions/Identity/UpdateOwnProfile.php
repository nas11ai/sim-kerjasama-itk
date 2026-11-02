<?php

namespace App\Actions\Identity;

use App\Data\Request\ProfileDetailsInput;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * SHARED-01-07 — user menyunting nama dan data tambahannya sendiri. Email, NIDN, dan
 * organisasi di luar jangkauan (BR-IAM-01/02/18); tanpa notifikasi (BR-IAM-15).
 *
 * @method static User run(User $user, string $name, ?ProfileDetailsInput $details)
 */
class UpdateOwnProfile
{
    use AsAction;

    public const AUDIT_DESCRIPTION = 'Profil diperbarui';

    public function handle(User $user, string $name, ?ProfileDetailsInput $details): User
    {
        $before = $this->snapshot($user);

        DB::transaction(function () use ($user, $name, $details, $before): void {
            $user->update(['name' => $name]);

            if ($details !== null) {
                $user->userProfile?->update(['phone' => $details->phone, 'expertise' => $details->expertise]);
            }

            $after = $this->snapshot($user);
            $changed = array_keys(array_filter($after, fn (?string $value, string $key): bool => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH));

            // Tidak ada yang berubah, jadi tidak ada keputusan untuk dicatat (preseden CorrectNidn).
            if ($changed === []) {
                return;
            }

            AuditTrail::record(self::AUDIT_DESCRIPTION, $user, $user, new AuditChange(
                old: array_intersect_key($before, array_flip($changed)),
                new: array_intersect_key($after, array_flip($changed)),
            ));
        });

        return $user;
    }

    /** @return array<string, string|null> */
    private function snapshot(User $user): array
    {
        $profile = $user->userProfile;
        $values = ['name' => $user->name];

        if ($profile === null) {
            return $values;
        }

        return [...$values, 'phone' => $profile->phone, 'expertise' => $profile->expertise];
    }
}
