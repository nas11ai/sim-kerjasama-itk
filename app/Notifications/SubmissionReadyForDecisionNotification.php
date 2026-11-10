<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-REV-05, BR-REV-16 — putaran review selesai tanpa catatan terbuka; Operator pemegang
 * `submissions.view-all` yang harus memutuskan, jadi cukup in-app (spec §4.7).
 */
class SubmissionReadyForDecisionNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'SubmissionReadyForDecision';

    public function __construct(private readonly FormSubmission $submission) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan siap diputuskan — SIMPAS')
            ->line($this->message());
    }

    /**
     * W39 — tanpa `action_url`: tujuan "Buka" dihitung NotificationDestination saat diklik.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'submission_ready_for_decision',
            title: 'Pengajuan siap diputuskan',
            message: $this->message(),
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }

    private function message(): string
    {
        return "Pengajuan #{$this->submission->id} siap disetujui atau ditolak.";
    }
}
