<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\SubmissionPeriod;
use App\Models\SubmissionPeriodPhase;
use App\Support\SubmissionPeriodGate;
use Illuminate\Support\Collection;

/**
 * Periode yang boleh ditautkan ke tiap tahap (K9b): belum ditutup paksa, belum sepenuhnya
 * tutup, belum tertaut. Sengaja tanpa syarat pemilik tenggat: menautkan sebelum memindah
 * tenggat adalah langkah sah.
 */
class LinkablePeriods implements ChangesNoState
{
    /**
     * @param  Collection<int, FormPhase>  $phases
     * @return array<int, list<array{value: string, label: string}>>
     */
    public static function forPhases(Collection $phases): array
    {
        $candidates = SubmissionPeriod::query()
            ->where('is_force_closed', false)
            ->with('submissionDates')
            ->orderBy('name')
            ->get()
            ->reject(fn (SubmissionPeriod $period): bool => SubmissionPeriodGate::isFullyClosed($period));

        $linked = SubmissionPeriodPhase::query()
            ->whereIn('form_phase_id', $phases->pluck('id'))
            ->get()
            ->groupBy('form_phase_id');

        $options = [];

        foreach ($phases as $phase) {
            $taken = ($linked[$phase->id] ?? collect())->pluck('submission_period_id')->all();

            $options[$phase->id] = [];

            foreach ($candidates as $period) {
                if (!in_array($period->id, $taken, true)) {
                    $options[$phase->id][] = ['value' => (string) $period->id, 'label' => $period->name];
                }
            }
        }

        return $options;
    }
}
