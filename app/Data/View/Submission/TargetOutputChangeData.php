<?php

namespace App\Data\View\Submission;

use Spatie\LaravelData\Data;

/** Satu perubahan target luaran untuk "Jejak Perubahan Target"; `from`/`to` berupa nama tipe. */
final class TargetOutputChangeData extends Data
{
    public function __construct(
        public string $description,
        public ?string $actor,
        public ?string $at,
        public ?string $from,
        public ?string $to,
    ) {}
}
