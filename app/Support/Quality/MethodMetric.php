<?php

namespace App\Support\Quality;

/** Hasil ukur satu method. Readonly karena ia hasil pengukuran, bukan keadaan. */
final class MethodMetric
{
    public function __construct(
        public readonly string $name,
        public readonly int $loc,
        public readonly int $complexity,
        public readonly int $parameters,
        public readonly int $promotedParameters,
        public readonly int $returns,
    ) {}

    /** Constructor pembawa data: ia sendiri obat Long Parameter List, bukan gejalanya. */
    public function onlyPromotesProperties(): bool
    {
        return $this->parameters > 0 && $this->promotedParameters === $this->parameters;
    }
}
