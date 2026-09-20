<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\RejectNidnVerification;
use App\Actions\Identity\VerifyNidn;
use App\Http\Controllers\Controller;
use App\Models\UserProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * BR-IAM-04, BR-IAM-12, BR-IAM-13 — antrian verifikasi NIDN.
 *
 * Dibangun dari status `pending`, bukan "belum diverifikasi": akun undangan lahir
 * `active` tanpa NIDN, dan antrian dari negasi akan memuat baris yang tak jujur.
 */
class NidnVerificationController extends Controller
{
    public function index(): Response
    {
        $this->authorizeVerify();

        return Inertia::render('Identity/VerificationQueuePage', [
            'pending' => UserProfile::query()
                ->awaitingVerification()
                ->with(['user:id,name,email', 'organization:id,name'])
                ->orderBy('created_at')
                ->get()
                ->map(fn (UserProfile $profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->user?->name,
                    'email' => $profile->user?->email,
                    'nidn' => $profile->nidn,
                    'organization' => $profile->organization?->name,
                    'registered_at' => $profile->created_at?->toDateString(),
                ])
                ->values(),
            // BR-IAM-13 — akun yang ditolak tetap terdaftar dan masih bisa
            // diverifikasi setelah NIDN-nya dikoreksi.
            'rejected' => UserProfile::query()
                ->where('verification_status', 'rejected')
                ->with(['user:id,name,email'])
                ->orderByDesc('updated_at')
                ->get()
                ->map(fn (UserProfile $profile): array => [
                    'id' => $profile->id,
                    'name' => $profile->user?->name,
                    'email' => $profile->user?->email,
                    'nidn' => $profile->nidn,
                    'reason' => $profile->rejection_reason,
                ])
                ->values(),
        ]);
    }

    public function verify(UserProfile $profile): RedirectResponse
    {
        $this->authorizeVerify();

        VerifyNidn::run($profile);

        return back()->with('success', 'NIDN diverifikasi.');
    }

    public function reject(Request $request, UserProfile $profile): RedirectResponse
    {
        $this->authorizeVerify();

        // BR-IAM-13 — alasan wajib. Ditegakkan di FormRequest maupun di Action:
        // penolakan juga bisa datang dari jalur lain, dan penolakan tanpa
        // alasan tidak memberi pendaftar apa pun untuk diperbaiki.
        $validated = $request->validate([
            'reason' => 'required|string|min:3|max:2000',
        ]);

        RejectNidnVerification::run($profile, $validated['reason']);

        return back()->with('success', 'Pendaftaran ditolak dan alasannya dikirimkan.');
    }

    private function authorizeVerify(): void
    {
        $user = Auth::user();

        abort_unless($user !== null && $user->can('users.verify'), 403);
    }
}
