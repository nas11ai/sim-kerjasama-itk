<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\VerificationCardData;
use App\Enums\VerificationStatus;
use App\Models\UserProfile;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-IAM-04, BR-IAM-12, BR-IAM-13 — antrian verifikasi NIDN. Dibangun dari status `pending`,
 * bukan "belum diverifikasi": akun undangan lahir `active` tanpa NIDN, jadi antrian dari
 * negasi memuat baris yang tak pernah bisa diverifikasi atau ditolak jujur (IAM-REG-14).
 *
 * @method static array{pending: list<VerificationCardData>, rejected: list<VerificationCardData>} run()
 */
class VerificationQueue implements ChangesNoState
{
    use AsAction;

    /** @return array{pending: list<VerificationCardData>, rejected: list<VerificationCardData>} */
    public function handle(): array
    {
        $pending = $this->profiles(UserProfile::query()->awaitingVerification()->orderBy('created_at'));
        // BR-IAM-13 — akun yang ditolak tetap terdaftar dan masih bisa diverifikasi.
        $rejected = $this->profiles(UserProfile::query()
            ->where('verification_status', VerificationStatus::Rejected->value)
            ->orderByRaw('rejected_at desc nulls last'));

        $files = BiodataFiles::forProfiles($pending->concat($rejected));

        return [
            'pending' => $this->cards($pending, $files),
            'rejected' => $this->cards($rejected, $files),
        ];
    }

    /**
     * @param  Builder<UserProfile>  $query
     * @return Collection<int, UserProfile>
     */
    private function profiles(Builder $query): Collection
    {
        return $query
            ->select('user_profiles.*')
            ->addSelect(['rejected_at' => RejectedAt::subquery()])
            ->with(['user:id,name,email', 'organization:id,name'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  Collection<int, UserProfile>  $profiles
     * @param  array<int, list<array{label: string, url: string}>>  $files
     * @return list<VerificationCardData>
     */
    private function cards(Collection $profiles, array $files): array
    {
        return array_values($profiles->map(fn (UserProfile $profile): VerificationCardData => VerificationCardData::fromModel(
            $profile,
            $this->rejectedAt($profile),
            $files[$profile->id] ?? [],
        ))->all());
    }

    private function rejectedAt(UserProfile $profile): ?CarbonImmutable
    {
        $at = $profile->getAttribute('rejected_at');

        return is_string($at) ? CarbonImmutable::parse($at) : null;
    }
}
