<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Enums\DeadlineType;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-26 (ADR 0019, K5) — langkah Upload Luaran (`output`) tidak bersiklus dan tidak direview
 * (W139, DDD monev Detail 7). Hanya membaca dan menolak, **tidak menulis audit (BR-RPT-09).**
 *
 * @method static void run(list<FormPhaseDetailInput> $details)
 */
class AssertPhaseSteps implements ChangesNoState
{
    use AsAction;

    /**
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    public function handle(array $details): void
    {
        $errors = [];

        foreach ($details as $index => $detail) {
            if ($detail->deadlineType !== DeadlineType::Output) {
                continue;
            }

            if ($detail->deadlineCycle !== null) {
                $errors["phase_details.{$index}.deadline_cycle"] = 'Upload Luaran tidak bersiklus.';
            }

            if ($detail->needsReview) {
                $errors["phase_details.{$index}.needs_review"] = 'Detail Upload Luaran tidak direview.';
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
