<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Announcement;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Satu definisi pengumuman yang boleh dilihat penanya. Tamu hanya melihat yang
 * publik; pengguna login melihat publik dan privat. Yang `expired_at`-nya lewat
 * tidak tampil bagi siapa pun di sini — layar admin membaca model langsung.
 *
 * @method static Builder<Announcement> run(?User $viewer)
 */
class VisibleAnnouncements implements ChangesNoState
{
    use AsAction;

    /** @return Builder<Announcement> */
    public function handle(?User $viewer): Builder
    {
        $query = Announcement::query()->where(static function (Builder $query): void {
            $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
        });

        if ($viewer === null) {
            return $query->where('type', 'public');
        }

        return $this->targeting($query, $viewer);
    }

    /**
     * fe-v2-design §4.7 — privat dipersempit per peran; cukup cocok satu.
     * Super Admin tidak dikecualikan (W36): layar kelola membaca tanpa Query ini.
     *
     * @param  Builder<Announcement>  $query
     * @return Builder<Announcement>
     */
    private function targeting(Builder $query, User $viewer): Builder
    {
        $roleIds = $viewer->roles()->pluck('roles.id')->all();

        return $query->where(static function (Builder $query) use ($roleIds): void {
            $query->whereDoesntHave('targetRoles')
                ->orWhereHas('targetRoles', static fn (Builder $roles) => $roles->whereIn('roles.id', $roleIds));
        });
    }
}
