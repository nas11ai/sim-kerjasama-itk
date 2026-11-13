<?php

namespace App\Actions\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Request\FormPhaseDetailInput;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-26 (ADR 0019, K5) — langkah Upload Luaran tidak bersiklus dan tidak direview (W139);
 * BR-FE-28 — tepat satu Form pengajuan per tahap, di langkah pertama. Kumpulan galat dilempar
 * sekali. Hanya membaca dan menolak, **tidak menulis audit (BR-RPT-09).**
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
        $errors = [...$this->outputStepErrors($details), ...$this->submissionFormErrors($details)];

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<FormPhaseDetailInput>  $details
     * @return array<string, string>
     */
    private function outputStepErrors(array $details): array
    {
        $errors = [];

        foreach ($details as $index => $detail) {
            if ($detail->deadlineType !== DeadlineType::Output) {
                continue;
            }

            if ($detail->deadlineCycle !== null) {
                $errors["phase_details.{$index}.deadline_cycle"] = 'Unggah Luaran tidak bersiklus.';
            }

            if ($detail->needsReview) {
                $errors["phase_details.{$index}.needs_review"] = 'Detail Unggah Luaran tidak dinilai.';
            }
        }

        return $errors;
    }

    /**
     * BR-FE-28 — yang dihitung Form-nya, bukan aturan aksesnya: satu Form untuk dua fakultas
     * tetap satu Form pengajuan.
     *
     * @param  list<FormPhaseDetailInput>  $details
     * @return array<string, string>
     */
    private function submissionFormErrors(array $details): array
    {
        $forms = $this->formsOf($details);
        $submissionForms = $this->submissionForms($details, $forms);
        $errors = [];

        if ($submissionForms === []) {
            $errors['phase_details'] = 'Tahap butuh satu langkah Pengajuan.';
        }

        if (count($submissionForms) > 1) {
            $errors['phase_details'] = 'Satu tahap hanya untuk satu Formulir pengajuan: '
                .implode(', ', $submissionForms).'. Buat tahap terpisah untuk tiap Formulir.';
        }

        if ($details !== [] && !$details[0]->opensSubmission()) {
            $errors['phase_details.0.deadline_type'] = 'Langkah pertama tahap harus langkah Pengajuan.';
        }

        foreach ($details as $index => $detail) {
            $formId = $forms->get($detail->formAccessControlId)?->id;

            if (!$detail->opensSubmission() && isset($submissionForms[$formId])) {
                $errors["phase_details.{$index}.form_access_control_id"] =
                    'Laporan Monev dan Unggah Luaran memakai Formulir selain Formulir pengajuan.';
            }
        }

        return $errors;
    }

    /**
     * Judul Form pengajuan per id Form, urut kemunculan pertama.
     *
     * @param  list<FormPhaseDetailInput>  $details
     * @param  Collection<int, Form>  $forms
     * @return array<int, string>
     */
    private function submissionForms(array $details, Collection $forms): array
    {
        $titles = [];

        foreach ($details as $detail) {
            $form = $forms->get($detail->formAccessControlId);

            if ($detail->opensSubmission() && $form !== null) {
                $titles[$form->id] ??= $form->title;
            }
        }

        return $titles;
    }

    /**
     * Form tiap aturan akses yang dipakai, dikunci id aturan aksesnya.
     *
     * @param  list<FormPhaseDetailInput>  $details
     * @return Collection<int, Form>
     */
    private function formsOf(array $details): Collection
    {
        $accessIds = array_unique(array_map(fn (FormPhaseDetailInput $d): int => $d->formAccessControlId, $details));

        return FormAccessControl::query()
            ->whereIn('id', $accessIds)
            ->with('form:id,title')
            ->get(['id', 'form_id'])
            ->toBase()
            ->mapWithKeys(fn (FormAccessControl $access): array => $access->form === null ? [] : [$access->id => $access->form]);
    }
}
