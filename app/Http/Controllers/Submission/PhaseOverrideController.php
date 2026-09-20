<?php

namespace App\Http\Controllers\Submission;

use App\Actions\Override\GrantPhaseOverride;
use App\Actions\Override\RevokePhaseOverride;
use App\Http\Controllers\Controller;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Models\FormSubmissionOverride;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BR-FE-08, BR-FE-09, BR-FE-10 — perpanjangan akses satu fase untuk satu
 * pengajuan. Controller hanya menerjemahkan: FormRequest → Action → redirect.
 * Aturannya di Action karena override juga bisa datang dari jalur lain.
 */
class PhaseOverrideController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $operator = Auth::user();

        abort_unless($operator !== null && $operator->can('grantOverride', $submission), 403);

        $validated = $request->validate([
            'form_phase_detail_id' => 'required|exists:form_phase_details,id',
            // BR-FE-09 — alasan wajib, dan tidak boleh berupa spasi belaka.
            'reason' => 'required|string|min:3|max:2000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        GrantPhaseOverride::run(
            $submission,
            FormPhaseDetail::findOrFail($validated['form_phase_detail_id']),
            $operator,
            $validated['reason'],
            $validated['expires_at'] ?? null,
        );

        return back()->with('success', 'Perpanjangan akses diberikan.');
    }

    public function destroy(
        FormSubmission $submission,
        FormSubmissionOverride $override
    ): RedirectResponse {
        $operator = Auth::user();

        abort_unless($operator !== null && $operator->can('grantOverride', $submission), 403);

        // Override milik pengajuan lain tidak boleh dicabut lewat rute ini —
        // BR-FE-10 berlaku dua arah: ia tidak melebar, dan tidak bisa disentuh
        // dari luar pasangannya.
        abort_unless($override->form_submission_id === $submission->id, 404);

        RevokePhaseOverride::run($override);

        return back()->with('success', 'Perpanjangan akses dicabut.');
    }
}
