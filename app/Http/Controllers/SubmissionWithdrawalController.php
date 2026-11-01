<?php

namespace App\Http\Controllers;

use App\Actions\FormSubmission\WithdrawSubmission;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Penarikan pengajuan (BR-SM-11) — wireframe OPERATOR-02-06. Controller
 * tersendiri: hanya Operator, hanya dari APPROVED, tidak dapat dibatalkan —
 * menumpang di endpoint status generik akan mengaburkan ketiganya.
 */
class SubmissionWithdrawalController extends Controller
{
    public function __invoke(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('withdraw', $submission), 403);

        // W26 — alasan wajib; tercatat di entri audit Action-nya.
        $validated = $request->validate(['reason' => 'required|string|min:3|max:2000']);

        WithdrawSubmission::run($submission, $user, $validated['reason']);

        return back()->with('success', 'Pengajuan berhasil ditarik.');
    }
}
