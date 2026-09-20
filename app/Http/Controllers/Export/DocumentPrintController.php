<?php

namespace App\Http\Controllers\Export;

use App\Actions\Print\PrintDocument;
use App\Http\Controllers\Controller;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Support\Reporting\PrintableDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * BR-RPT-11 — satu Action, satu use case: penolakan haknya dilempar Action
 * sebagai `AuthorizationException` dan berubah jadi 403 oleh handler, bukan
 * diperiksa ulang di sini dengan aturan yang bisa beda.
 */
class DocumentPrintController extends Controller
{
    public function __invoke(Request $request, FormSubmission $submission, string $document): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        try {
            PrintableDocument::assertSupported($document);
        } catch (InvalidArgumentException) {
            abort(404, 'Dokumen tidak dikenal.');
        }

        $job = PrintDocument::run($user, $document, $submission);

        return back()->with(
            'success',
            $job->status === ExportJob::STATUS_COMPLETED
                ? PrintableDocument::label($document).' siap diunduh.'
                : PrintableDocument::label($document).' sedang diproses — Anda diberi tahu saat selesai.',
        );
    }
}
