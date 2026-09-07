<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-15 — orang yang ditunjuk sebagai reviewer diberi tahu.
 *
 * Berbeda dari ReviewerAssignedNotification, yang memberi tahu **penugasan
 * atas satu pengajuan**. Yang ini memberi tahu perubahan perannya di sistem.
 */
class ReviewerAppointedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly string $reviewerType) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Anda ditunjuk sebagai reviewer')
            ->line('Operator LPPM menunjuk Anda sebagai reviewer '.$this->label().'.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'reviewer_appointed',
            'reviewer_type' => $this->reviewerType,
            'title' => 'Ditunjuk sebagai reviewer',
            'message' => 'Anda ditunjuk sebagai reviewer '.$this->label().'.',
        ];
    }

    private function label(): string
    {
        return $this->reviewerType === 'internal' ? 'internal' : 'eksternal';
    }
}
