<?php

namespace App\Http\Controllers\Submission;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\FormFieldResponse;
use App\Models\FormSubmission;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Berkas jawaban pengajuan dari disk privat (fe-v2-design W15). */
class SubmissionFileController extends Controller
{
    use ResolvesActor;

    // BR-SM-07 — satu-satunya jalan baca berkas pengajuan
    public function show(Request $request, FormSubmission $submission, FormFieldResponse $response): StreamedResponse
    {
        abort_unless($submission->isAccessibleBy($this->actor($request)), 403);

        // Setelah otorisasi, supaya 404 tidak bisa dipakai menebak berkas pengajuan lain.
        $path = $response->value;
        $disk = Storage::disk(PrivateUploadDisk::NAME);

        abort_unless(
            $response->form_submission_id === $submission->id
                && $response->formField?->fieldType?->name === 'file'
                && $path !== null && $disk->exists($path),
            404,
            'Berkas tidak ditemukan.'
        );

        return $disk->download($path, basename($path));
    }
}
