<?php

namespace App\Actions\Announcement;

use App\Data\Request\AnnouncementInput;
use App\Models\Announcement;
use App\Models\User;
use App\Services\Announcement\AnnouncementFileStore;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Menerbitkan pengumuman beserta lampirannya.
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
                'content' => $input->content,
                'type' => $input->type,
                'expired_at' => $input->expiredAt,
                'created_by' => $actor->id,
            ]);

            AnnouncementFileStore::attach($announcement, $input->files);

            AuditTrail::record(
                'Pengumuman diterbitkan',
                $announcement,
                $actor,
                new AuditChange(new: ['title' => $input->title, 'type' => $input->type]),
            );

            return $announcement;
        });
    }
}
