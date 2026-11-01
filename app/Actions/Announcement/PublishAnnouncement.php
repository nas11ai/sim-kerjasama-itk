<?php

namespace App\Actions\Announcement;

use App\Data\Request\AnnouncementInput;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Support\Announcement\AnnouncementHtml;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menerbitkan pengumuman beserta lampiran dan target perannya (fe-v2-design §4.7).
 *
 * @method static Announcement run(AnnouncementInput $input, User $actor)
 */
class PublishAnnouncement
{
    use AsAction;

    public function handle(AnnouncementInput $input, User $actor): Announcement
    {
        return DB::transaction(function () use ($input, $actor): Announcement {
            $announcement = Announcement::create([
                'title' => $input->title,
                // fe-v2-design §4.7 K6 — disaring di Action supaya setiap jalur tulis tersaring.
                'content' => AnnouncementHtml::sanitize($input->content),
                'type' => $input->type,
                'expired_at' => $input->expiredAt,
                'created_by' => $actor->id,
            ]);
            $announcement->syncTargetRoles($input->targetRoles);

            AnnouncementFileStore::attach($announcement, $input->files);

            AuditTrail::record(
                'Pengumuman diterbitkan',
                $announcement,
                $actor,
                new AuditChange(new: [
                    'title' => $input->title,
                    'type' => $input->type,
                    'target_roles' => $input->targetRoles,
                ]),
            );

            return $announcement;
        });
    }
}
