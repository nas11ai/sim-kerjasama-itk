<?php

namespace App\Actions\Announcement;

use App\Data\Request\AnnouncementInput;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Merevisi pengumuman. Lampiran yang dilepas hanya yang milik pengumuman ini;
 * id milik pengumuman lain diabaikan. Berkas di disk dihapus sesudah commit.
 *
 * @method static void run(Announcement $announcement, AnnouncementInput $input, User $actor)
 */
class ReviseAnnouncement
{
    use AsAction;

    public function handle(Announcement $announcement, AnnouncementInput $input, User $actor): void
    {
        $removed = DB::transaction(function () use ($announcement, $input, $actor): Collection {
            $removed = $announcement->announcementFiles()->whereIn('id', $input->deletedFileIds)->get();
            $old = ['title' => $announcement->title, 'type' => $announcement->type];

            $announcement->announcementFiles()->whereIn('id', $removed->modelKeys())->delete();
            $announcement->update([
                'title' => $input->title,
                'content' => $input->content,
                'type' => $input->type,
                'expired_at' => $input->expiredAt,
            ]);
            AnnouncementFileStore::attach($announcement, $input->files);

            AuditTrail::record(
                'Pengumuman direvisi',
                $announcement,
                $actor,
                new AuditChange(old: $old, new: ['title' => $input->title, 'type' => $input->type]),
            );

            return $removed;
        });

        AnnouncementFileStore::purge($removed);
    }
}
