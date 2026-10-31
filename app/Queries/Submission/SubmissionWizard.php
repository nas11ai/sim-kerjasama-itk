<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\Submission\SubmissionAbilitiesData;
use App\Data\View\Submission\SubmissionTargetOutputData;
use App\Data\View\Submission\Wizard\WizardFieldData;
use App\Data\View\Submission\Wizard\WizardReviewData;
use App\Data\View\Submission\Wizard\WizardStepData;
use App\Data\View\Submission\Wizard\WizardSubmissionData;
use App\Data\View\SubmissionStatusData;
use App\Models\FormField;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\WizardStepGate;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Props Submission/WizardPage untuk satu langkah; hanya langkah aktif yang dimuat. Hak
 * menyunting (`update`, BR-SM-07) dijaga controller sebelum Query ini dipanggil.
 *
 * @phpstan-type Wizard array{step: int, steps: list<WizardStepData>, submission: WizardSubmissionData, fields: list<WizardFieldData>, targetOutputs: list<SubmissionTargetOutputData>, targetOutputTypes: list<array{value: string, label: string}>, review: WizardReviewData|null, can: SubmissionAbilitiesData}
 *
 * @method static Wizard run(User $viewer, FormSubmission $submission, int $step)
 */
class SubmissionWizard implements ChangesNoState
{
    use AsAction;

    /** @return Wizard */
    public function handle(User $viewer, FormSubmission $submission, int $step): array
    {
        $submission->loadMissing(['form:id,title', 'submissionPeriod:id,name']);
        $isSchemeStep = $step === WizardStepGate::STEP_SCHEME;

        return [
            'step' => $step,
            'steps' => $this->steps($submission),
            'submission' => $this->header($submission),
            'fields' => $this->fields($submission, $step),
            'targetOutputs' => $isSchemeStep ? SubmissionTargetOutputs::run($submission) : [],
            'targetOutputTypes' => $isSchemeStep ? $this->targetOutputTypes($submission) : [],
            'review' => $step === WizardStepGate::STEP_REVIEW ? WizardReviewSummary::run($viewer, $submission) : null,
            'can' => SubmissionAbilitiesData::forUser($viewer, $submission),
        ];
    }

    /**
     * Stepper sebelum baris pengajuan lahir: hanya langkah 1 yang bisa diisi (ADR 0012).
     *
     * @return list<WizardStepData>
     */
    public static function stepsBeforeStart(): array
    {
        $steps = [];

        foreach (WizardStepGate::LABELS as $number => $label) {
            $blocked = $number === WizardStepGate::STEP_FORM_PERIOD ? null : 'Pilih form dan periode lebih dulu.';
            $steps[] = new WizardStepData($number, $label, false, $blocked);
        }

        return $steps;
    }

    /** @return list<WizardStepData> */
    private function steps(FormSubmission $submission): array
    {
        $steps = [];

        foreach (WizardStepGate::LABELS as $number => $label) {
            $steps[] = new WizardStepData(
                number: $number,
                label: $label,
                completed: $number <= $submission->last_completed_step,
                blockedReason: WizardStepGate::blockedReason($submission, $number),
            );
        }

        return $steps;
    }

    private function header(FormSubmission $submission): WizardSubmissionData
    {
        return new WizardSubmissionData(
            id: $submission->id,
            title: $submission->form->title,
            status: SubmissionStatusData::of($submission),
            period: $submission->submissionPeriod?->name,
            form: $submission->form->title,
            lastUpdatedAt: $submission->updated_at?->toISOString() ?? '',
        );
    }

    /** @return list<WizardFieldData> */
    private function fields(FormSubmission $submission, int $step): array
    {
        $values = SubmissionAnswers::run($submission);
        $fields = [];

        foreach ($submission->form->formFields as $field) {
            $type = $field->fieldType->name ?? '';

            if (WizardStepGate::stepForFieldType($type) === $step) {
                $fields[] = $this->field($submission, $field, $type, $values[$field->id] ?? null);
            }
        }

        return $fields;
    }

    private function field(FormSubmission $submission, FormField $field, string $type, ?string $value): WizardFieldData
    {
        $isFile = $type === 'file';
        $hasFile = $isFile && $value !== null && $value !== '';

        return new WizardFieldData(
            id: $field->id,
            label: $field->label,
            type: $type,
            // BR-FE-11 — kewajiban dihitung terhadap created_at pengajuan ini.
            required: $field->isRequiredFor($submission),
            // Path penyimpanan berkas tidak dikirim ke browser; yang tampil nama dan tautannya.
            value: $isFile ? null : $value,
            options: WizardFieldOptions::run($submission, $field, $type),
            fileName: $hasFile ? basename($value) : null,
            downloadUrl: null,
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function targetOutputTypes(FormSubmission $submission): array
    {
        return array_map(
            fn (array $type): array => ['value' => $type['code'], 'label' => $type['name']],
            TargetOutputTypeChoices::run($submission),
        );
    }
}
