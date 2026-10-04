<?php

namespace App\Data\Request;

/** Tahap pilihan wizard: yang ada bila `existingPhaseId` terisi, selain itu tahap baru. */
final class FormBuilderPhaseInput
{
    public function __construct(
        public readonly ?int $existingPhaseId,
        public readonly string $newTitle,
        public readonly ?string $newDescription,
        public readonly int $phaseTypeId,
        public readonly bool $needsReview,
    ) {}
}
