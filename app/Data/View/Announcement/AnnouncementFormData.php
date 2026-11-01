<?php

namespace App\Data\View\Announcement;

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Support\Announcement\AnnouncementHtml;
use Spatie\LaravelData\Data;

/** Isi awal `Announcement/FormPage` saat menyunting (fe-v2-design §4.7). */
final class AnnouncementFormData extends Data
{
    /**
     * @param  list<string>  $targetRoles
     * @param  list<AnnouncementFileData>  $files
     */
    public function __construct(
        public int $id,
        public string $title,
        public string $content,
        public string $type,
        public ?string $expiredOn,
        public array $targetRoles,
        public array $files,
    ) {}

    public static function of(Announcement $announcement): self
    {
        $announcement->loadMissing(['targetRoles', 'announcementFiles']);

        return new self(
            id: $announcement->id,
            title: $announcement->title,
            // K6 — baris lama yang ditulis sebelum saringan tidak masuk editor mentah.
            content: AnnouncementHtml::sanitize($announcement->content),
            type: $announcement->type,
            expiredOn: $announcement->expired_at?->format('Y-m-d'),
            targetRoles: $announcement->targetRoleNames(),
            files: array_values($announcement->announcementFiles
                ->map(static fn (AnnouncementFile $file): AnnouncementFileData => AnnouncementFileData::of($file))
                ->all()),
        );
    }
}
