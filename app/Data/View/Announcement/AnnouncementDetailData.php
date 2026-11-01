<?php

namespace App\Data\View\Announcement;

use App\Models\Announcement;
use App\Models\AnnouncementFile;
use App\Support\Announcement\AnnouncementHtml;
use Spatie\LaravelData\Data;

/** Isi `Announcement/ShowPage` (fe-v2-design §4.7); `isRead` null untuk tamu. */
final class AnnouncementDetailData extends Data
{
    /** @param  list<AnnouncementFileData>  $files */
    public function __construct(
        public int $id,
        public string $title,
        public string $contentHtml,
        public string $publishedAt,
        public ?string $expiresAt,
        public ?string $author,
        public array $files,
        public ?bool $isRead,
    ) {}

    public static function of(Announcement $announcement, ?bool $isRead): self
    {
        $announcement->loadMissing(['announcementFiles', 'announcementCreator']);

        return new self(
            id: $announcement->id,
            title: $announcement->title,
            // K6 — disaring juga saat dibaca: baris lama tanpa saringan tidak lolos mentah ke v-html.
            contentHtml: AnnouncementHtml::sanitize($announcement->content),
            publishedAt: $announcement->created_at?->toIso8601String() ?? '',
            expiresAt: $announcement->expired_at?->toIso8601String(),
            author: $announcement->announcementCreator?->name,
            files: array_values($announcement->announcementFiles
                ->map(static fn (AnnouncementFile $file): AnnouncementFileData => AnnouncementFileData::of($file))
                ->all()),
            isRead: $isRead,
        );
    }
}
