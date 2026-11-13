<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ResolveReviewSummary;
use App\Http\Controllers\Controller;
use App\Models\ReviewSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * BR-REV-06 — menutup ulir diskusi. Otorisasi di Action: hanya reviewer
 * pemilik catatan (atau penggantinya, BR-REV-15) — kalau researcher bisa
 * menutup sendiri, ia bisa memaksa pengajuannya disetujui.
 */
class ReviewSummaryResolutionController extends Controller
{
    public function __invoke(ReviewSummary $reviewSummary): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        ResolveReviewSummary::run($reviewSummary, $user);

        return back()->with('success', 'Catatan penilaian ditandai selesai.');
    }
}
