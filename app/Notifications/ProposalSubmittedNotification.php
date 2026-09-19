<?php

namespace App\Notifications;

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

    /** @return array{title: string, message: string, action_url: string, type: string, meta: array{submission_id: int}} */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Pengajuan Baru Masuk',
            'message' => "Pengajuan #{$this->submission->id} telah disubmit dan menunggu penugasan reviewer.",
            'action_url' => "/submissions/{$this->submission->id}",
            'type' => 'info',
            'meta' => ['submission_id' => $this->submission->id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan Baru — SIMPAS')
            ->line("Pengajuan #{$this->submission->id} telah disubmit.")
            ->action('Lihat Detail', url("/submissions/{$this->submission->id}"));
    }
}
