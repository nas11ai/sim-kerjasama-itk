<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-NOT-02 — reviewer menutup satu ulir diskusi; peneliti utama cukup diberi kabar
 * in-app karena tak ada yang menuntut tindakannya.
 */
class RevisionResolvedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'RevisionResolved';

    public function __construct(private readonly FormSubmission $submission) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Catatan review ditandai selesai')
            ->line('Reviewer menandai salah satu catatan review atas pengajuan Anda selesai.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'revision_resolved',
            title: 'Catatan review ditandai selesai',
            message: 'Reviewer menandai salah satu catatan review atas pengajuan Anda selesai.',
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }
}
