<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use App\Models\SubmissionPeriod;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-32 — satu pintu "versi struktur angkatan ini": versi pakuan, atau versi terbaru bila angkatan
 * belum punya pengajuan. Pengajuan yang sudah ada membaca versi langkahnya sendiri, bukan kueri ini.
 *
 * @method static FormPhaseVersion|null run(SubmissionPeriod $period, FormPhase $phase)
 */
class PhaseVersionFor implements ChangesNoState
{
    use AsAction;

    public function handle(SubmissionPeriod $period, FormPhase $phase): ?FormPhaseVersion
    {
        $pinned = PhaseVersionPin::query()
            ->where('submission_period_id', $period->id)
            ->where('form_phase_id', $phase->id)
            ->first();

        return $pinned->formPhaseVersion ?? $phase->latestVersion()->first();
    }
}
