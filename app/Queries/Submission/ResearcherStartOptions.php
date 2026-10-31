<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\ResolveWizardSubmission;
use App\Data\View\Submission\Wizard\StartFormOptionData;
use App\Data\View\Submission\Wizard\StartPeriodOptionData;
use App\Models\FormAccessControl;
use App\Models\SubmissionPeriod;
use App\Models\User;
use App\Support\SubmissionPeriodGate;
use Illuminate\Database\Eloquent\Relations\Relation;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Langkah 1 wizard (RESEARCHER-02-02): periode yang masih menerima pengajuan (BR-FE-01) dan
 * form yang terbuka bagi peneliti lewat fase periode itu. Draft yang masih bisa disunting
 * ditautkan, karena StartSubmission akan memakainya ulang alih-alih membuat baru.
 *
 * @method static list<StartPeriodOptionData> run(User $user)
 */
class ResearcherStartOptions implements ChangesNoState
{
    use AsAction;

    /** @return list<StartPeriodOptionData> */
    public function handle(User $user): array
    {
        $periods = SubmissionPeriod::query()
            ->with([
                'submissionDates',
                'submissionPeriodPhases.formPhase.formPhaseDetails' => fn (Relation $q) => $q
                    ->whereIn('form_access_control_id', FormAccessControl::query()->accessibleBy($user)->select('id'))
                    ->orderBy('order')
                    ->with('formAccessControl.form:id,title'),
            ])
            ->orderBy('id')
            ->get()
            ->filter(fn (SubmissionPeriod $period): bool => SubmissionPeriodGate::isOpen($period));

        $options = [];

        foreach ($periods as $period) {
            $forms = $this->forms($period, $user);

            if ($forms !== []) {
                $options[] = new StartPeriodOptionData(
                    id: $period->id,
                    name: $period->name,
                    closesAt: $period->submissionDates->sortBy('datetime')->last()?->datetime?->toISOString(),
                    forms: $forms,
                );
            }
        }

        return $options;
    }

    /**
     * Satu form bisa terbuka lewat dua kontrol akses (ADR 0013); ditawarkan sekali.
     *
     * @return list<StartFormOptionData>
     */
    private function forms(SubmissionPeriod $period, User $user): array
    {
        $forms = [];

        foreach ($period->submissionPeriodPhases as $periodPhase) {
            foreach ($periodPhase->formPhase->formPhaseDetails ?? [] as $detail) {
                $form = $detail->formAccessControl?->form;

                if ($form !== null && !isset($forms[$form->id])) {
                    $forms[$form->id] = new StartFormOptionData(
                        formId: $form->id,
                        formPhaseId: $detail->form_phase_id,
                        title: $form->title,
                        draftId: $this->draftId($form->id, $period, $user),
                    );
                }
            }
        }

        return array_values($forms);
    }

    private function draftId(int $formId, SubmissionPeriod $period, User $user): ?int
    {
        $existing = ResolveWizardSubmission::make()->handle($formId, $period->id, $user);

        // BR-SM-05 — sama dengan StartSubmission: hanya baris yang masih boleh disunting yang dipakai ulang.
        return $existing !== null && $user->can('update', $existing) ? $existing->id : null;
    }
}
