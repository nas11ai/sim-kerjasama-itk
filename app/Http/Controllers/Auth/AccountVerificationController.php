<?php

namespace App\Http\Controllers\Auth;

use App\Data\View\AccountVerificationData;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountVerificationController extends Controller
{
    /**
     * BR-IAM-04 — layar Menunggu Verifikasi. Akun yang sudah boleh submit, atau yang
     * tidak punya NIDN untuk diverifikasi (jalur undangan, operator), tidak punya urusan di sini.
     */
    public function __invoke(Request $request): RedirectResponse|Response
    {
        $user = $request->user();
        $profile = $user?->userProfile;

        if ($user === null || $profile === null || $profile->verification_status->canSubmit()) {
            return redirect()->route('user.dashboard');
        }

        return Inertia::render('Auth/VerificationStatusPage', [
            'verification' => AccountVerificationData::fromProfile($user, $profile),
        ]);
    }
}
