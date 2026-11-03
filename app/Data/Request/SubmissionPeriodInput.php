<?php

namespace App\Data\Request;

/** Masukan satu periode: nama, batas akhir per (jenis, siklus), dan tahap yang dibuka. */
final class SubmissionPeriodInput
{
    /**
     * @param  list<PeriodDateInput>  $dates
     * @param  list<int>  $formPhaseIds
     */
    public function __construct(
        public readonly string $name,
        public readonly array $dates,
        public readonly array $formPhaseIds,
    ) {}
}
