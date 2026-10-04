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
        return Announcement::query()
            ->when($viewer === null, static fn (Builder $query) => $query->where('type', 'public'))
            ->where(static function (Builder $query): void {
                $query->whereNull('expired_at')->orWhere('expired_at', '>', now());
            });
    }
}
