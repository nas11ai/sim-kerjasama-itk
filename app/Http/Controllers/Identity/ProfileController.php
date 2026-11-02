<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\UpdateOwnProfile;
use App\Data\View\Identity\OwnProfileData;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\UpdateOwnProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** SHARED-01-07 Profil Saya — profil milik sendiri; kata sandi lewat `password.update` (W66). */
class ProfileController extends Controller
{
    use ResolvesActor;

    public function show(Request $request): Response
    {
        return Inertia::render('Profile/ShowPage', ['profile' => OwnProfileData::of($this->actor($request))]);
    }

    public function update(UpdateOwnProfileRequest $request): RedirectResponse
    {
        UpdateOwnProfile::run($this->actor($request), $request->string('name')->trim()->toString(), $request->details());

        return back()->with('success', 'Profil diperbarui.');
    }
}
