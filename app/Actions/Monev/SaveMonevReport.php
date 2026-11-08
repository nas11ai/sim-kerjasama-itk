<?php

namespace App\Actions\Monev;

use App\Data\Request\MonevReportInput;
use App\Models\FormSubmission;
use App\Models\User;
use App\Services\Submission\FieldAnswerWriter;
use App\Support\Audit\AuditTrail;
use App\Support\MonevAccessGate;
use App\Support\OptimisticLock;
use App\Support\PhaseAccessGate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * W121 — menyimpan draft laporan monev, di-resolve dari id-nya. Jalur wizard menyaring
 * `parent_submission_id IS NULL`, sehingga laporan anak tidak pernah bisa disimpan di sana.
 *
 * @method static FormSubmission run(FormSubmission $report, MonevReportInput $input, User $user)
 */
class SaveMonevReport
{
    use AsAction;

    private const UPLOAD_DIRECTORY = 'form-uploads';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(FormSubmission $report, MonevReportInput $input, User $user): FormSubmission
    {
        // BR-SM-04, W123 — hanya ketua induk, selama laporan masih draft.
        if (!$user->can('update', $report)) {
            throw new AuthorizationException('Laporan ini terkunci.');
        }

        // W122 / BR-MON-08 — pembekuan berlaku juga bagi draft yang sudah ada.
        MonevAccessGate::assertReportOpen($report);

        // BR-MON-05 — penerapan BR-FE-08: tenggat siklus atau override laporan ini.
        PhaseAccessGate::assertOpenFor($report, $report->formPhaseDetail);

        return DB::transaction(function () use ($report, $input, $user): FormSubmission {
            // BR-FE-14 — dibandingkan di dalam transaksi dengan nilai yang akan ditimpa.
            OptimisticLock::assertNotStale($report, $input->lastUpdatedAt);

            // Layar memuat ulang jawaban tersimpan, jadi kosong berarti dikosongkan.
            $changed = FieldAnswerWriter::overwrite($report, $input->answers, $input->uploads, self::UPLOAD_DIRECTORY);

            // W161, BR-FE-14 — hanya jawaban yang berubah menggeser cap kunci, sama dengan
            // SaveSubmissionDraft: tab lain yang membawa cap lama ditolak 409.
            if ($changed) {
                $report->touch();
            }

            // BR-RPT-09 — satu Action, satu entri audit.
            AuditTrail::record('Draft laporan monev disimpan', $report, $user);

            return $report;
        });
    }
}
