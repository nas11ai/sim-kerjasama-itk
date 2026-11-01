<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Announcement\ManagedAnnouncementRowData;
use App\Models\Announcement;
use Illuminate\Pagination\LengthAwarePaginator;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Daftar layar kelola pengumuman (fe-v2-design §4.7): semua pengumuman, termasuk yang
 * kedaluwarsa, sehingga sengaja tidak lewat VisibleAnnouncements.
 *
 * @method static LengthAwarePaginator<int, ManagedAnnouncementRowData> run(string $search)
 */
class ManagedAnnouncementList implements ChangesNoState
{
    use AsAction;

    private const PER_PAGE = 15;

    /** @return LengthAwarePaginator<int, ManagedAnnouncementRowData> */
    public function handle(string $search): LengthAwarePaginator
    {
        return Announcement::query()
            ->with(['targetRoles:roles.id,roles.name', 'announcementCreator:id,name'])
            // PostgreSQL — whereLike tidak peka huruf.
            ->when($search !== '', static fn ($query) => $query->whereLike('title', "%{$search}%"))
            ->latest()
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString()
            ->through(static fn (Announcement $announcement): ManagedAnnouncementRowData => ManagedAnnouncementRowData::of($announcement));
    }
}
