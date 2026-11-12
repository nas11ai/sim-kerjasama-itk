<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Actions\FormSubmission\ResolveWizardSubmission;
use App\Data\View\Submission\Wizard\StartFormOptionData;
use App\Data\View\Submission\Wizard\StartPeriodOptionData;
use App\Models\Form;
use App\Models\FormAccessControl;
use App\Models\FormPhase;
use App\Models\FormPhaseDetail;
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
        $accessible = fn (Relation $q) => $q
            ->whereIn('form_access_control_id', FormAccessControl::query()->accessibleBy($user)->select('id'))
            ->with('formAccessControl.form:id,title,is_active');

        $periods = SubmissionPeriod::query()
            ->with([
                'submissionDates',
                // Dua tahap pustaka ber-Form pengajuan sama di satu periode: tahap tertua yang
                // ditawarkan, tidak bergantung urutan baris tautan (R2).
                'submissionPeriodPhases' => fn (Relation $q) => $q->orderBy('form_phase_id'),
                // BR-FE-32 — langkah versi angkatan: pakuan periode itu, atau versi terbaru tahap.
                'submissionPeriodPhases.formPhase.latestVersion.formPhaseDetails' => $accessible,
                'phaseVersionPins.formPhaseVersion.formPhaseDetails' => $accessible,
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
                    closesAt: SubmissionPeriodGate::submitDeadline($period)?->datetime->toISOString(),
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
            $phase = $periodPhase->formPhase;

            if ($phase === null) {
                continue;
            }

            foreach ($this->cohortSteps($period, $phase) as $detail) {
                $option = $this->offer($detail, $phase, $period, $user);

                // Cek sudah-ditawarkan sesudah semua lewati, supaya detail lain Form ini tetap terpilih.
                if ($option !== null && !isset($forms[$option->formId])) {
                    $forms[$option->formId] = $option;
                }
            }
        }

        return array_values($forms);
    }

    /**
     * BR-FE-32 — langkah versi angkatan (pakuan, atau terbaru), dari relasi yang sudah dimuat.
     *
     * @return iterable<FormPhaseDetail>
     */
    private function cohortSteps(SubmissionPeriod $period, FormPhase $phase): iterable
    {
        $pin = $period->phaseVersionPins->firstWhere('form_phase_id', $phase->id);

        return ($pin->formPhaseVersion ?? $phase->latestVersion)->formPhaseDetails ?? [];
    }

    private function offer(FormPhaseDetail $detail, FormPhase $phase, SubmissionPeriod $period, User $user): ?StartFormOptionData
    {
        $form = $detail->formAccessControl?->form;

        // BR-MON-01, W139 — laporan monev dan Upload Luaran bukan pintu pengajuan baru.
        if ($form === null || !$detail->opensSubmission()) {
            return null;
        }

        $draftId = $this->draftId($form->id, $period, $user);

        // R1-W2 — `forms()` hanya melintasi tahap yang ditautkan periode ini; tanggalnya milik
        // periode (BR-FE-26), jadi tak ada lagi tenggat "milik periode lain" yang dilewati.
        if ($this->isClosedToNewSubmissions($form, $phase) && $draftId === null) {
            return null;
        }

        return new StartFormOptionData(
            formId: $form->id,
            formPhaseId: $detail->form_phase_id,
            title: $form->title,
            draftId: $draftId,
        );
    }

    // BR-FE-19 / BR-FE-20 / W77 — Form atau tahap nonaktif hanya ditawarkan bila peneliti punya draft di sana.
    private function isClosedToNewSubmissions(Form $form, FormPhase $phase): bool
    {
        return !$form->is_active || !$phase->is_active;
    }

    private function draftId(int $formId, SubmissionPeriod $period, User $user): ?int
    {
        $existing = ResolveWizardSubmission::make()->handle($formId, $period->id, $user);

        // BR-SM-05 — sama dengan StartSubmission: hanya baris yang masih boleh disunting yang dipakai ulang.
        return $existing !== null && $user->can('update', $existing) ? $existing->id : null;
    }
}
