<?php

namespace App\Http\Controllers\Submission;

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResearchOutput\ResearchOutputRequest;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\ResearchOutput\OutputUploadDetail;
use App\Support\PhaseAccessGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * BR-RO-01 s/d BR-RO-09 — luaran terealisasi.
 *
 * Tiga sumbu harus lolos bersamaan (ADR 0014): siapa lewat Policy, kapan lewat
 * FormPhaseDetail, keadaan lewat status induk — lapis beda, celah tak mencolok.
 */
class ResearchOutputController extends Controller
{
    public function store(ResearchOutputRequest $request, FormSubmission $submission): RedirectResponse
    {
        $user = $this->authorizeManage($submission);

        SaveResearchOutput::run($submission, $user, $request->toInput());

        return back()->with('success', 'Luaran tersimpan.');
    }

    public function update(
        ResearchOutputRequest $request,
        FormSubmission $submission,
        ResearchOutput $output
    ): RedirectResponse {
        $user = $this->authorizeManage($submission);

        abort_unless($output->form_submission_id === $submission->id, 404);

        SaveResearchOutput::run($submission, $user, $request->toInput(), $output);

        return back()->with('success', 'Luaran diperbarui.');
    }

    private function authorizeManage(FormSubmission $submission): User
    {
        $user = Auth::user();

        // BR-SM-07 lebih dulu: pengajuan yang tak boleh dibaca juga tak boleh ditulisi.
        abort_unless($user !== null && $submission->isAccessibleBy($user), 403);
        abort_unless($user->can('manageResearchOutput', $submission), 403);

        // BR-RO-08 — sumbu **kapan**: Batas Akhir detail Upload Luaran (W139), bukan Batas Submit
        // pengajuan yang selalu lewat saat disetujui. Membaca tetap terbuka (BR-FE-15).
        PhaseAccessGate::assertOpenFor($submission, OutputUploadDetail::for($submission));

        return $user;
    }
}
