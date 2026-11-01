<?php

namespace App\Queries\Announcement;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Announcement\AnnouncementDetailData;
use App\Models\Announcement;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi `Announcement/ShowPage` bagi penanya, atau null bila pengumuman itu tidak terlihat
 * olehnya (VisibleAnnouncements) — pemanggil menjawabnya 404, bukan 403.
 *
 * @method static AnnouncementDetailData|null run(Announcement $announcement, ?User $viewer)
 */
class ReaderAnnouncementDetail implements ChangesNoState
{
    use AsAction;

    public function handle(Announcement $announcement, ?User $viewer): ?AnnouncementDetailData
    {
        if (!VisibleAnnouncements::run($viewer)->whereKey($announcement->id)->exists()) {
            return null;
        }

        $isRead = $viewer === null
            ? null
            : $announcement->announcementReader()->whereKey($viewer->id)->exists();

        return AnnouncementDetailData::of($announcement, $isRead);
    }
}
