<?php

namespace App\Queries\Submission;

use App\Actions\Concerns\ChangesNoState;
use App\Data\View\Submission\SubmissionHistoryEntryData;
use App\Models\AuditEntry;
use App\Models\FormSubmission;
use App\Models\User;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Jejak audit satu pengajuan (BR-RPT-09), terbaru dulu. Penonton detail sudah lolos
 * BR-SM-07, jadi seluruh entri pengajuan ini boleh ia baca (BR-RPT-05).
 *
 * @method static list<SubmissionHistoryEntryData> run(FormSubmission $submission)
 */
class SubmissionHistory implements ChangesNoState
{
    use AsAction;

    /** @return list<SubmissionHistoryEntryData> */
    public function handle(FormSubmission $submission): array
    {
        return array_values(AuditEntry::query()->auditOnly()
            ->where('subject_type', FormSubmission::class)
            ->where('subject_id', $submission->id)
            ->with('causer')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (AuditEntry $entry): SubmissionHistoryEntryData => new SubmissionHistoryEntryData(
                description: $entry->description,
                actor: $entry->causer instanceof User ? $entry->causer->name : null,
                at: $entry->created_at?->toIso8601String(),
            ))
            ->all());
    }
}
