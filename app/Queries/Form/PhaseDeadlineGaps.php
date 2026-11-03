<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionPeriodPhase;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Collection;

/**
 * BR-FE-17 / ADR 0014 — keadaan antara K9b: per periode tertaut, berapa detail tahap yang
 * Batas Submit-nya bukan milik periode itu (jadi belum terbuka di sana).
 */
class PhaseDeadlineGaps implements ChangesNoState
{
    /**
     * @param  Collection<int, FormPhase>  $phases
     * @return array<int, list<array{period: string, count: int}>>
     */
    public static function forPhases(Collection $phases): array
    {
        $ownerPeriods = [];

        foreach (FormPhaseDetail::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with('submissionDate:id,submission_period_id')
            ->get(['id', 'form_phase_id', 'submission_date_id']) as $detail) {
            $ownerPeriods[$detail->form_phase_id][] = $detail->submissionDate->submission_period_id;
        }

        $links = SubmissionPeriodPhase::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->with('submissionPeriod.submissionDates')
            ->get()
            ->sortBy(fn (SubmissionPeriodPhase $link): string => $link->submissionPeriod->name ?? '');

        $gaps = [];

        foreach ($links as $link) {
            // BR-FE-17 / K9b — periode lama yang sudah tutup sepenuhnya tidak perlu dikejar
            // tenggatnya; periode terbuka atau tanpa tanggal (target baru) tetap diperingatkan.
            if (SubmissionPeriodGate::isFullyClosed($link->submissionPeriod)) {
                continue;
            }

            $count = count(array_filter(
                $ownerPeriods[$link->form_phase_id] ?? [],
                fn (int $owner): bool => $owner !== $link->submission_period_id,
            ));

            if ($count > 0) {
                $gaps[$link->form_phase_id][] = ['period' => $link->submissionPeriod->name ?? '', 'count' => $count];
            }
        }

        return $gaps;
    }
}
