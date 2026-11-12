<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormPhase;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Dampak Terbitkan tahap (BR-FE-33): label versi yang akan lahir, dan berapa angkatan yang sudah
 * terpaku ke versi lama dan tetap memakainya (BR-FE-32). Satu baris pin = satu angkatan, dan
 * semua pin menunjuk versi yang sudah ada, jadi semuanya lebih lama dari versi berikutnya.
 *
 * @method static array{nextLabel: string, pinnedCohorts: int} run(FormPhase $phase)
 */
class PhasePublishImpact implements ChangesNoState
{
    use AsAction;

    /** @return array{nextLabel: string, pinnedCohorts: int} */
    public function handle(FormPhase $phase): array
    {
        $next = ($phase->latestVersion()->first()->number ?? 0) + 1;

        return [
            'nextLabel' => FormPhaseVersion::labelFor($next),
            'pinnedCohorts' => PhaseVersionPin::query()->where('form_phase_id', $phase->id)->count(),
        ];
    }
}
