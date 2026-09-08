<?php

namespace App\Notifications;

use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-REV-01 — reviewer yang ditugaskan diberi tahu.
 *
 * Tanpa ini reviewer baru tahu ada tugas saat kebetulan membuka aplikasi,
 * dan tenggat evaluasinya terlanjur berjalan.
 */
class ReviewerAssignedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ReviewerAssigned';

    public function __construct(private readonly FormSubmission $submission) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Penugasan review baru')
            ->line('Anda ditugaskan untuk mengevaluasi sebuah pengajuan.')
            ->line('Judul: '.($this->submission->title ?? 'Pengajuan #'.$this->submission->id));
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reviewer_assigned',
            'form_submission_id' => $this->submission->id,
            'title' => 'Penugasan review baru',
            'message' => 'Anda ditugaskan untuk mengevaluasi sebuah pengajuan.',
        ];
    }
}
