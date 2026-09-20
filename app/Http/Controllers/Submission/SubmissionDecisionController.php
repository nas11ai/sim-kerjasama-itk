<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\TransferSubmissionOwnership;
use App\Actions\Review\RejectSubmission;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Keputusan Operator atas pengajuan: menolak dan memindahkan kepemilikan.
 * Penolakan sengaja bukan cabang endpoint status generik — bersyarat sendiri
 * (hanya UNDER_REVIEW, wajib beralasan), dan menumpang akan mengaburkan keduanya.
 */
class SubmissionDecisionController extends Controller
{
    public function reject(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('reject', $submission), 403);

        $validated = $request->validate(['reason' => 'required|string|min:1']);

        RejectSubmission::run($submission, $user, $validated['reason']);

        return back()->with('success', 'Pengajuan ditolak.');
    }

    public function transferOwnership(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        // BR-IAM-10 — pemindahan kepemilikan tindakan Operator, bukan pemilik.
        abort_unless($user !== null && $user->can('submissions.view-all'), 403);

        $validated = $request->validate(['successor_id' => 'required|exists:users,id']);

        TransferSubmissionOwnership::run($submission, User::findOrFail($validated['successor_id']));

        return back()->with('success', 'Kepemilikan pengajuan dipindahkan.');
    }
}
