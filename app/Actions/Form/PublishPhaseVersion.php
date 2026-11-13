<?php

namespace App\Actions\Form;

use App\Actions\Period\AssertOnePhasePerSubmissionForm;
use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use App\Models\SubmissionPeriodPhase;
use App\Models\User;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-FE-32 — menerbitkan versi struktur tahap berikutnya dengan menyalin baris langkah dan
 * pemasangannya; versi lama tidak disentuh, jadi angkatan yang terpaku padanya tidak berubah.
 *
 * @method static FormPhaseVersion run(FormPhase $phase, PhaseStructureInput $structure, User $actor)
 */
class PublishPhaseVersion
{
    use AsAction;

    /**
     * @throws ValidationException
     */
    public function handle(FormPhase $phase, PhaseStructureInput $structure, User $actor): FormPhaseVersion
    {
        AssertPhaseSteps::run($structure->details);
        AssertStepEvaluationForms::run($structure->details);
        AssertStepFormsPublished::run($structure->details);

        return DB::transaction(function () use ($phase, $structure, $actor): FormPhaseVersion {
            // Kunci tahap: dua penerbitan bersamaan tidak berebut nomor versi yang sama.
            FormPhase::query()->whereKey($phase->id)->lockForUpdate()->firstOrFail();
            $this->assertSubmissionFormKept($phase, $structure->details);

            $version = $phase->versions()->create([
                'number' => ($phase->latestVersion()->first()->number ?? 0) + 1,
                'published_by' => $actor->id,
                'published_at' => now(),
            ]);
            $this->copySteps($phase, $version, $structure->details);
            $phase->unsetRelation('latestVersion');
            $this->assertOnePhasePerSubmissionForm($phase);

            AuditTrail::record('Struktur tahap diterbitkan', $phase, $actor, new AuditChange(new: [
                'version' => $version->number,
                'steps' => count($structure->details),
            ]));

            return $version;
        });
    }

    /**
     * BR-FE-29 — versi baru bisa membawa Form pengajuan tahap lain di periode tertaut. Dicek sesudah
     * langkah disalin, karena PhaseSubmissionForms membaca versi terbaru; penolakan menggulungnya.
     *
     * @throws ValidationException
     */
    private function assertOnePhasePerSubmissionForm(FormPhase $phase): void
    {
        $links = SubmissionPeriodPhase::query()
            ->whereIn('submission_period_id', SubmissionPeriodPhase::query()->where('form_phase_id', $phase->id)->select('submission_period_id'))
            ->get(['submission_period_id', 'form_phase_id'])
            ->groupBy('submission_period_id');

        foreach ($links as $periodLinks) {
            AssertOnePhasePerSubmissionForm::run(array_values($periodLinks->map(fn (SubmissionPeriodPhase $link): int => $link->form_phase_id)->all()), 'phase_details');
        }
    }

    /** @param  list<FormPhaseDetailInput>  $details */
    private function copySteps(FormPhase $phase, FormPhaseVersion $version, array $details): void
    {
        $orders = NormalisePhaseDetailOrder::run(array_map(
            fn (FormPhaseDetailInput $d): int => $d->formAccessControlId,
            $details,
        ));

        foreach ($details as $index => $detail) {
            $step = $version->formPhaseDetails()->create([
                'form_phase_id' => $phase->id,
                'form_access_control_id' => $detail->formAccessControlId,
                'phase_type_id' => $detail->phaseTypeId,
                'order' => $orders[$index],
                'needs_review' => $detail->needsReview,
                ...$detail->stepKind(),
            ]);

            foreach ($detail->evaluationForms as $position => $attached) {
                $step->stepEvaluationForms()->create([
                    'review_evaluation_form_id' => $attached->reviewEvaluationFormId,
                    'is_required' => $attached->isRequired,
                    'is_active' => $attached->isActive,
                    'order' => $position + 1,
                ]);
            }
        }
    }

    /**
     * G3-W3 — angkatan terpaku membaca Form pengajuan tahap ini (BR-FE-29, pustaka bertapis Form);
     * menggantinya antar versi membuat dua angkatan satu tahap melayani Form berbeda.
     *
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    private function assertSubmissionFormKept(FormPhase $phase, array $details): void
    {
        if (!PhaseVersionPin::query()->where('form_phase_id', $phase->id)->exists()) {
            return;
        }

        $current = $phase->currentDetails()
            ->where('deadline_type', DeadlineType::Submit->value)
            ->whereNull('deadline_cycle')
            ->get()
            ->map(fn (FormPhaseDetail $step): int => $step->form_access_control_id)
            ->all();
        $incoming = array_map(
            fn (FormPhaseDetailInput $d): int => $d->formAccessControlId,
            array_filter($details, fn (FormPhaseDetailInput $d): bool => $d->opensSubmission()),
        );

        if ($this->formsOf($current) === $this->formsOf($incoming)) {
            return;
        }

        throw ValidationException::withMessages([
            'phase_details' => 'Formulir pengajuan tahap ini sudah dipakai angkatan berjalan, jadi tidak bisa diganti. Buat tahap baru untuk Formulir lain.',
        ]);
    }

    /**
     * @param  array<int, int>  $accessControlIds
     * @return list<int> id Form, terurut
     */
    private function formsOf(array $accessControlIds): array
    {
        $forms = FormAccessControl::query()->whereIn('id', $accessControlIds)->get(['id', 'form_id'])
            ->map(fn (FormAccessControl $access): int => $access->form_id)
            ->unique()->sort()->all();

        return array_values($forms);
    }
}
