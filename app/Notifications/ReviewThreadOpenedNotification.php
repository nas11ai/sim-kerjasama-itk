<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\ReviewSummary;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Reviewer membuka ulir diskusi baru; peserta diskusi lain perlu menanggapinya. */
class ReviewThreadOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ReviewThreadOpened';

    public function __construct(private readonly ReviewSummary $summary) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ulir penilaian baru | SIMPAS')
            ->line($this->message());
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'review_thread_opened',
            title: 'Ulir penilaian baru',
            message: $this->message(),
            context: [
                'form_submission_id' => $this->summary->form_submission_id,
                'review_summary_id' => $this->summary->id,
            ],
        ))->toArray();
    }

    private function message(): string
    {
        return "Penilai membuka ulir penilaian baru atas pengajuan #{$this->summary->form_submission_id}.";
    }
}
