<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\SaveWizardStep;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Submission\WizardStepRequest;
use App\Models\FormSubmission;
use App\Queries\Submission\ResearcherStartOptions;
use App\Queries\Submission\ResearchMemberCandidates;
use App\Queries\Submission\SubmissionWizard;
use App\Support\AccountStateGuard;
use App\Support\WizardStepGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/** Wizard pengajuan tujuh langkah (ADR 0012, RESEARCHER-02-02..02-10). */
class SubmissionWizardController extends Controller
{
    use ResolvesActor;

    private const PAGE = 'Submission/WizardPage';

    /** Langkah 1 sebelum baris pengajuan lahir; barisnya dibuat `user.submissions.store`. */
    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->actor($request);

        // BR-IAM-03 — pintu UX; penegaknya tetap StartSubmission (IAM-VERIFY-04).
        if (!AccountStateGuard::isProfileComplete($user)) {
            return redirect()->route('profile.complete', ['next' => 'submission']);
        }

        return Inertia::render(self::PAGE, [
            'step' => WizardStepGate::STEP_FORM_PERIOD,
            'steps' => SubmissionWizard::stepsBeforeStart(),
            'submission' => null,
            'startOptions' => ResearcherStartOptions::run($user),
            'fields' => [],
            'targetOutputs' => [],
            'targetOutputTypes' => [],
            'review' => null,
            'members' => null,
            'budget' => null,
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
            // Hanya dimuat lewat partial reload pencarian anggota langkah 4.
            'memberCandidates' => Inertia::optional(fn () => ResearchMemberCandidates::run(
                $submission,
                $request->string('member_search')->value(),
            )),
        ]);
    }

    /** Setiap perpindahan langkah — Lanjut, Sebelumnya, atau Stepper — lewat sini. */
    public function save(WizardStepRequest $request, FormSubmission $submission, int $step): RedirectResponse
    {
        $input = $request->toStepInput($step);

        SaveWizardStep::run($submission, $input, $this->actor($request));

        return redirect()->route('user.submissions.wizard', [$submission, $input->goto]);
    }

    public function submit(Request $request, FormSubmission $submission): RedirectResponse
    {
        try {
            SubmitFormSubmission::run($submission, $this->actor($request));
        } catch (ValidationException $e) {
            // [SM-WIZARD-08] — penolakan dibaca di langkah 7, tempat daftar kelengkapannya.
            return redirect()->route('user.submissions.wizard', [$submission, WizardStepGate::STEP_REVIEW])
                ->withErrors($e->errors());
        }

        return redirect()->route('user.submissions.show', $submission)->with('success', 'Pengajuan berhasil dikirim.');
    }
}
