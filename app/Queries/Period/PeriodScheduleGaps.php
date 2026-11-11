<?php

namespace App\Queries\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\PeriodDateInput;
use App\Enums\DeadlineType;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-26 (ADR 0019) — celah jadwal: tanggal yang dibutuhkan langkah fase tertaut tetapi belum ada
 * di kalender periode. Tanggal kerja yang hilang menutup langkahnya; Batas Evaluasi yang hilang
 * membuat evaluasi langkah yang direview tak bertenggat. Keduanya dilaporkan.
 *
 * @method static list<array{phase: string, step: int, label: string}> run(SubmissionPeriod $period)
 */
class PeriodScheduleGaps implements ChangesNoState
{
    use AsAction;

    /** @return list<array{phase: string, step: int, label: string}> */
    public function handle(SubmissionPeriod $period): array
    {
        $steps = FormPhaseDetail::query()
            ->whereIn('form_phase_id', $period->submissionPeriodPhases()->select('form_phase_id'))
            ->with('formPhase:id,title')
            ->orderBy('form_phase_id')
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        $gaps = [];

        foreach ($steps as $step) {
            foreach (self::missingTypes($period, $step) as $type) {
                $gaps[] = [
                    'phase' => $step->formPhase->title ?? '',
                    'step' => $step->order,
                    'label' => $type->label($step->deadline_cycle),
                ];
            }
        }

        return $gaps;
    }

    /**
     * Kartu fase ADMIN-08-08: per periode tertaut yang belum tutup sepenuhnya, banyak tanggal
     * (bukan langkah) yang masih kurang. Periode lama yang sudah tutup tidak perlu dikejar.
     *
     * @param  Collection<int, FormPhase>  $phases
     * @return array<int, list<array{period: string, count: int}>>
     */
    public static function forPhases(Collection $phases): array
    {
        $steps = FormPhaseDetail::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->get();
        $links = SubmissionPeriodPhase::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with('submissionPeriod.submissionDates')
            ->get()
            ->sortBy(fn (SubmissionPeriodPhase $link): string => $link->submissionPeriod->name ?? '');

        $gaps = [];

        foreach ($links as $link) {
            $period = $link->submissionPeriod;

            if ($period === null || SubmissionPeriodGate::isFullyClosed($period)) {
                continue;
            }

            $count = self::missingDateCount($period, $steps->where('form_phase_id', $link->form_phase_id)->all());

            if ($count > 0) {
                $gaps[$link->form_phase_id][] = ['period' => $period->name, 'count' => $count];
            }
        }

        return $gaps;
    }

    /** Banyak tanggal yang masih kurang di `$period` bagi langkah `$phase`. */
    public static function missingCount(SubmissionPeriod $period, FormPhase $phase): int
    {
        return self::missingDateCount($period, $phase->formPhaseDetails()->get()->all());
    }

    /**
     * Dua langkah bersiklus sama membaca satu tanggal (FE-WINDOW-04), jadi dihitung per (jenis, siklus).
     *
     * @param  array<int, FormPhaseDetail>  $steps
     */
    private static function missingDateCount(SubmissionPeriod $period, array $steps): int
    {
        $keys = [];

        foreach ($steps as $step) {
            foreach (self::missingTypes($period, $step) as $type) {
                $keys[PeriodDateInput::keyOf($type, $step->deadline_cycle)] = true;
            }
        }

        return count($keys);
    }

    /** @return list<DeadlineType> */
    private static function missingTypes(SubmissionPeriod $period, FormPhaseDetail $step): array
    {
        $needed = $step->needs_review ? [$step->deadline_type, DeadlineType::Evaluation] : [$step->deadline_type];

        return array_values(array_filter(
            $needed,
            fn (DeadlineType $type): bool => $period->deadlineFor($step, $type) === null,
        ));
    }
}
