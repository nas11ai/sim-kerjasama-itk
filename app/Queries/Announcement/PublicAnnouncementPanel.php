<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Announcement\AnnouncementSummaryData;
use App\Models\Announcement;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W6 — panel pengumuman publik di halaman login: yang berlaku bagi tamu, paling banyak lima
 * terbaru. `PublicAnnouncementFeed` (WelcomePage) hidup sampai F99.
 *
 * @method static list<AnnouncementSummaryData> run()
 */
class PublicAnnouncementPanel implements ChangesNoState
{
    use AsAction;

    private const LIMIT = 5;

    /** @return list<AnnouncementSummaryData> */
    public function handle(): array
    {
        return array_values(VisibleAnnouncements::run(null)
            ->withCount('announcementFiles')
            ->latest()
            ->orderByDesc('id')
            ->take(self::LIMIT)
            ->get()
            ->map(static fn (Announcement $announcement): AnnouncementSummaryData => AnnouncementSummaryData::of($announcement, null))
            ->all());
    }
}
