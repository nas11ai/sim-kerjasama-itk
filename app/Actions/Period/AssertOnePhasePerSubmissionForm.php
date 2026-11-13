<?php

namespace App\Actions\Period;

use App\Actions\Concerns\ChangesNoState;
use App\Queries\Form\PhaseSubmissionForms;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-29 — satu periode paling banyak punya satu tahap per Form pengajuan. Peneliti memilih Form,
 * bukan tahap (`ResearcherStartOptions`), jadi tahap kedua dengan Form yang sama tak terjangkau.
 * Dipanggil semua jalur yang menautkan tahap ke periode, dengan daftar tahap periode sesudahnya.
 *
 * @method static void run(list<int> $phaseIds, string $errorKey)
 */
class AssertOnePhasePerSubmissionForm implements ChangesNoState
{
    use AsAction;

    /** @param  list<int>  $phaseIds */
    public function handle(array $phaseIds, string $errorKey): void
    {
        $phasesByForm = [];

        foreach (PhaseSubmissionForms::forPhases($phaseIds) as $entry) {
            $phasesByForm[$entry['formId']][] = $entry;
        }

        foreach ($phasesByForm as $entries) {
            if (count($entries) < 2) {
                continue;
            }

            throw ValidationException::withMessages([$errorKey => sprintf(
                'Tahap %s sama-sama memakai Formulir pengajuan %s. Satu periode hanya boleh punya satu tahap per Formulir pengajuan.',
                implode(' dan ', array_column($entries, 'phaseTitle')),
                $entries[0]['formTitle'],
            )]);
        }
    }
}
