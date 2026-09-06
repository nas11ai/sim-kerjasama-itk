<?php

namespace App\Http\Controllers\Review;

use App\Actions\Review\ResolveReviewSummary;
use App\Http\Controllers\Controller;
use App\Models\ReviewSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * BR-REV-06 — menutup satu ulir diskusi.
 *
 * Otorisasinya dipegang Action: hanya reviewer pemilik catatan (atau
 * penggantinya lewat BR-REV-15). Kalau researcher bisa menutup sendiri, ia
 * bisa memaksa pengajuannya disetujui.
 */
class ReviewSummaryResolutionController extends Controller
{
    public function __invoke(ReviewSummary $reviewSummary): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null, 403);

        ResolveReviewSummary::run($reviewSummary, $user);

        return back()->with('success', 'Catatan review ditandai selesai.');
    }
}
