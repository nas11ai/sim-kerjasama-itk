<?php

namespace App\Actions\Print;

use App\Jobs\GenerateExport;
use App\Models\ExportJob;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Audit\AuditTrail;
use App\Support\Reporting\ExportThreshold;
use App\Support\Reporting\PrintableDocument;
use App\Support\Reporting\SubmissionVisibility;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-RPT-11 — mencetak dokumen PDF.
 *
 * Haknya diperiksa **sebelum** baris ExportJob dibuat: permintaan yang pasti
 * ditolak tidak perlu meninggalkan jejak permintaan yang seolah sedang
 * diproses, dan Operator yang melihatnya di riwayat akan menunggu berkas yang
 * tidak akan pernah jadi.
 *
 * Ambangnya sama persis dengan ekspor tabular — BR-RPT-11 memang menyatakan
 * demikian, dan angka kedua yang berbeda akan membuat "ambang yang sama"
 * berhenti benar pada pemakaian pertamanya.
 *
 * @method static ExportJob run(User $user, string $document, FormSubmission $submission)
 */
class PrintDocument
{
    use AsAction;

    public function handle(User $user, string $document, FormSubmission $submission): ExportJob
    {
        PrintableDocument::assertPrintable($document, $user, $submission);

        $rowCount = $this->rowCount($document, $user, $submission);

        $job = ExportJob::create([
            'requested_by' => $user->id,
            'type' => $document,
            'filters' => ['form_submission_id' => $submission->id],
            'status' => ExportJob::STATUS_QUEUED,
            'row_count' => $rowCount,
        ]);

        AuditTrail::record('Dokumen dicetak', $submission, $user, null, [
            'document' => $document,
            'export_job_id' => $job->id,
        ]);

        if (ExportThreshold::needsBackgroundJob($rowCount)) {
            GenerateExport::dispatch($job->id);

            return $job;
        }

        GenerateExport::dispatchSync($job->id);

        return $job->refresh();
    }

    /**
     * Berapa baris yang akan masuk dokumen ini — itulah yang menentukan
     * "sederhana" atau "kompleks". Dokumen satu pengajuan selalu sederhana;
     * yang bisa membesar hanya rekap per peneliti.
     */
    private function rowCount(string $document, User $user, FormSubmission $submission): int
    {
        if ($document !== PrintableDocument::RESEARCHER_RECAP) {
            return 1;
        }

        return SubmissionVisibility::for($user)
            ->where('submitted_by', $submission->submitted_by)
            ->count();
    }
}
