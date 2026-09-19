<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\SubmissionPeriod;
use App\Notifications\Concerns\UsesConfiguredChannels;
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
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'SubmissionDeadlineExtended';

    public function __construct(private readonly SubmissionPeriod $period) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Batas waktu pengajuan diperpanjang')
            ->line('Batas waktu pada periode "'.$this->period->name.'" diperpanjang.')
            ->line('Draft Anda masih dapat dilanjutkan.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'submission_deadline_extended',
            title: 'Batas waktu diperpanjang',
            message: 'Batas waktu pada periode "'.$this->period->name.'" diperpanjang. Draft Anda masih dapat dilanjutkan.',
            context: ['submission_period_id' => $this->period->id],
        ))->toArray();
    }
}
