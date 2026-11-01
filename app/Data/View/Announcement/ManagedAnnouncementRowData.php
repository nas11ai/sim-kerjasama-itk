<?php

namespace App\Data\View\Announcement;

use App\Models\Announcement;
use App\Support\Announcement\AnnouncementAudience;
use Spatie\LaravelData\Data;

/** Satu baris layar kelola pengumuman (fe-v2-design §4.7); yang kedaluwarsa tetap tampil. */
final class ManagedAnnouncementRowData extends Data
{
    public function __construct(
        public int $id,
        public string $title,
        public string $audienceLabel,
        public ?string $expiresAt,
        public bool $expired,
        public string $createdAt,
        public ?string $author,
    ) {}

    /** Butuh `targetRoles` dan `announcementCreator` sudah dimuat. */
    public static function of(Announcement $announcement): self
    {
        $expiresAt = $announcement->expired_at;

        return new self(
            id: $announcement->id,
            title: $announcement->title,
            audienceLabel: AnnouncementAudience::describe(
                $announcement->type,
                $announcement->targetRoleNames(),
            ),
            expiresAt: $expiresAt?->toIso8601String(),
            expired: $expiresAt !== null && $expiresAt->lessThanOrEqualTo(now()),
            createdAt: $announcement->created_at?->toIso8601String() ?? '',
            author: $announcement->announcementCreator?->name,
        );
    }
}
