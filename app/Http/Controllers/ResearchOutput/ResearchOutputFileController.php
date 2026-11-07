<?php

namespace App\Http\Controllers\ResearchOutput;

use App\Http\Controllers\Concerns\ResolvesActor;
use App\Http\Controllers\Controller;
use App\Models\ResearchOutput;
use App\Models\ResearchOutputFile;
use App\Support\PrivateUploadDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** W141 — berkas bukti luaran dari disk privat (pola `SubmissionFileController`, W15). */
class ResearchOutputFileController extends Controller
{
    use ResolvesActor;

    // BR-SM-07 — siapa pun yang boleh membaca pengajuannya boleh membaca buktinya (BR-RO-07: baca).
    public function show(Request $request, ResearchOutput $output, int $file): StreamedResponse
    {
        abort_unless($output->formSubmission->isAccessibleBy($this->actor($request)), 403);

        // Sesudah otorisasi, supaya 404 tidak bisa dipakai menebak berkas luaran lain.
        $record = ResearchOutputFile::query()->whereKey($file)->where('research_output_id', $output->id)->first();
        $disk = Storage::disk(PrivateUploadDisk::NAME);

        abort_unless($record !== null && $disk->exists($record->file_path), 404, 'Berkas tidak ditemukan.');

        return $disk->download($record->file_path, $record->original_name ?? basename($record->file_path));
    }
}
