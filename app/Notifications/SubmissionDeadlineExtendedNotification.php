<?php

namespace App\Notifications;

use App\Models\SubmissionPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-SCH-08 — perpanjangan batas waktu diberitahukan ke pemilik draft.
 *
 * Peneliti yang sudah menyerah karena batasnya lewat tidak punya alasan untuk
 * membuka aplikasi lagi. Perpanjangan yang tidak diberitahukan praktis sama
 * dengan tidak diberikan.
 */
class SubmissionDeadlineExtendedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly SubmissionPeriod $period) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Batas waktu pengajuan diperpanjang')
            ->line('Batas waktu pada periode "'.$this->period->name.'" diperpanjang.')
            ->line('Draft Anda masih dapat dilanjutkan.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'submission_deadline_extended',
            'submission_period_id' => $this->period->id,
            'title' => 'Batas waktu diperpanjang',
            'message' => 'Batas waktu pada periode "'.$this->period->name.'" diperpanjang. Draft Anda masih dapat dilanjutkan.',
        ];
    }
}
