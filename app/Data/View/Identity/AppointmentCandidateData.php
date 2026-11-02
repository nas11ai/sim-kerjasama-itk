<?php

namespace App\Data\View\Identity;

use App\Enums\ReviewerStanding;
use App\Models\Reviewer;
use App\Models\User;
use App\Support\Identity\ReviewerTypeCatalog;
use Spatie\LaravelData\Data;

/**
 * Kandidat penunjukan reviewer (BR-IAM-11). `currentReviewer` terisi = penunjukan memperbarui
 * record yang ada (IAM-REVIEWER-04); relasi `userProfile`, `organization`, `reviewers` dimuat
 * AppointmentCandidates.
 */
final class AppointmentCandidateData extends Data
{
    /**
     * @param  array{type: array{value: string, label: string}, tenureStart: ?string, tenureEnd: ?string, standing: array{value: string, label: string}}|null  $currentReviewer
     */
    public function __construct(
        public int $userId,
        public string $name,
        public string $email,
        public ?string $nidn,
        public ?string $organization,
        public ?array $currentReviewer,
    ) {}

    public static function fromModel(User $user): self
    {
        $reviewer = $user->reviewers->first();

        return new self(
            userId: $user->id,
            name: $user->name,
            email: $user->email,
            nidn: $user->userProfile?->nidn,
            organization: $user->organization?->name,
            currentReviewer: $reviewer instanceof Reviewer ? self::current($reviewer) : null,
        );
    }

    /** @return array{type: array{value: string, label: string}, tenureStart: ?string, tenureEnd: ?string, standing: array{value: string, label: string}} */
    private static function current(Reviewer $reviewer): array
    {
        return [
            'type' => ReviewerTypeCatalog::option($reviewer->reviewer_type),
            'tenureStart' => $reviewer->start_date?->toDateString(),
            'tenureEnd' => $reviewer->end_date?->toDateString(),
            'standing' => ReviewerStanding::of($reviewer)->option(),
        ];
    }
}
