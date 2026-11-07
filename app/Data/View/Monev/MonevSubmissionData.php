<?php

namespace App\Data\View\Monev;

use App\Data\View\SubmissionStatusData;
use Spatie\LaravelData\Data;

/**
 * Satu kartu RESEARCHER-05-01: induk beserta siklus monevnya. `canFill` = ketua induk
 * (BR-SM-04, W123); anggota tim membaca tanpa tombol isi.
 */
final class MonevSubmissionData extends Data
{
    /** @param list<MonevCycleData> $cycles */
    public function __construct(
        public int $id,
        public string $title,
        public ?string $scheme,
        public SubmissionStatusData $status,
        public bool $canFill,
        public array $cycles,
    ) {}
}
