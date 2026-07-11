<?php

namespace App\Http\Middleware;

use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Reviewer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\States\Submission\SubmissionStatus;
use App\States\Submission\Approved;
use App\States\Submission\NeedsRevision;
use App\States\Submission\Rejected;
use App\States\Submission\Submitted;
use App\States\Submission\UnderReview;

class CheckBiodata
{
    private const ALLOWED_ROUTES = [
        'user.dashboard',
        'user.biodata.index',
        'user.biodata.submit',
        'profile.edit',
        'profile.update',
        'profile.destroy',
        'logout',
    ];

    private const ROLES_REQUIRING_BIODATA = ['Mahasiswa', 'Tenaga Kependidikan'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$this->needsBiodataCheck($user)) {
            $this->setSessionStatus([
                'required' => false,
                'completed' => true,
                'showAllMenus' => true,
            ]);

            return $next($request);
        }

        $biodataForm = $this->getBiodataForm();

        if (!$biodataForm) {
            $this->handleMissingBiodataForm();

            return $next($request);
        }

        $submission = $this->getUserBiodataSubmission($user, $biodataForm);

        return $this->handleBiodataStatus($request, $next, $submission, $biodataForm);
    }

    private function needsBiodataCheck($user): bool
    {
        // Skip biodata check if user is an active reviewer
        if (
            Reviewer::where('user_id', $user->id)
                ->where(function ($query) {
                    $query->whereNull('end_date')
                        ->orWhere('end_date', '>=', now());
                })
                ->exists()
        ) {
            return false;
        }

        $userRoles = $user->getRoleNames()->toArray();

        return collect($userRoles)
            ->intersect(self::ROLES_REQUIRING_BIODATA)
            ->isNotEmpty();
    }

    private function getBiodataForm(): ?Form
    {
        return Form::where('form_type_id', 1)
            ->where('is_active', true)
            ->first();
    }

    private function getUserBiodataSubmission($user, Form $biodataForm): ?FormSubmission
    {
        return FormSubmission::where('form_id', $biodataForm->id)
            ->where('submitted_by', $user->id)
            ->first();
    }

    private function handleMissingBiodataForm(): void
    {
        Log::warning('Formulir biodata tidak ditemukan atau sedang tidak aktif di dalam database.');

        $this->setSessionStatus([
            'required' => false,
            'completed' => true,
            'showAllMenus' => true,
            'error' => true,
            'message' => 'Formulir biodata tidak ditemukan. Silakan hubungi administrator.',
        ]);
    }

    private function handleBiodataStatus(
        Request $request,
        Closure $next,
        ?FormSubmission $submission,
        Form $biodataForm
    ): Response {
        if (!$submission || !$submission->is_submitted) {
            return $this->handleNoBiodata($request, $next, $biodataForm);
        }

        if (!$submission->status instanceof Approved) {
            return $this->handlePendingBiodata($request, $next, $submission, $biodataForm);
        }

        return $this->handleApprovedBiodata($request, $next, $submission);
    }

    private function handleNoBiodata(Request $request, Closure $next, Form $biodataForm): Response
    {
        $this->setSessionStatus([
            'required' => true,
            'completed' => false,
            'showAllMenus' => false,
            'status' => 'not_submitted',
            'message' => 'Silakan lengkapi biodata Anda terlebih dahulu.',
            'form_id' => $biodataForm->id,
        ]);

        if ($this->shouldRedirect($request)) {
            return redirect()
                ->route('user.biodata.index')
                ->with('warning', 'Silakan lengkapi biodata Anda terlebih dahulu untuk mengakses fitur lainnya.');
        }

        return $next($request);
    }

    private function handlePendingBiodata(
        Request $request,
        Closure $next,
        FormSubmission $submission,
        Form $biodataForm
    ): Response {
        $status = $submission->status;

        $statusMessage = $this->getStatusMessage($status);

        $this->setSessionStatus([
            'required' => true,
            'completed' => false,
            'showAllMenus' => false,
            'status' => $status->label(),
            'message' => $statusMessage,
            'submission_id' => $submission->id,
            'form_id' => $biodataForm->id,
            'can_edit' => $status instanceof Rejected
                || $status instanceof NeedsRevision,
        ]);

        if ($this->shouldRedirect($request)) {
            return redirect()
                ->route('user.dashboard')
                ->with('info', $statusMessage . ' Akses menu lain akan dibuka setelah biodata disetujui.');
        }

        return $next($request);
    }

    private function handleApprovedBiodata(Request $request, Closure $next, FormSubmission $submission): Response
    {
        $this->setSessionStatus([
            'required' => true,
            'completed' => true,
            'showAllMenus' => true,
            'status' => 'approved',
            'submission_id' => $submission->id,
        ]);

        return $next($request);
    }

    private function shouldRedirect(Request $request): bool
    {
        $currentRoute = $request->route()?->getName();

        return !in_array($currentRoute, self::ALLOWED_ROUTES);
    }

    private function getStatusMessage(SubmissionStatus $status): string
    {
        return match (true) {
            $status instanceof Submitted =>
            'Biodata Anda telah dikirim dan sedang menunggu proses review.',

            $status instanceof UnderReview =>
            'Biodata Anda sedang dalam proses review.',

            $status instanceof Rejected =>
            'Biodata Anda ditolak. Silakan perbaiki dan kirim ulang.',

            $status instanceof NeedsRevision =>
            'Biodata Anda memerlukan revisi. Silakan perbaiki.',

            default =>
            'Biodata Anda belum disetujui.',
        };
    }

    private function setSessionStatus(array $status): void
    {
        session(['biodataStatus' => $status]);
    }
}
