<?php

namespace App\Actions\FormSubmission;

use App\Models\FormSubmission;
use App\Queries\Submission\ColumnSelectorAnswer;
use App\Support\Audit\AuditChange;
use App\Support\Audit\AuditTrail;
use App\Support\SubmissionTitleField;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-SM-22 — menyalin jawaban field `submission_title` ke kolom `form_submissions.title`. Daftar,
 * detail, pencarian, dan ekspor membaca kolom itu tanpa tahu field mana yang menyimpannya — pola
 * yang sama dengan `scheme_selector` (ADR 0005).
 *
 * @method static void run(FormSubmission $submission)
 */
class SyncSubmissionTitle
{
    use AsAction;

    public function handle(FormSubmission $submission): void
    {
        $answer = ColumnSelectorAnswer::run($submission, SubmissionTitleField::TYPE);

        // Form tanpa field judul: kolomnya tidak disentuh, judul Form yang tampil.
        if ($answer === null) {
            return;
        }

        $title = $answer['value'] === null ? null : trim($answer['value']);
        $title = $title === '' ? null : $title;

        if ($submission->title === $title) {
            return;
        }

        $sebelumnya = $submission->title;
        $submission->update(['title' => $title]);

        // BR-RPT-09 — dicatat hanya saat berubah, sama dengan SyncSchemeSelection.
        AuditTrail::record(
            'Judul pengajuan diubah',
            $submission,
            null,
            new AuditChange(old: ['title' => $sebelumnya], new: ['title' => $title]),
        );
    }
}
