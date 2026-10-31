<?php

namespace App\Http\Controllers\Submission;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Queries\Submission\ResearcherStartOptions;
use App\Queries\Submission\SubmissionWizard;
use App\Support\WizardStepGate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Wizard pengajuan tujuh langkah (ADR 0012, RESEARCHER-02-02..02-10). */
class SubmissionWizardController extends Controller
{
    use ResolvesActor;

    private const PAGE = 'Submission/WizardPage';

    /** Langkah 1 sebelum baris pengajuan lahir; barisnya dibuat `user.submissions.store`. */
    public function create(Request $request): Response
    {
        return Inertia::render(self::PAGE, [
            'step' => WizardStepGate::STEP_FORM_PERIOD,
            'steps' => SubmissionWizard::stepsBeforeStart(),
            'submission' => null,
            'startOptions' => ResearcherStartOptions::run($this->actor($request)),
            'fields' => [],
            'targetOutputs' => [],
            'targetOutputTypes' => [],
            'review' => null,
            'can' => null,
        ]);
    }

    public function edit(Request $request, FormSubmission $submission, ?int $step = null): Response
    {
        $viewer = $this->actor($request);

        // BR-SM-05, BR-SM-10 — wizard hanya untuk yang boleh menyunting: pengajuan yang sudah
        // dikirim, terarsip, atau milik orang lain tidak dibuka sebagai form yang bisa diisi.
        abort_unless($viewer->can('update', $submission), 403, 'Pengajuan ini tidak dapat disunting.');

        $step ??= WizardStepGate::resumeStep($submission);
        abort_unless(isset(WizardStepGate::LABELS[$step]), 404);

        return Inertia::render(self::PAGE, [
            'startOptions' => [],
            ...SubmissionWizard::run($viewer, $submission, $step),
        ]);
    }
}
