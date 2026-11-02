<?php

namespace App\Queries\Identity;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Identity\UserRowData;
use App\Enums\VerificationStatus;
use App\Models\User;
use App\Support\Identity\RoleCatalog;
use App\Support\Search\LikePattern;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * OPERATOR-08-01 — seluruh user, tersaring di server. Status akun dan status verifikasi
 * disaring terpisah karena DDD memisahkannya (W49); nilai filter tak dikenal diabaikan.
 *
 * @phpstan-type Filters array{search: string, role: string, account: string, verification: string}
 * @phpstan-type Option array{value: string, label: string}
 *
 * @method static array{users: LengthAwarePaginator<int, UserRowData>, roleOptions: list<Option>, accountOptions: list<Option>, verificationOptions: list<Option>} run(Filters $filters)
 */
class UserDirectory implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 20;

    private const ACCOUNT_OPTIONS = [
        ['value' => 'active', 'label' => 'Aktif'],
        ['value' => 'inactive', 'label' => 'Nonaktif'],
    ];

    /**
     * @param  Filters  $filters
     * @return array{users: LengthAwarePaginator<int, UserRowData>, roleOptions: list<Option>, accountOptions: list<Option>, verificationOptions: list<Option>}
     */
    public function handle(array $filters): array
    {
        $query = User::query()
            ->with(['roles:id,name', 'userProfile', 'organization:organizations.id,name'])
            ->orderBy('name')
            ->orderBy('id');

        $this->filter($query, $filters);

        return [
            'users' => $query->paginate(self::PER_PAGE)
                ->withQueryString()
                ->through(fn (User $user): UserRowData => UserRowData::fromModel($user)),
            'roleOptions' => RoleCatalog::options(array_keys(RoleCatalog::LABELS)),
            'accountOptions' => self::ACCOUNT_OPTIONS,
            'verificationOptions' => array_map(
                fn (VerificationStatus $status): array => ['value' => $status->value, 'label' => $status->label()],
                VerificationStatus::cases(),
            ),
        ];
    }

    /**
     * @param  Builder<User>  $query
     * @param  Filters  $filters
     */
    private function filter(Builder $query, array $filters): void
    {
        $pattern = LikePattern::contains($filters['search']);
        $query->when($filters['search'] !== '', fn (Builder $q) => $q->where(fn (Builder $any) => $any
            ->whereLike('name', $pattern)
            ->orWhereLike('email', $pattern)
            ->orWhereHas('userProfile', fn (Builder $profile) => $profile->whereLike('nidn', $pattern))));

        $role = $filters['role'];
        $query->when(array_key_exists($role, RoleCatalog::LABELS), fn (Builder $q) => $q->whereHas('roles', fn (Builder $r) => $r->where('name', $role)));

        $account = $filters['account'];
        $query->when(in_array($account, ['active', 'inactive'], true), fn (Builder $q) => $q->where('is_active', $account === 'active'));

        $verification = VerificationStatus::tryFrom($filters['verification']);
        $query->when($verification !== null, fn (Builder $q) => $q->whereHas('userProfile', fn (Builder $profile) => $profile->where('verification_status', $verification)));
    }
}
