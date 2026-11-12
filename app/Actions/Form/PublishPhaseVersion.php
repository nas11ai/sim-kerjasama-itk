<?php

namespace App\Actions\Form;

use App\Data\Request\FormPhaseDetailInput;
use App\Data\Request\PhaseStructureInput;
use App\Data\Request\StepEvaluationFormInput;
use App\Enums\DeadlineType;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormPhaseVersion;
use App\Models\PhaseVersionPin;
use App\Models\ReviewEvaluationForm;
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
        $this->assertEvaluationForms($structure->details);

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

            AuditTrail::record('Struktur tahap diterbitkan', $phase, $actor, new AuditChange(new: [
                'version' => $version->number,
                'steps' => count($structure->details),
            ]));

            return $version;
        });
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
            'phase_details' => 'Form pengajuan tahap ini sudah dipakai angkatan berjalan, jadi tidak bisa diganti. Buat tahap baru untuk Form lain.',
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

    /**
     * BR-FE-30 — pemasangan hanya Form Evaluasi terbit, sekali per langkah; langkah berform evaluasi
     * aktif tetap direview.
     *
     * @param  list<FormPhaseDetailInput>  $details
     *
     * @throws ValidationException
     */
    private function assertEvaluationForms(array $details): void
    {
        $requested = array_merge(...array_map(
            fn (FormPhaseDetailInput $d): array => array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $d->evaluationForms),
            $details,
        ));
        $published = array_values(ReviewEvaluationForm::query()->whereIn('id', $requested)->whereHas('versions')->get(['id'])
            ->map(fn (ReviewEvaluationForm $form): int => $form->id)
            ->all());

        foreach ($details as $index => $detail) {
            $this->assertStepEvaluationForms($index, $detail, $published);
        }
    }

    /**
     * @param  list<int>  $published
     *
     * @throws ValidationException
     */
    private function assertStepEvaluationForms(int $index, FormPhaseDetailInput $detail, array $published): void
    {
        $ids = array_map(fn (StepEvaluationFormInput $a): int => $a->reviewEvaluationFormId, $detail->evaluationForms);
        $active = count(array_filter($detail->evaluationForms, fn (StepEvaluationFormInput $a): bool => $a->isActive));

        if (!$detail->needsReview && $active > 0) {
            throw ValidationException::withMessages([
                "phase_details.{$index}.needs_review" => "Detail ini punya {$active} form evaluasi aktif, jadi tetap harus direview.",
            ]);
        }

        if (array_diff($ids, $published) !== []) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini belum pernah diterbitkan, jadi belum bisa dipasang.',
            ]);
        }

        if (count($ids) !== count(array_unique($ids))) {
            throw ValidationException::withMessages([
                'review_evaluation_form_id' => 'Form evaluasi ini sudah terpasang di langkah ini.',
            ]);
        }
    }
}
