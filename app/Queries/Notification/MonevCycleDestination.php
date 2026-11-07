<?php

namespace App\Queries\Notification;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Notifications\DatabaseNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-MON-10 — tujuan notifikasi "siklus monev dibuka" (konteks induk + detail siklus). Ketua
 * dan anggota ke Daftar Monev, Operator ke induk, reviewer laporan siklus itu ke penugasannya;
 * selain itu tanpa tujuan (BR-SM-07).
 *
 * @method static string|null run(DatabaseNotification $notification, User $recipient)
 */
class MonevCycleDestination implements ChangesNoState
{
    use AsAction;

    public function handle(DatabaseNotification $notification, User $recipient): ?string
    {
        $parentId = $notification->data['form_submission_id'] ?? null;
        $detailId = $notification->data['form_phase_detail_id'] ?? null;

        if (!is_int($parentId) || !is_int($detailId)) {
            return null;
        }

        if (SubmissionVisibility::ownedOrJoinedBy($recipient)->whereKey($parentId)->exists()) {
            return route('monev.index');
        }

        if (SubmissionVisibility::seesEverything($recipient)) {
            return route('admin.submissions.show', $parentId);
        }

        return $this->reviewsReport($parentId, $detailId, $recipient) ? route('reviewer.assignments.index') : null;
    }

    /** Reviewer induk bukan reviewer laporannya: penugasan monev per siklus (BR-MON-04). */
    private function reviewsReport(int $parentId, int $detailId, User $recipient): bool
    {
        $report = FormSubmission::query()
            ->where('parent_submission_id', $parentId)
            ->where('form_phase_detail_id', $detailId)
            ->first();

        return $report?->isAccessibleBy($recipient) === true;
    }
}
