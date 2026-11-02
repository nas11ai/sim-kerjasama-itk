<?php

namespace App\Http\Controllers\Identity;

use App\Actions\Identity\ReplaceProfilePhoto;
use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Http\Requests\Identity\ProfilePhotoRequest;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** W65 — foto profil milik sendiri: tanpa parameter di URL, jadi tak ada foto orang lain untuk ditebak. */
class ProfilePhotoController extends Controller
{
    use ResolvesActor;

    public function show(Request $request): StreamedResponse
    {
        $path = $this->actor($request)->userProfile?->photo_path;
        $disk = Storage::disk(PrivateUploadDisk::NAME);

        abort_unless($path !== null && $disk->exists($path), 404);

        return $disk->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function update(ProfilePhotoRequest $request): RedirectResponse
    {
        ReplaceProfilePhoto::run($this->actor($request), $request->photo());

        return back()->with('success', 'Foto profil diperbarui.');
    }
}
