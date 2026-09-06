<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ReplaceReviewer;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** BR-REV-10 — mengganti reviewer; record lama ditandai replaced. */
class ReplaceReviewerController extends Controller
{
    public function __invoke(Request $request, FormSubmission $submission, Reviewer $reviewer): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('submissions.view-all'), 403);

        $validated = $request->validate([
            'incoming_reviewer_id' => 'required|exists:reviewers,id',
        ]);

        ReplaceReviewer::run($submission, $reviewer, Reviewer::findOrFail($validated['incoming_reviewer_id']));

        return back()->with('success', 'Reviewer berhasil diganti.');
    }
}
