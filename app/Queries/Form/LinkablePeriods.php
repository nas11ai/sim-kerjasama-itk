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
 * tutup, belum tertaut, dan belum punya tahap lain ber-Form pengajuan sama (BR-FE-29). Sengaja
 * tanpa syarat pemilik tenggat: menautkan sebelum memindah tenggat adalah langkah sah.
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

        $periodLinks = SubmissionPeriodPhase::query()
            ->whereIn('submission_period_id', $candidates->pluck('id'))
            ->get();
        $forms = PhaseSubmissionForms::forPhases(array_values(array_unique([
            ...$phases->map(fn (FormPhase $phase): int => $phase->id)->all(),
            ...$periodLinks->map(fn (SubmissionPeriodPhase $link): int => $link->form_phase_id)->all(),
        ])));
        $formsInPeriod = self::formsInPeriod($periodLinks, $forms);

        $options = [];

        foreach ($phases as $phase) {
            $taken = ($linked[$phase->id] ?? collect())->pluck('submission_period_id')->all();
            $form = $forms[$phase->id]['formId'] ?? null;

            $options[$phase->id] = [];

            foreach ($candidates as $period) {
                if (in_array($period->id, $taken, true) || in_array($form, $formsInPeriod[$period->id] ?? [], true)) {
                    continue;
                }

                $options[$phase->id][] = ['value' => (string) $period->id, 'label' => $period->name];
            }
        }

        return $options;
    }

    /**
     * BR-FE-29 — id Form pengajuan yang sudah dipakai tahap tertaut, per id periode.
     *
     * @param  Collection<int, SubmissionPeriodPhase>  $links
     * @param  array<int, array{formId: int, formTitle: string, phaseTitle: string}>  $forms
     * @return array<int, list<int>>
     */
    private static function formsInPeriod(Collection $links, array $forms): array
    {
        $byPeriod = [];

        foreach ($links as $link) {
            if (isset($forms[$link->form_phase_id])) {
                $byPeriod[$link->submission_period_id][] = $forms[$link->form_phase_id]['formId'];
            }
        }

        return $byPeriod;
    }
}
