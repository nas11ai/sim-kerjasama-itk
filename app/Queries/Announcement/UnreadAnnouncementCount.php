<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W32 — jumlah badge sidebar "Pengumuman": yang terlihat oleh pembaca dan belum ia buka.
 *
 * @method static int run(User $reader)
 */
class UnreadAnnouncementCount implements ChangesNoState
{
    use AsAction;

    public function handle(User $reader): int
    {
        return VisibleAnnouncements::run($reader)
            ->whereDoesntHave('announcementReader', static fn (Builder $query) => $query->whereKey($reader->id))
            ->count();
    }
}
