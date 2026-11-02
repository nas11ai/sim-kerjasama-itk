<?php

namespace App\Data\View\FormEngine;

use App\Enums\PeriodStatus;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Queries\Period\PeriodDraftOwners;
use App\Queries\Period\SubmissionPeriodReferences;
use Spatie\LaravelData\Data;

/**
 * Satu baris OPERATOR-09-01. Frontend tidak menghitung apa pun sendiri: status,
 * kelayakan tutup, dan penghalang hapus dikirim dari definisi backend-nya.
 */
final class PeriodRowData extends Data
{
    /**
     * @param  array{value: string, label: string}  $status
     * @param  list<PeriodDateData>  $dates
     * @param  list<int>  $formPhaseIds
     * @param  list<array{label: string, count: int}>  $deleteBlockers
     */
    public function __construct(
        public int $id,
        public string $name,
        public array $status,
        public array $dates,
        public array $formPhaseIds,
        public int $submittedCount,
        public int $draftOwnerCount,
        public bool $closable,
        public array $deleteBlockers,
    ) {}

    /**
     * Relasi tanggal, tahap, dan `submitted_count` dimuat SubmissionPeriodList. Rujukan
     * dan pemilik draft dibaca lewat Query yang juga dipakai penegaknya (BR-FE-21,
     * BR-SCH-08), bukan salinannya — beberapa kueri per baris di layar admin.
     */
    public static function fromModel(SubmissionPeriod $period): self
    {
        $status = PeriodStatus::of($period);

        return new self(
            id: $period->id,
            name: $period->name,
            status: $status->option(),
            dates: array_values($period->submissionDates
                ->sortBy(static fn (SubmissionDate $date): string => $date->datetime->toIso8601String())
                ->map(static fn (SubmissionDate $date): PeriodDateData => PeriodDateData::fromModel($date))
                ->all()),
            formPhaseIds: array_values($period->submissionPeriodPhases
                ->map(static fn (SubmissionPeriodPhase $phase): int => $phase->form_phase_id)
                ->all()),
            submittedCount: $period->submitted_count,
            draftOwnerCount: PeriodDraftOwners::run($period)->count(),
            closable: $status->closable(),
            deleteBlockers: self::blockers($period),
        );
    }

    /** @return list<array{label: string, count: int}> */
    private static function blockers(SubmissionPeriod $period): array
    {
        $blockers = [];

        foreach (SubmissionPeriodReferences::run($period) as $label => $count) {
            if ($count > 0) {
                $blockers[] = ['label' => $label, 'count' => $count];
            }
        }

        return $blockers;
    }
}
