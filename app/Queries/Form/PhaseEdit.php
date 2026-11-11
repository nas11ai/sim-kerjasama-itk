<?php

namespace App\Queries\Form;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\FormEngine\PhaseDetailEditData;
use App\Data\View\FormEngine\PhaseEditData;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\ReviewerFormAssignment;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Isi editor tahap ADMIN-08-09: semua detail tahap — Form pengajuan dan Form langkah monevnya
 * (BR-FE-28) — urut `order` lalu `id`, beserta hitungan pakainya lewat subquery, tanpa kueri per detail.
 *
 * @method static PhaseEditData run(?FormPhase $phase)
 */
class PhaseEdit implements ChangesNoState
{
    use AsAction;

    public function handle(?FormPhase $phase): PhaseEditData
    {
        // W94 — mode buat: detail Pengajuan kosong pertamanya dibuat layar dari pilihan akses pertama.
        if ($phase === null) {
            return new PhaseEditData(id: null, title: '', description: null, isActive: true, details: []);
        }

        $details = $this->details($phase);
        $labels = $this->accessLabels($details);

        return new PhaseEditData(
            id: $phase->id,
            title: $phase->title,
            description: $phase->description,
            isActive: $phase->is_active,
            details: array_values($details->map(
                fn (FormPhaseDetail $detail): PhaseDetailEditData => $this->toData($detail, $labels),
            )->all()),
        );
    }

    /** @return Collection<int, FormPhaseDetail> */
    private function details(FormPhase $phase): Collection
    {
        return $phase->formPhaseDetails()
            ->with([
                'formAccessControl.form:id,title',
                'phaseType:id,name',
            ])
            ->addSelect([
                'submission_count' => FormSubmission::query()->selectRaw('count(*)')
                    ->whereColumn('form_submissions.form_phase_detail_id', 'form_phase_details.id'),
                // BR-FE-22 — penugasan form evaluasi mana pun milik detail ini, aktif atau tidak.
                'assignment_count' => ReviewerFormAssignment::query()->selectRaw('count(*)')
                    ->join('review_evaluation_forms', 'review_evaluation_forms.id', '=', 'reviewer_form_assignments.review_evaluation_form_id')
                    ->whereColumn('review_evaluation_forms.form_phase_detail_id', 'form_phase_details.id'),
            ])
            ->withCount('activeReviewEvaluationForms as evaluation_form_count')
            ->orderBy('order')
            ->orderBy('id')
            ->get();
    }

    /**
     * "{permissionLabel} · {organizationLabel}" per id aturan akses, dari FormAccessRules.
     *
     * @param  Collection<int, FormPhaseDetail>  $details
     * @return array<int, string>
     */
    private function accessLabels(Collection $details): array
    {
        $formIds = array_values(array_unique(array_map(
            fn (FormPhaseDetail $detail): int => $detail->formAccessControl->form_id ?? 0,
            $details->all(),
        )));
        $labels = [];

        foreach (FormAccessRules::forForms($formIds) as $rules) {
            foreach ($rules as $rule) {
                $labels[$rule->id] = "{$rule->permissionLabel} · {$rule->organizationLabel}";
            }
        }

        return $labels;
    }

    /** @param  array<int, string>  $labels */
    private function toData(FormPhaseDetail $detail, array $labels): PhaseDetailEditData
    {
        return new PhaseDetailEditData(
            id: $detail->id,
            order: $detail->order,
            formAccessControlId: $detail->form_access_control_id,
            formTitle: $detail->formAccessControl->form->title ?? '',
            accessLabel: $labels[$detail->form_access_control_id] ?? '',
            phaseTypeId: $detail->phase_type_id,
            phaseTypeName: $detail->phaseType->name ?? '',
            needsReview: $detail->needs_review,
            deadlineType: $detail->deadline_type->value,
            deadlineCycle: $detail->deadline_cycle,
            deadlineLabel: $detail->deadline_type->stepLabel($detail->deadline_cycle),
            submissionCount: $detail->submission_count,
            assignmentCount: $detail->assignment_count,
            evaluationFormCount: $detail->evaluation_form_count,
        );
    }
}
