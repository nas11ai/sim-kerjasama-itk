<?php

namespace App\Http\Controllers;

use App\Actions\Form\AssertFormAccessible;
use App\Actions\FormSubmission\SaveSubmissionDraft;
use App\Actions\FormSubmission\StartSubmission;
use App\Actions\FormSubmission\SubmitFormSubmission;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Submission\StartSubmissionRequest;
use App\Http\Requests\Submission\SubmissionAnswersRequest;
use App\Queries\Submission\AccessibleFormPhaseDetail;
use App\Support\WizardStepGate;
use Illuminate\Http\RedirectResponse;

class UserFormController extends Controller
{
    use ResolvesActor;

    public function startSubmission(StartSubmissionRequest $request): RedirectResponse
    {
        $user = $this->actor($request);

        AssertFormAccessible::run($user, $request->form(), 'Anda tidak memiliki akses ke formulir ini.');

        // Gagal keras kalau tidak ketemu, bukan menulis NULL ke form_phase_detail_id.
        $detail = AccessibleFormPhaseDetail::run($request->integer('form_phase_id'), $request->integer('form_id'), $request->integer('submission_period_id'), $user)
            ?? abort(422, 'Kombinasi form dan fase formulir tidak ditemukan.');

        $submission = StartSubmission::run($request->integer('form_id'), $request->integer('submission_period_id'), $detail, $user);

        return redirect()->route('user.submissions.wizard', [$submission, WizardStepGate::STEP_BASIC_INFO]);
    }

    public function saveDraft(SubmissionAnswersRequest $request): RedirectResponse
    {
        SaveSubmissionDraft::run($request->toInput(), $this->actor($request));

        return redirect()->back()->with('success', 'Draft berhasil disimpan.');
    }

    public function submitForm(SubmissionAnswersRequest $request): RedirectResponse
    {
        $user = $this->actor($request);
        $submission = SaveSubmissionDraft::run($request->toInput(), $user);

        // BR-SM-01, BR-SM-02, BR-SM-04, BR-SM-16 — seluruh keputusan submit ada di Action. Cabang
        // needs_review yang dulu di sini dicabut: ia memanggil transitionTo(Approved) dari DRAFT, transisi
        // yang tidak diizinkan state machine, sehingga selalu melempar TransitionNotFound (ADR 0016).
        SubmitFormSubmission::run($submission, $user);

        return redirect()->back()->with('success', 'Formulir berhasil diserahkan.');
    }
}
