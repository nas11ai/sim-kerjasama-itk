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
        'export_completed' => 'reporting.exports.index',
        'export_failed' => 'reporting.exports.index',
        'reviewer_appointed' => 'reviewer.assignments.index',
    ];

    /**
     * Tipe yang membawa reviewer langsung ke lembar penilaiannya: penugasan baru, dan penilaian yang
     * dibuka ulang karena versi baru diterapkan (BR-REV-19).
     */
    private const EVALUATION_TYPES = ['reviewer_assigned', 'evaluation_version_applied'];

    /** Tipe yang membuka halaman pengajuan menurut kursi penerimanya. */
    private const SUBMISSION_TYPES = [
        'review_thread_opened',
        'review_comment_added',
        'revision_resolved',
        'phase_override_granted',
        'monev_evaluation_submitted',
        'proposal_submitted',
        // ADR 0018 — keputusan review: Operator ke halaman kelola, peserta pemeriksaan ke
        // ringkasan review, peneliti ke halaman pengajuannya — semua menurut kursinya.
        'submission_ready_for_decision',
        'recheck_requested',
        'revision_requested',
        'proposal_approved',
        'proposal_rejected',
    ];

    public function handle(DatabaseNotification $notification, User $recipient): ?string
    {
        $type = $notification->data['type'] ?? null;

        if (!is_string($type)) {
            return null;
        }

        return match (true) {
            array_key_exists($type, self::FIXED_ROUTES) => route(self::FIXED_ROUTES[$type]),
            // BR-MON-10 — konteksnya induk + detail siklus, bukan satu pengajuan yang dibuka.
            $type === 'monev_stage_opened' => MonevCycleDestination::run($notification, $recipient),
            default => $this->submissionDestination($type, $notification, $recipient),
        };
    }

    private function submissionDestination(string $type, DatabaseNotification $notification, User $recipient): ?string
    {
        $isEvaluationType = in_array($type, self::EVALUATION_TYPES, true);
        $isSubmissionType = $isEvaluationType || in_array($type, self::SUBMISSION_TYPES, true);
        $submission = $isSubmissionType ? $this->accessibleSubmission($notification, $recipient) : null;

        if ($submission === null) {
            return null;
        }

        return $isEvaluationType
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
        $isReport = $submission->parent_submission_id !== null;

        // Kursi ketua/anggota didahulukan: Operator yang juga ketua membaca pengajuannya sendiri sebagai peneliti.
        if (SubmissionVisibility::ownedOrJoinedBy($recipient)->whereKey($submission->id)->exists()) {
            return route($isReport ? 'monev.reports.show' : 'user.submissions.show', $submission);
        }

        // W130 — laporan monev dibaca Operator di halaman laporannya juga.
        if (SubmissionVisibility::seesEverything($recipient)) {
            return route($isReport ? 'monev.reports.show' : 'admin.submissions.show', $submission);
        }

        // Lolos isAccessibleBy tanpa dua kursi di atas berarti reviewer aktif.
        return route('reviewer.assignments.summary', $submission);
    }
}
