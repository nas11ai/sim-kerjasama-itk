<?php

namespace App\Support\Reporting;

use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Menulis dokumen PDF ke disk yang sama dengan ekspor tabular — satu
 * penyimpanan, satu retensi (BR-RPT-12), satu unduhan bertanda tangan
 * (BR-RPT-03). Penyimpanan sendiri menulis ulang ketiganya, dan itu menyimpang.
 */
final class DocumentWriter
{
    public static function write(ExportJob $job, User $user): string
    {
        $submission = self::subject($job);

        PrintableDocument::assertPrintable($job->type, $user, $submission);

        $pdf = Pdf::loadView('documents.'.$job->type, [
            'document' => PrintableDocument::label($job->type),
            'submission' => $submission,
            'printedBy' => $user,
            'printedAt' => now(),
        ]);

        $path = ExportWriter::DIRECTORY.'/'.$job->type.'-'.$job->id.'-'.now()->format('Ymd-His').'.pdf';

        Storage::disk(ExportWriter::DISK)->put($path, $pdf->output());

        return $path;
    }

    public static function subject(ExportJob $job): FormSubmission
    {
        $id = $job->filters['form_submission_id'] ?? null;

        if (!is_int($id) && !is_string($id)) {
            throw new RuntimeException("ExportJob {$job->id} tidak menyebut pengajuan yang dicetak.");
        }

        return FormSubmission::query()->findOrFail((int) $id);
    }
}
