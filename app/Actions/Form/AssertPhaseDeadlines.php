<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Models\FormPhase;
use App\Models\SubmissionDate;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-17 (ADR 0014) — tenggat detail tahap milik periode yang memakai tahap itu. Hanya
 * tenggat baru atau yang dipindah yang diperiksa; yang tak diubah tetap sah. Hanya membaca
 * dan menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(?FormPhase $phase, list<FormPhaseDetailInput> $details, array<int, int> $currentDates = [])
 */
class AssertPhaseDeadlines implements ChangesNoState
{
    use AsAction;

    /**
     * @param  FormPhase|null  $phase  null saat tahap belum ada
     * @param  list<FormPhaseDetailInput>  $details
     * @param  array<int, int>  $currentDates  tenggat detail lama per id detail
     *
     * @throws ValidationException
     */
    public function handle(?FormPhase $phase, array $details, array $currentDates = []): void
    {
        $changed = array_filter(
            $details,
            fn (FormPhaseDetailInput $detail): bool => $this->isNewOrMoved($detail, $currentDates),
        );

        if ($changed === []) {
            return;
        }

        $allowed = SubmissionDate::query()
            ->selectableFor($phase)
            ->whereIn('id', array_map(fn (FormPhaseDetailInput $detail): ?int => $detail->submissionDateId, $changed))
            ->pluck('id')
            ->all();

        $errors = [];

        foreach ($changed as $index => $detail) {
            if (!in_array($detail->submissionDateId, $allowed, true)) {
                $errors["phase_details.{$index}.submission_date_id"] = 'Tenggat harus milik periode yang '
                    .'memakai tahap ini, atau periode yang belum ditutup paksa bila belum ada.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** @param  array<int, int>  $currentDates */
    private function isNewOrMoved(FormPhaseDetailInput $detail, array $currentDates): bool
    {
        if ($detail->submissionDateId === null) {
            return false;
        }

        return $detail->id === null || ($currentDates[$detail->id] ?? null) !== $detail->submissionDateId;
    }
}
