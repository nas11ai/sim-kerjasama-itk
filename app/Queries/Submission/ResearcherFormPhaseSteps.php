<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormFieldResponse;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\PhaseType;
use App\Models\SubmissionPeriod;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Langkah wizard satu fase: detail fase yang terbuka bagi peneliti, dengan pengajuan dicari seperti
 * ResolveWizardSubmission (form, periode, peneliti) supaya layar memuat baris yang akan disimpan; dulu per
 * form saja, jadi periode baru membuka pengajuan periode lalu. Fase di luar periode URL tak punya langkah.
 *
 * @phpstan-type SubmissionRow array{id: int, is_submitted: bool, can_proceed: bool, created_at: string|null, updated_at: string|null, responses: array<string, string|null>}
 * @phpstan-type StepRow array{id: int|null, form: Form|null, order: int, needs_review: bool, phase_type: PhaseType|null, user_submission: SubmissionRow|null}
 *
 * @method static list<StepRow> run(SubmissionPeriod $period, FormPhase $phase, User $user)
 */
class ResearcherFormPhaseSteps implements ChangesNoState
{
    use AsAction;

    /** @return list<StepRow> */
    public function handle(SubmissionPeriod $period, FormPhase $phase, User $user): array
    {
        if (!$phase->submissionPeriodPhases()->where('submission_period_id', $period->id)->exists()) {
            return [];
        }

        $details = $phase->formPhaseDetails()
            ->whereIn('form_access_control_id', FormAccessControl::query()->accessibleBy($user)->select('id'))
            ->with([
                'formAccessControl.form.formFields.fieldType',
                'formAccessControl.form.formFields.formFieldOptions',
                'phaseType',
            ])
            ->orderBy('order')
            ->get();

        // Urut naik lalu keyBy: yang tersisa per form adalah id terbesar, sama dengan resolver.
        $mine = FormSubmission::query()
            ->with('formFieldResponses')
            ->whereNull('parent_submission_id')
            ->where('submitted_by', $user->id)
            ->where('submission_period_id', $period->id)
            ->orderBy('id')
            ->get()
            ->keyBy('form_id');

        return array_values($details->map(fn (FormPhaseDetail $detail): array => $this->step($detail, $mine))->all());
    }

    /**
     * @param  Collection<int, FormSubmission>  $mine
     * @return StepRow
     */
    private function step(FormPhaseDetail $detail, Collection $mine): array
    {
        $accessControl = $detail->formAccessControl;
        $submission = $accessControl === null ? null : $mine->get($accessControl->form_id);

        return [
            'id' => $accessControl?->id,
            'form' => $accessControl?->form,
            'order' => $detail->order,
            'needs_review' => $detail->needs_review,
            'phase_type' => $detail->phaseType,
            'user_submission' => $submission === null ? null : $this->submission($submission),
        ];
    }

    /** @return SubmissionRow */
    private function submission(FormSubmission $submission): array
    {
        return [
            'id' => $submission->id,
            'is_submitted' => $submission->is_submitted,
            'can_proceed' => $submission->canProceed(),
            // BR-FE-14 — `updated_at` inilah yang dikirim balik sebagai cap waktu optimistic lock.
            'created_at' => $submission->created_at?->toISOString(),
            'updated_at' => $submission->updated_at?->toISOString(),
            'responses' => $submission->formFieldResponses
                ->mapWithKeys(fn (FormFieldResponse $response): array => ["field_{$response->form_field_id}" => $response->value])
                ->all(),
        ];
    }
}
