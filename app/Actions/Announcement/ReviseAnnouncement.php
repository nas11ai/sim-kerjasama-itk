<?php

namespace App\Actions\Announcement;

use App\Data\Request\AnnouncementInput;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Services\Announcement\AnnouncementInlineImageStore;
use App\Support\Announcement\AnnouncementHtml;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Merevisi pengumuman. Lampiran yang dilepas hanya yang milik pengumuman ini;
 * id milik pengumuman lain diabaikan. Berkas di disk dihapus sesudah commit,
 * termasuk gambar sisipan yang tak lagi dirujuk isinya (fe-v2-design §4.7 K6a).
 *
 * @method static void run(Announcement $announcement, AnnouncementInput $input, User $actor)
 */
class ReviseAnnouncement
{
    use AsAction;

    public function handle(Announcement $announcement, AnnouncementInput $input, User $actor): void
    {
        $oldImages = AnnouncementHtml::inlineImagePaths($announcement->content);
        $content = AnnouncementHtml::sanitize($input->content);

        $removed = DB::transaction(function () use ($announcement, $input, $actor, $content): Collection {
            $removed = $announcement->announcementFiles()->whereIn('id', $input->deletedFileIds)->get();
            $old = [
                'title' => $announcement->title,
                'type' => $announcement->type,
                'target_roles' => $announcement->targetRoleNames(),
            ];

            $announcement->announcementFiles()->whereIn('id', $removed->modelKeys())->delete();
            $announcement->update([
                'title' => $input->title,
                'content' => $content,
                'type' => $input->type,
                'expired_at' => $input->expiredAt,
            ]);
            $announcement->syncTargetRoles($input->targetRoles);
            AnnouncementFileStore::attach($announcement, $input->files);

            AuditTrail::record(
                'Pengumuman direvisi',
                $announcement,
                $actor,
                new AuditChange(old: $old, new: [
                    'title' => $input->title,
                    'type' => $input->type,
                    'target_roles' => $input->targetRoles,
                ]),
            );

            return $removed;
        });

        AnnouncementFileStore::purge($removed);
        $dropped = array_values(array_diff($oldImages, AnnouncementHtml::inlineImagePaths($content)));
        AnnouncementInlineImageStore::purgeUnreferenced($dropped, $announcement->id);
    }
}
