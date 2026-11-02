<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\CompleteProfile;
use App\Data\View\Identity\OwnProfileData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\CompleteProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * SHARED-01-06 Lengkapi Profil (BR-IAM-03). Terbuka bagi setiap pemilik UserProfile, apa pun
 * status verifikasinya (BR-IAM-04, W63). `next` hanya menerima satu nilai: tanpa open redirect.
 */
class ProfileCompletionController extends Controller
{
    use ResolvesActor;

    private const NEXT_SUBMISSION = 'submission';

    public function create(Request $request): Response|RedirectResponse
    {
        $user = $this->actor($request);
        $profile = $user->userProfile;

        // W63 — tanpa UserProfile tidak ada data tambahan untuk diisi; katakan alasannya
        // (mis. Admin pemegang submissions.create yang datang dari "Buat Pengajuan Baru").
        if ($profile === null) {
            return redirect()->route('profile.edit')
                ->with('error', 'Akun ini tidak memiliki profil peneliti, jadi tidak dapat membuat pengajuan.');
        }

        if ($profile->isComplete()) {
            return redirect()->route('profile.edit');
        }

        return Inertia::render('Profile/CompletePage', [
            'profile' => OwnProfileData::of($user),
            'next' => $this->next($request),
        ]);
    }

    public function store(CompleteProfileRequest $request): RedirectResponse
    {
        CompleteProfile::run($this->actor($request), $request->details(), $request->photo());

        $target = $this->next($request) === self::NEXT_SUBMISSION ? 'user.submissions.create' : 'dashboard';

        return redirect()->route($target)->with('success', 'Profil tersimpan.');
    }

    private function next(Request $request): ?string
    {
        return $request->query('next') === self::NEXT_SUBMISSION ? self::NEXT_SUBMISSION : null;
    }
}
