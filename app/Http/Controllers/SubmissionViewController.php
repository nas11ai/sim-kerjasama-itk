<?php

namespace App\Http\Controllers;

use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Submission\ResearcherSubmissionDetail;
use App\Queries\Submission\ResearcherSubmissionList;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SubmissionViewController extends Controller
{
    public function userIndex(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->value(),
            'status' => $request->string('status')->value(),
            'period' => $request->string('period')->value(),
        ];

        return Inertia::render('Submission/IndexPage', [
            'filters' => $filters,
            'canCreate' => $this->viewer($request)->can('submissions.create'),
            ...ResearcherSubmissionList::run($this->viewer($request), $filters),
        ]);
    }

    public function userShowSubmission(Request $request, FormSubmission $submission): Response|RedirectResponse
    {
        $user = $this->viewer($request);

        // BR-SM-07 — akses level pengajuan adalah lapis terpisah dari FormAccessControl. Cek
        // buatan sendiri di sini dulu melewatkan anggota penelitian dan pemegang submissions.view-all.
        abort_unless($submission->isAccessibleBy($user), 403, 'Akses tidak sah terhadap pengajuan.');

        // W130 — laporan monev punya halamannya sendiri.
        if ($submission->parent_submission_id !== null) {
            return redirect()->route('monev.reports.show', $submission);
        }

        return Inertia::render('Submission/ShowPage', ResearcherSubmissionDetail::run($user, $submission));
    }

    private function viewer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
