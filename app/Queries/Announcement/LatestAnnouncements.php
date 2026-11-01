<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Announcement\AnnouncementSummaryData;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * "Pengumuman terbaru" di dasbor — potongan teratas daftar pembaca, dengan
 * cakupan dan penanda baca yang sama (VisibleAnnouncements).
 *
 * @method static list<AnnouncementSummaryData> run(User $reader, int $limit = 3)
 */
class LatestAnnouncements implements ChangesNoState
{
    use AsAction;

    /** @return list<AnnouncementSummaryData> */
    public function handle(User $reader, int $limit = 3): array
    {
        return array_values(ReaderAnnouncementList::newestFor($reader)
            ->limit($limit)
            ->get()
            ->map(ReaderAnnouncementList::summary(...))
            ->all());
    }
}
