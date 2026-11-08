<?php

namespace App\Data\Reporting;

use App\Support\Identity\RoleCatalog;
use Illuminate\Http\Request;
use Spatie\LaravelData\Data;

/**
 * W155 — filter Audit Log seperti frame OPERATOR-07-03: cari, Waktu (bawaan 7 hari), Tipe Aksi,
 * Pelaku (peran DDD atau Sistem). Nilai di luar pilihan kembali ke bawaan.
 */
final class AuditFilters extends Data
{
    public const SYSTEM_ACTOR = 'system';

    public const DEFAULT_RANGE = '7d';

    /** @var array<string, array{label: string, days: int|null}> */
    public const RANGES = [
        '7d' => ['label' => '7 hari', 'days' => 7],
        '30d' => ['label' => '30 hari', 'days' => 30],
        '90d' => ['label' => '90 hari', 'days' => 90],
        'all' => ['label' => 'Semua', 'days' => null],
    ];

    public function __construct(
        public string $search = '',
        public string $range = self::DEFAULT_RANGE,
        public string $action = '',
        public string $actor = '',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $range = $request->string('range')->value();
        $actor = $request->string('actor')->value();
        $knownActor = $actor === self::SYSTEM_ACTOR || array_key_exists($actor, RoleCatalog::LABELS);

        return new self(
            search: trim($request->string('search')->value()),
            range: array_key_exists($range, self::RANGES) ? $range : self::DEFAULT_RANGE,
            action: $request->string('action')->value(),
            actor: $knownActor ? $actor : '',
        );
    }

    public function days(): ?int
    {
        return self::RANGES[$this->range]['days'] ?? null;
    }
}
