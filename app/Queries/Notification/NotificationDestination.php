<?php

namespace App\Queries\Notification;

use App\Actions\Concerns\ChangesNoState;
use App\Models\FormSubmission;
use App\Models\User;
use App\Support\Reporting\SubmissionVisibility;
use Illuminate\Notifications\DatabaseNotification;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * BR-NOT-04, W39 — halaman yang dibuka "Buka" sebuah notifikasi. Dihitung saat
 * diklik dari `type` + konteks payload, bukan disimpan sebagai URL: akses ke
 * pengajuannya dicek ulang saat itu juga (BR-SM-07), dan tak ada URL basi di DB.
 *
 * @method static string|null run(DatabaseNotification $notification, User $recipient)
 */
class NotificationDestination implements ChangesNoState
{
    use AsAction;

    /** Tipe yang tujuannya tetap, tanpa konteks. */
    private const FIXED_ROUTES = [
        'submission_period_active' => 'user.submissions.index',
        'submission_period_closed' => 'user.submissions.index',
        'submission_deadline_extended' => 'user.submissions.index',
        'form_changed' => 'user.submissions.index',
        'account_reactivated' => 'dashboard',
        'nidn_verified' => 'verification.status',
        'nidn_verification_rejected' => 'verification.status',
        'new_registration_awaiting_verification' => 'admin.verifications.index',
        'export_completed' => 'exports.index',
        'export_failed' => 'exports.index',
        'reviewer_appointed' => 'reviewer.assignments.index',
    ];

    /** Tipe yang membuka halaman pengajuan menurut kursi penerimanya. */
    private const SUBMISSION_TYPES = [
        'review_thread_opened',
        'review_comment_added',
        'revision_resolved',
        'phase_override_granted',
        'monev_stage_opened',
        'monev_evaluation_submitted',
        'proposal_submitted',
    ];

    public function handle(DatabaseNotification $notification, User $recipient): ?string
    {
        $type = $notification->data['type'] ?? null;

        if (!is_string($type)) {
            return null;
        }

        if (array_key_exists($type, self::FIXED_ROUTES)) {
            return route(self::FIXED_ROUTES[$type]);
        }

        $isSubmissionType = $type === 'reviewer_assigned' || in_array($type, self::SUBMISSION_TYPES, true);
        $submission = $isSubmissionType ? $this->accessibleSubmission($notification, $recipient) : null;

        if ($submission === null) {
            return null;
        }

        return $type === 'reviewer_assigned'
            ? route('reviewer.assignments.evaluation', $submission)
            : $this->submissionPage($submission, $recipient);
    }

    /**
     * BR-SM-07 — gerbang akses level pengajuan. Ia hanya menghitung penugasan
     * `active`, sehingga reviewer yang diganti (BR-REV-10) tertolak di sini.
     */
    private function accessibleSubmission(DatabaseNotification $notification, User $recipient): ?FormSubmission
    {
        $id = $notification->data['form_submission_id'] ?? null;
        $submission = is_int($id) ? FormSubmission::query()->find($id) : null;

        if ($submission === null || !$submission->isAccessibleBy($recipient)) {
            return null;
        }

        return $submission;
    }

    private function submissionPage(FormSubmission $submission, User $recipient): string
    {
        // Kursi ketua/anggota didahulukan: Operator yang juga ketua membaca pengajuannya sendiri sebagai peneliti.
        if (SubmissionVisibility::ownedOrJoinedBy($recipient)->whereKey($submission->id)->exists()) {
            return route('user.submissions.show', $submission);
        }

        if (SubmissionVisibility::seesEverything($recipient)) {
            return route('admin.submissions.show', $submission);
        }

        // Lolos isAccessibleBy tanpa dua kursi di atas berarti reviewer aktif.
        return route('reviewer.assignments.summary', $submission);
    }
}
