<?php

namespace App\Http\Controllers\ResearchOutput;

use App\Actions\ResearchOutput\SaveResearchOutput;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\ResearchOutput\ResearchOutputRequest;
use App\Models\FormSubmission;
use App\Models\ResearchOutput;
use App\Models\User;
use App\Queries\ResearchOutput\OutputUploadDetail;
use App\Queries\ResearchOutput\ResearcherOutputList;
use App\Queries\ResearchOutput\ResearchOutputForm;
use App\Support\PhaseAccessGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar luaran penelitian (ux/ia/06_research_output, W145). Tiga sumbu harus lolos bersamaan
 * (ADR 0014): siapa lewat Policy, kapan lewat detail Upload Luaran, keadaan lewat status induk.
 * Layar membaca ketiganya dari satu definisi, `OutputWriteAccess` (W146).
 */
class ResearchOutputController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        return Inertia::render('ResearchOutput/IndexPage', [
            'groups' => ResearcherOutputList::run($this->actor($request)),
        ]);
    }

    public function create(Request $request): Response
    {
        $id = $request->query('submission');
        abort_unless(is_string($id) && ctype_digit($id), 404);

        return Inertia::render('ResearchOutput/FormPage',
            ResearchOutputForm::create($this->actor($request), FormSubmission::findOrFail($id)));
    }

    public function store(ResearchOutputRequest $request, FormSubmission $submission): RedirectResponse
    {
        $user = $this->authorizeWrite($request, $submission);

        $output = SaveResearchOutput::run($submission, $user, $request->toInput());

        return redirect()->route('research-outputs.show', $output)->with('success', 'Luaran tersimpan.');
    }

    public function edit(Request $request, ResearchOutput $output): Response
    {
        return Inertia::render('ResearchOutput/FormPage', ResearchOutputForm::edit($this->actor($request), $output));
    }

    public function update(ResearchOutputRequest $request, ResearchOutput $output): RedirectResponse
    {
        $submission = $output->formSubmission;
        $user = $this->authorizeWrite($request, $submission);

        SaveResearchOutput::run($submission, $user, $request->toInput(), $output);

        return redirect()->route('research-outputs.show', $output)->with('success', 'Luaran diperbarui.');
    }

    private function authorizeWrite(Request $request, FormSubmission $submission): User
    {
        $user = $this->actor($request);

        // BR-SM-07 lebih dulu: pengajuan yang tak boleh dibaca juga tak boleh ditulisi.
        abort_unless($submission->isAccessibleBy($user), 403);
        abort_unless($user->can('manageResearchOutput', $submission), 403);

        // BR-RO-08 — Batas Akhir detail Upload Luaran (W139). Keadaan induk: Guard di Action.
        PhaseAccessGate::assertOpenFor($submission, OutputUploadDetail::for($submission));

        return $user;
    }
}
