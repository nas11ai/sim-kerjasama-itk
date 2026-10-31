<?php

namespace App\Http\Controllers\Submission;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\FormSubmission;
use App\Queries\Submission\SubmissionRevision;
use App\States\Submission\NeedsRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** RESEARCHER-02-05 — membaca catatan reviewer dan mengirim ulang revisi. */
class SubmissionRevisionController extends Controller
{
    use ResolvesActor;

    public function __invoke(Request $request, FormSubmission $submission): Response|RedirectResponse
    {
        $viewer = $this->actor($request);

        // BR-SM-07 — lebih dulu dari pengalihan, supaya orang luar ditolak, bukan diantar ke detail.
        abort_unless($submission->isAccessibleBy($viewer), 403, 'Akses tidak sah terhadap pengajuan.');

        // Sesudah Kirim Ulang, `back()` mendarat di sini lagi; pengajuan yang tak lagi
        // menunggu revisi dibaca di detail.
        if (!$submission->status instanceof NeedsRevision) {
            return redirect()->route('user.submissions.show', $submission);
        }

        return Inertia::render('Submission/RevisionPage', SubmissionRevision::run($viewer, $submission));
    }
}
