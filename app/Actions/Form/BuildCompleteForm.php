<?php

namespace App\Actions\Form;

use App\Data\Request\EvaluationFormInput;
use App\Data\Request\FormBuilderFieldInput;
use App\Data\Request\FormBuilderInput;
use App\Data\Request\FormBuilderPeriodInput;
use App\Data\Request\FormBuilderPhaseInput;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\ReviewEvaluationForm;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SchemeFieldGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Wizard enam langkah dalam satu transaksi. Periode diselesaikan lebih dulu: BR-FE-07
 * mewajibkan setiap detail lahir dengan tenggat, yaitu tanggal terakhir periode itu.
 *
 * @method static Form run(FormBuilderInput $input, User $actor)
 */
class BuildCompleteForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormBuilderInput $input, User $actor): Form
    {
        // BR-SCH-05 — penjaga yang sama dengan pembuatan form di layar Form.
        SchemeFieldGuard::assertTrlHasSchemeSelector(array_map(
            fn (FormBuilderFieldInput $field): int => $field->fieldTypeId,
            $input->fields,
        ));

        return DB::transaction(function () use ($input, $actor): Form {
            $period = $this->resolvePeriod($input->period);
            $deadline = $this->latestDate($period);
            $form = $this->createForm($input);
            $phase = $this->resolvePhase($input->phase);

            // Tahap yang sudah memakai periode ini tidak ditautkan dua kali.
            $period->submissionPeriodPhases()->firstOrCreate(['form_phase_id' => $phase->id]);

            // BR-FE-17 — tenggatnya milik periode yang baru saja memakai tahap ini.
            $detailIds = $this->createDetails($form, $phase, $input, $deadline);

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record('Form dibangun lewat wizard', $form, $actor, new AuditChange(new: [
                'title' => $form->title,
                'form_phase_id' => $phase->id,
                'submission_period_id' => $period->id,
                'detail_ids' => $detailIds,
            ]));

            return $form;
        });
    }

    private function resolvePeriod(FormBuilderPeriodInput $input): SubmissionPeriod
    {
        if ($input->existingPeriodId !== null) {
            return SubmissionPeriod::findOrFail($input->existingPeriodId);
        }

        $period = SubmissionPeriod::create(['name' => $input->newName]);

        foreach ($input->dates as $date) {
            $period->submissionDates()->create([
                'submission_date_label_id' => $date['labelId'],
                'datetime' => $date['date'],
            ]);
        }

        return $period;
    }

    /** @throws ValidationException */
    private function latestDate(SubmissionPeriod $period): SubmissionDate
    {
        $latest = $period->submissionDates()->orderByDesc('datetime')->orderByDesc('id')->first();

        if ($latest === null) {
            throw ValidationException::withMessages([
                'submission_period' => 'Periode wajib punya setidaknya satu tanggal.',
            ]);
        }

        return $latest;
    }

    private function createForm(FormBuilderInput $input): Form
    {
        $form = Form::create([
            'title' => $input->title,
            'description' => $input->description,
            'form_type_id' => $input->formTypeId,
            'is_active' => $input->isActive,
        ]);

        foreach ($input->fields as $index => $field) {
            $created = $form->formFields()->create([
                'field_type_id' => $field->fieldTypeId,
                'label' => $field->label,
                'is_required' => $field->isRequired,
                'order' => $index + 1,
            ]);

            $created->formFieldOptions()->createMany(
                array_map(fn (string $label): array => ['label' => $label], $field->options),
            );
        }

        return $form;
    }

    private function resolvePhase(FormBuilderPhaseInput $input): FormPhase
    {
        if ($input->existingPhaseId !== null) {
            return FormPhase::findOrFail($input->existingPhaseId);
        }

        return FormPhase::create([
            'title' => $input->newTitle,
            'description' => $input->newDescription,
            'is_active' => true,
        ]);
    }

    /** @return list<int> */
    private function createDetails(Form $form, FormPhase $phase, FormBuilderInput $input, SubmissionDate $deadline): array
    {
        // Detail satu form berbagi satu urutan, di belakang form yang sudah ada di tahap.
        $last = filter_var($phase->formPhaseDetails()->max('order'), FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
        $detailIds = [];

        foreach ($input->accessControls as $access) {
            $detail = FormPhaseDetail::create([
                'form_phase_id' => $phase->id,
                'form_access_control_id' => FormAccessControl::create([
                    'form_id' => $form->id,
                    'permission' => $access['permission'],
                    'organization_id' => $access['organizationId'],
                ])->id,
                'phase_type_id' => $input->phase->phaseTypeId,
                'needs_review' => $input->phase->needsReview,
                'order' => ($last ?? 0) + 1,
                'submission_date_id' => $deadline->id,
            ]);

            $this->createEvaluationForms($detail, $input->phase->needsReview ? $input->evaluationForms : []);
            $detailIds[] = $detail->id;
        }

        return $detailIds;
    }

    /** @param  list<EvaluationFormInput>  $evaluationForms */
    private function createEvaluationForms(FormPhaseDetail $detail, array $evaluationForms): void
    {
        foreach ($evaluationForms as $index => $evaluation) {
            $created = ReviewEvaluationForm::create([
                'title' => $evaluation->title,
                'description' => $evaluation->description,
                'form_phase_detail_id' => $detail->id,
                'is_required' => $evaluation->isRequired,
                'is_active' => true,
                'order' => $index + 1,
            ]);

            foreach ($evaluation->fields as $fieldIndex => $field) {
                $reviewField = $created->reviewFormFields()->create([
                    'field_type_id' => $field->fieldTypeId,
                    'label' => $field->label,
                    'description' => $field->description,
                    'is_required' => $field->isRequired,
                    'order' => $fieldIndex + 1,
                ]);

                foreach ($field->options as $optionIndex => $option) {
                    $reviewField->reviewFormFieldOptions()->create([...$option, 'order' => $optionIndex + 1]);
                }
            }
        }
    }
}
