<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Announcement;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Pengumuman untuk carousel halaman depan: publik dan belum kedaluwarsa, paling
 * banyak 10 demi performa. Bentuknya meniru paginator karena layar membacanya begitu.
 *
 * @method static array{data: Collection<int, Announcement>, current_page: int, last_page: int, per_page: int, total: int, from: int, to: int} run()
 */
class PublicAnnouncementFeed implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 10;

    /** @return array{data: Collection<int, Announcement>, current_page: int, last_page: int, per_page: int, total: int, from: int, to: int} */
    public function handle(): array
    {
        $visible = VisibleAnnouncements::run(null);
        $latest = $visible->clone()->with('announcementFiles')->latest()->take(self::LIMIT)->get();

        return [
            'data' => $latest,
            'current_page' => 1,
            'last_page' => 1,
            'per_page' => self::LIMIT,
            'total' => $visible->count(),
            'from' => 1,
            'to' => $latest->count(),
        ];
    }
}
