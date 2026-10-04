<?php

namespace App\Data\Request;

/** Periode pilihan wizard: yang ada bila `existingPeriodId` terisi, selain itu baru. */
final class FormBuilderPeriodInput
{
    /**
     * @param  list<array{labelId: int, date: string}>  $dates  hanya untuk periode baru
     */
    public function __construct(
        public readonly ?int $existingPeriodId,
        public readonly string $newName,
        public readonly array $dates,
    ) {}
}
