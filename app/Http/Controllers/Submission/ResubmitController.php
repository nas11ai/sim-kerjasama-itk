<?php

namespace App\Http\Controllers\Submission;

use App\Actions\FormSubmission\ResubmitFormSubmission;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * BR-SM-03 — mengirim ulang pengajuan dari NEEDS_REVISION.
 *
 * Tanpa endpoint ini alur revisi berhenti total: pengajuan yang diminta
 * direvisi tidak punya cara kembali ke antrean review.
 */
class ResubmitController extends Controller
{
    public function __invoke(FormSubmission $submission): RedirectResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('resubmit', $submission), 403);

        ResubmitFormSubmission::run($submission, $user);

        return back()->with('success', 'Pengajuan dikirim ulang.');
    }
}
