<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-REV-07 — Operator menolak pengajuan dengan alasan; ketua diberi tahu beserta alasannya.
 */
class ProposalRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ProposalRejected';

    public function __construct(private readonly FormSubmission $submission) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan ditolak — SIMPAS')
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
            type: 'proposal_rejected',
            title: 'Pengajuan ditolak',
            message: $this->message(),
            context: [
                'form_submission_id' => $this->submission->id,
                'rejection_reason' => $this->submission->rejection_reason,
            ],
        ))->toArray();
    }

    private function message(): string
    {
        return "Pengajuan #{$this->submission->id} ditolak. Alasan: {$this->submission->rejection_reason}";
    }
}
