<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Queries\Review\ReviewAttachmentSubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * BR-SM-07 — hanya lampiran review yang bisa diunduh, dan hanya oleh pengguna
 * yang boleh melihat pengajuan pemiliknya. Berkas lain di disk `public` tertutup.
 */
class ReviewAttachmentDownloadController extends Controller
{
    public function __invoke(Request $request): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 403);

        $path = (string) $request->query('path');
        $submission = ReviewAttachmentSubmission::run($path);

        abort_if($submission === null, 404, 'File tidak ditemukan');
        abort_unless($submission->isAccessibleBy($user), 403);
        // Setelah otorisasi, supaya 404 tidak bisa dipakai menebak berkas yang ada.
        abort_unless(Storage::disk('public')->exists($path), 404, 'File tidak ditemukan');

        return response()->download(Storage::disk('public')->path($path));
    }
}
