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

        abort_unless($user !== null && $user->can('replaceReviewer', $submission), 403);

        // W26 — catatan opsional; tercatat di entri audit Action-nya bila diisi.
        $validated = $request->validate([
            'incoming_reviewer_id' => 'required|exists:reviewers,id',
            'note' => 'nullable|string|max:2000',
        ]);

        ReplaceReviewer::run(
            $submission,
            $reviewer,
            Reviewer::findOrFail($validated['incoming_reviewer_id']),
            $validated['note'] ?? null,
        );

        return back()->with('success', 'Penilai berhasil diganti.');
    }
}
