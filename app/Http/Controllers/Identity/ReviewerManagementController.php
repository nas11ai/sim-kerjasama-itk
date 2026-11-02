<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\AppointReviewer;
use App\Actions\Identity\ReviseReviewerAppointment;
use App\Enums\ReviewerStanding;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\AppointReviewerRequest;
use App\Http\Requests\Identity\ReviseAppointmentRequest;
use App\Models\InvitationToken;
use App\Models\Reviewer;
use App\Models\User;
use App\Queries\Identity\AppointmentCandidates;
use App\Queries\Review\ReviewerDirectory;
use App\Support\Identity\ReviewerTypeCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** OPERATOR-08-04 — manajemen reviewer; route-nya dijaga `can:reviewers.assign` (W47). */
class ReviewerManagementController extends Controller
{
    use ResolvesActor;

    public function index(Request $request): Response
    {
        $viewer = $this->actor($request);
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'type' => $request->string('type')->value(),
            'standing' => $request->string('standing')->value(),
        ];

        return Inertia::render('Identity/ReviewerIndexPage', [
            'reviewers' => ReviewerDirectory::run($filters, $viewer->can('viewAny', User::class)),
            'filters' => $filters,
            'typeOptions' => ReviewerTypeCatalog::options(),
            'standingOptions' => ReviewerStanding::options(),
            'appointmentCandidates' => Inertia::optional(fn () => AppointmentCandidates::run(
                $request->string('candidate_search')->value()
            )),
            'can' => ['manageInvitations' => $viewer->can('manage', InvitationToken::class)],
        ]);
    }

    public function store(AppointReviewerRequest $request): RedirectResponse
    {
        $tenure = $request->tenure();

        // BR-IAM-11 — role, idempoten per orang, BR-IAM-05, notifikasi, audit.
        AppointReviewer::run(User::findOrFail($request->userId()), $request->reviewerType(), $tenure['start'], $tenure['end']);

        return back()->with('success', 'Reviewer ditunjuk.');
    }

    public function update(ReviseAppointmentRequest $request, Reviewer $reviewer): RedirectResponse
    {
        ReviseReviewerAppointment::run($this->actor($request), $reviewer, $request->reviewerType(), $request->tenure());

        return back()->with('success', 'Masa tugas reviewer diperbarui.');
    }
}
