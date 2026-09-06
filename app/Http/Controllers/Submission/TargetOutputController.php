<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\SaveTargetOutput;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Models\SubmissionTargetOutput;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/** BR-SM-14, BR-SM-15 — target luaran yang dijanjikan pengajuan. */
class TargetOutputController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        // Otorisasinya milik Policy — satu sumber, supaya objek `can` yang
        // dikirim ke frontend jujur (ADR 0015).
        abort_unless($user !== null && $user->can('manageTargetOutput', $submission), 403);

        $validated = $request->validate(['output_type' => 'required|string']);

        SaveTargetOutput::run($submission, $user, $validated['output_type']);

        return back()->with('success', 'Target luaran tersimpan.');
    }

    public function update(Request $request, FormSubmission $submission, SubmissionTargetOutput $targetOutput): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('manageTargetOutput', $submission), 403);

        $validated = $request->validate(['output_type' => 'required|string']);

        SaveTargetOutput::run($submission, $user, $validated['output_type'], $targetOutput);

        return back()->with('success', 'Target luaran diperbarui.');
    }
}
