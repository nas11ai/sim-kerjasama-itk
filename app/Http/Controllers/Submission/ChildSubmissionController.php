<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\StartChildSubmission;
use App\Http\Controllers\Controller;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * BR-FE-04 — membuat laporan turunan (monev, kelengkapan berkas) dari sebuah
 * pengajuan yang sudah disetujui.
 */
class ChildSubmissionController extends Controller
{
    public function store(Request $request, FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        // BR-SM-07 — laporan turunan menyangkut isi pengajuan, jadi yang boleh
        // membuatnya adalah pihak yang memang punya akses ke pengajuan itu.
        abort_unless($user !== null && $submission->isAccessibleBy($user), 403);

        $validated = $request->validate([
            'form_phase_detail_id' => 'required|exists:form_phase_details,id',
        ]);

        $child = StartChildSubmission::run(
            $submission,
            FormPhaseDetail::findOrFail($validated['form_phase_detail_id']),
            $user,
        );

        return redirect()
            ->route('user.submissions.show', $child)
            ->with('success', 'Laporan berhasil dibuat.');
    }
}
