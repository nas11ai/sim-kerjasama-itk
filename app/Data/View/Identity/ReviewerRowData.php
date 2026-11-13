<?php

namespace App\Data\View\Identity;

use App\Enums\ReviewerStanding;
use App\Models\Reviewer;
use App\Support\Identity\ReviewerTypeCatalog;
use Spatie\LaravelData\Data;

/**
 * Satu baris OPERATOR-08-04; `user` dan `running_assignments` (withCount) dimuat
 * ReviewerDirectory. W54 — beban = penugasan aktif yang evaluasinya belum beres.
 */
final class ReviewerRowData extends Data
{
    /**
     * @param  array{value: string, label: string}  $type
     * @param  array{value: string, label: string}  $standing
     */
    public function __construct(
        public int $id,
        public int $userId,
        public string $name,
        public array $type,
        public ?string $tenureStart,
        public ?string $tenureEnd,
        public int $runningAssignments,
        public array $standing,
        public ?string $historyUrl,
    ) {}

    public static function fromModel(Reviewer $reviewer, bool $linksHistory): self
    {
        $running = $reviewer->getAttribute('running_assignments');

        return new self(
            id: $reviewer->id,
            userId: $reviewer->user_id,
            name: $reviewer->user->name ?? '-',
            type: ReviewerTypeCatalog::option($reviewer->reviewer_type),
            tenureStart: $reviewer->start_date?->toDateString(),
            tenureEnd: $reviewer->end_date?->toDateString(),
            runningAssignments: is_int($running) ? $running : 0,
            standing: ReviewerStanding::of($reviewer)->option(),
            historyUrl: $linksHistory ? route('admin.users.show', ['user' => $reviewer->user_id, 'tab' => 'reviews']) : null,
        );
    }
}
