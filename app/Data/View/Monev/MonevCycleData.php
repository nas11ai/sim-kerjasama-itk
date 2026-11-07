<?php

namespace App\Data\View\Monev;

use App\Enums\MonevCycleState;
use Spatie\LaravelData\Data;

/**
 * Satu siklus monev milik sebuah induk sebagaimana dirender layar. Keadaan, alasan terkunci,
 * dan nama dihitung backend (`MonevCycles`), jadi Vue tidak menurunkannya sendiri.
 */
final class MonevCycleData extends Data
{
    public function __construct(
        public int $detailId,
        public string $name,
        public int $order,
        public bool $needsReview,
        public ?string $deadline,
        public MonevCycleState $state,
        public ?string $blockedReason,
        public ?int $reportId,
        public ?string $submittedAt,
        public ?string $openedAt,
    ) {}
}
