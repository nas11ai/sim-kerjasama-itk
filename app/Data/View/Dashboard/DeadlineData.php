<?php

namespace App\Data\View\Dashboard;

use Carbon\CarbonInterface;
use Spatie\LaravelData\Data;

/** Satu baris "Deadline Mendekat"; `closesAt` ISO 8601, `daysLeft` per hari kalender. */
final class DeadlineData extends Data
{
    /** W43 — satu ambang "mendekat" untuk daftar deadline dan meta kartu reviewer. */
    public const URGENT_DAYS = 5;

    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        public string $title,
        public string $closesAt,
        public int $daysLeft,
        public bool $urgent,
        public ?string $href,
    ) {}

    public static function until(string $title, CarbonInterface $closesAt, ?string $href): self
    {
        // Hari kalender di zona aplikasi: tenggat ISO dari ReviewerAssignmentCards berzona UTC.
        $today = now()->startOfDay();
        $daysLeft = intdiv(
            $closesAt->copy()->setTimezone($today->getTimezone())->startOfDay()->getTimestamp() - $today->getTimestamp(),
            self::SECONDS_PER_DAY,
        );

        return new self($title, $closesAt->toIso8601String(), $daysLeft, $daysLeft <= self::URGENT_DAYS, $href);
    }
}
