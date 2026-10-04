<?php

namespace App\Actions\Announcement;

use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menarik pengumuman beserta lampirannya, termasuk berkas di disk `public`.
 *
 * @method static void run(Announcement $announcement, User $actor)
 */
class RetractAnnouncement
{
    use AsAction;

    public function handle(Announcement $announcement, User $actor): void
    {
        $files = DB::transaction(function () use ($announcement, $actor): Collection {
            $files = $announcement->announcementFiles()->get();

            AuditTrail::record(
                'Pengumuman ditarik',
                $announcement,
                $actor,
                new AuditChange(old: ['title' => $announcement->title, 'type' => $announcement->type]),
            );

            $announcement->announcementFiles()->delete();
            $announcement->delete();

            return $files;
        });

        AnnouncementFileStore::purge($files);
    }
}
