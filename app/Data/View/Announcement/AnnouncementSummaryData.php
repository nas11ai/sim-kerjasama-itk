<?php

namespace App\Data\View\Announcement;

use App\Models\Announcement;
use App\Support\Announcement\AnnouncementHtml;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * Satu kartu pengumuman bagi pembaca dan panel login (fe-v2-design §4.7). `excerpt` teks
 * polos, bukan HTML; `isRead` null untuk tamu.
 */
final class AnnouncementSummaryData extends Data
{
    private const EXCERPT_LENGTH = 160;

    public function __construct(
        public int $id,
        public string $title,
        public string $excerpt,
        public string $publishedAt,
        public int $attachmentCount,
        public ?bool $isRead,
    ) {}

    /** `attachmentCount` dibaca dari `withCount('announcementFiles')` bila ada. */
    public static function of(Announcement $announcement, ?bool $isRead): self
    {
        $count = $announcement->getAttribute('announcement_files_count');

        return new self(
            id: $announcement->id,
            title: $announcement->title,
            excerpt: Str::limit(AnnouncementHtml::plainText($announcement->content), self::EXCERPT_LENGTH),
            publishedAt: $announcement->created_at?->toIso8601String() ?? '',
            attachmentCount: is_int($count) ? $count : 0,
            isRead: $isRead,
        );
    }
}
