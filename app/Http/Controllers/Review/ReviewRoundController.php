<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\FinishReviewRound;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use App\Queries\Review\ActiveReviewerAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * BR-REV-16 — reviewer menekan *Selesai menilai* / *Selesai memeriksa*. Tanpa input, jadi tanpa
 * FormRequest; seluruh aturannya di FinishReviewRound.
 */
class ReviewRoundController extends Controller
{
    use ResolvesActor;

    public function finish(Request $request, FormSubmission $submission): RedirectResponse
    {
        $actor = $this->actor($request);
        $reviewer = Reviewer::where('user_id', $actor->id)->first();
        $seat = $reviewer === null ? null : ActiveReviewerAssignment::run($submission, $reviewer->id);

        // BR-SM-07, BR-REV-10 — hanya penugasan yang masih berlaku.
        abort_if($seat === null, 403, 'Anda tidak ditugaskan sebagai reviewer pengajuan ini.');

        $round = FinishReviewRound::run($seat, $actor);

        return back()->with('success', "{$round->kind->finishLabel()} tercatat.");
    }
}
