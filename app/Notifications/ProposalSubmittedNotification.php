<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProposalSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ProposalSubmitted';

    public function __construct(private readonly FormSubmission $submission) {}

    /**
     * W39 — tanpa `action_url`: tujuan "Buka" dihitung NotificationDestination saat diklik.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'proposal_submitted',
            title: 'Pengajuan Baru Masuk',
            message: "Pengajuan #{$this->submission->id} telah dikirim dan menunggu penugasan penilai.",
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan Baru | SIMPAS')
            ->line("Pengajuan #{$this->submission->id} telah dikirim.")
            ->action('Lihat Detail', url("/submissions/{$this->submission->id}"));
    }
}
