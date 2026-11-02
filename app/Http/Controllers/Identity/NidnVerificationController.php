<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\CorrectNidn;
use App\Actions\Identity\RejectNidnVerification;
use App\Actions\Identity\VerifyNidn;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\CorrectNidnRequest;
use App\Http\Requests\Identity\RejectVerificationRequest;
use App\Models\UserProfile;
use App\Queries\Identity\VerificationQueue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/** OPERATOR-08-02 — antrian verifikasi NIDN; route-nya dijaga `can:users.verify` (W47). */
class NidnVerificationController extends Controller
{
    use ResolvesActor;

    public function index(): Response
    {
        return Inertia::render('Identity/VerificationIndexPage', VerificationQueue::run());
    }

    public function verify(UserProfile $profile): RedirectResponse
    {
        VerifyNidn::run($profile);

        return back()->with('success', 'NIDN diverifikasi.');
    }

    public function reject(RejectVerificationRequest $request, UserProfile $profile): RedirectResponse
    {
        RejectNidnVerification::run($profile, $request->reason());

        return back()->with('success', 'Pendaftaran ditolak dan alasannya dikirimkan.');
    }

    // BR-IAM-13 — koreksi tidak memverifikasi; Operator tetap menekan Verifikasi sesudahnya.
    public function correctNidn(CorrectNidnRequest $request, UserProfile $profile): RedirectResponse
    {
        CorrectNidn::run($this->actor($request), $profile, $request->nidn());

        return back()->with('success', 'NIDN dikoreksi.');
    }
}
