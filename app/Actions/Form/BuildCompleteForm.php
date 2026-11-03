<?php

namespace App\Actions\Form;

use App\Data\Request\FormBuilderFieldInput;
use App\Data\Request\FormBuilderInput;
use App\Data\Request\FormBuilderPhaseInput;
use App\Data\Result\BuiltForm;
use App\Enums\DeadlineType;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Services\Form\EvaluationFormWriter;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SchemeFieldGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Wizard enam langkah dalam satu transaksi. Periode diselesaikan lebih dulu: BR-FE-07 mewajibkan
 * setiap detail lahir dengan tenggat. K8g — periodenya sudah ada; detail mendapat Batas Submit
 * siklus pengajuan utamanya, dan Batas Evaluasi bila tahapnya direview (BR-FE-26).
 *
 * @method static BuiltForm run(FormBuilderInput $input, User $actor)
 */
class BuildCompleteForm
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormBuilderInput $input, User $actor): BuiltForm
    {
        // BR-SCH-05 — penjaga yang sama dengan pembuatan form di layar Form.
        SchemeFieldGuard::assertTrlHasSchemeSelector(array_map(
            fn (FormBuilderFieldInput $field): int => $field->fieldTypeId,
            $input->fields,
        ));

        return DB::transaction(function () use ($input, $actor): BuiltForm {
            $period = $this->usablePeriod($input->periodId);
            $deadline = $this->mainDate($period, DeadlineType::Submit, 'Periode wajib punya Batas Submit.');
            $evaluation = $input->phase->needsReview
                ? $this->mainDate($period, DeadlineType::Evaluation, 'Tahap yang direview butuh Batas Evaluasi di periode ini.')
                : null;
            $form = $this->createForm($input);
            $phase = $this->resolvePhase($input->phase);

            // Tahap yang sudah memakai periode ini tidak ditautkan dua kali.
            $period->submissionPeriodPhases()->firstOrCreate(['form_phase_id' => $phase->id]);

            // BR-FE-17 — tenggatnya milik periode yang baru saja memakai tahap ini.
            $detailIds = $this->createDetails($form, $phase, $input, [
                'submission_date_id' => $deadline->id,
                'evaluation_date_id' => $evaluation?->id,
            ]);

            // BR-RPT-09 — di dalam transaksi: pembuatan yang gagal tidak meninggalkan jejak.
            AuditTrail::record('Form dibangun lewat wizard', $form, $actor, new AuditChange(new: [
                'title' => $form->title,
                'form_phase_id' => $phase->id,
                'phase_created' => $input->phase->existingPhaseId === null,
                'submission_period_id' => $period->id,
                'detail_ids' => $detailIds,
            ]));

            return new BuiltForm($form, $deadline);
        });
    }

    /**
     * BR-SCH-07 — periode tutup paksa atau yang tanggalnya sudah lewat semua tidak lagi
     * menerima tahap baru (ADR 0014); layar Form membatasinya sama.
     *
     * @throws ValidationException
     */
    private function usablePeriod(int $id): SubmissionPeriod
    {
        $period = SubmissionPeriod::query()->usable()->find($id);

        if ($period === null) {
            throw ValidationException::withMessages([
                'submission_period.existing_period_id' => 'Periode sudah ditutup atau tanggalnya sudah lewat.',
            ]);
        }

        return $period;
    }

    /**
     * BR-FE-26 — tenggat jenis ini pada siklus pengajuan utama periode (BR-FE-25: paling banyak satu).
     *
     * @throws ValidationException
     */
    private function mainDate(SubmissionPeriod $period, DeadlineType $type, string $missing): SubmissionDate
    {
        $date = $period->submissionDates()->ofType($type)->first();

        if ($date === null) {
            throw ValidationException::withMessages(['submission_period.existing_period_id' => $missing]);
        }

        return $date;
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

    /**
     * @param  array{submission_date_id: int, evaluation_date_id: int|null}  $deadlines
     * @return list<int>
     */
    private function createDetails(Form $form, FormPhase $phase, FormBuilderInput $input, array $deadlines): array
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
                ...$deadlines,
            ]);

            EvaluationFormWriter::write($detail, $input->phase->needsReview ? $input->evaluationForms : []);
            $detailIds[] = $detail->id;
        }

        return $detailIds;
    }
}
