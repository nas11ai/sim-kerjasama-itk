<?php

namespace App\Http\Controllers\Submission;

use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\User;
use App\Queries\Review\ReviewerCandidates;
use App\Queries\Submission\OperatorSubmissionDetail;
use App\Queries\Submission\OperatorSubmissionList;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Layar pengajuan sisi Operator (OPERATOR-02-01, 02-02).
 */
class OperatorSubmissionController extends Controller
{
    // BR-SM-07 — view-all melihat seluruh pengajuan terkirim; draft belum ada bagi Operator.
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->value(),
            'status' => $request->string('status')->value(),
            'period' => $request->string('period')->value(),
            'scheme' => $request->string('scheme')->value(),
        ];

        return Inertia::render('Submission/ManageIndexPage', [
            'filters' => $filters,
            ...OperatorSubmissionList::run($filters),
        ]);
    }

    // BR-SM-07 — view-all; draft belum diajukan, bagi Operator ia belum ada.
    public function show(Request $request, FormSubmission $submission): Response
    {
        abort_unless($submission->is_submitted, 404, 'Pengajuan tidak ditemukan.');
        $viewer = $request->user();
        abort_unless($viewer instanceof User, 403);

        return Inertia::render('Submission/ManageShowPage', [
            ...OperatorSubmissionDetail::run($viewer, $submission),
            // Hanya lewat partial reload dialog penugasan, jadi pencarian tetap di server.
            'reviewerCandidates' => Inertia::optional(fn () => ReviewerCandidates::run(
                $submission,
                $request->string('reviewer_search')->trim()->toString(),
            )),
        ]);
    }
}
