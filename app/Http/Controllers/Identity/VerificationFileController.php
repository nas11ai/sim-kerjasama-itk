<?php

namespace App\Http\Controllers\Identity;

use App\Http\Controllers\Controller;
use App\Models\FormFieldResponse;
use App\Models\UserProfile;
use App\Queries\Identity\BiodataFiles;
use App\Support\PrivateUploadDisk;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** W58 — berkas biodata pendaftar dari disk privat (W15); otorisasi `can:users.verify` di route. */
class VerificationFileController extends Controller
{
    public function show(UserProfile $profile, FormFieldResponse $response): StreamedResponse
    {
        // Sesudah otorisasi, dan satu jawaban 404 untuk semua sebab: tidak membocorkan
        // apakah berkas milik profil lain itu ada.
        $path = $response->value;
        $disk = Storage::disk(PrivateUploadDisk::NAME);

        abort_unless(
            BiodataFiles::belongsTo($profile, $response) && $path !== null && $disk->exists($path),
            404,
            'Berkas tidak ditemukan.'
        );

        return $disk->download($path, basename($path));
    }
}
