<?php

namespace App\Http\Controllers;

use App\Actions\Form\AssertFormAccessible;
use App\Actions\FormSubmission\StartSubmission;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Requests\Submission\StartSubmissionRequest;
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
}
