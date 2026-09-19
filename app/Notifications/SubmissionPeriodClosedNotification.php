<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\SubmissionPeriod;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-SM-10 — pemilik draft diberi tahu saat periodenya ditutup.
 *
 * Draft-nya tidak dihapus, hanya berubah read-only; tanpa pemberitahuan,
 * peneliti baru menyadarinya ketika mencoba menyunting dan ditolak.
 */
class SubmissionPeriodClosedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'SubmissionPeriodClosed';

    public function __construct(private readonly SubmissionPeriod $period) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Periode {$this->period->name} telah ditutup")
            ->line("Periode pengajuan {$this->period->name} sudah ditutup.")
            ->line('Draft Anda tetap tersimpan, tetapi tidak dapat disunting lagi.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'submission_period_closed',
            title: 'Periode pengajuan ditutup',
            message: "Periode {$this->period->name} sudah ditutup. Draft Anda tetap tersimpan sebagai arsip.",
            context: ['submission_period_id' => $this->period->id],
        ))->toArray();
    }
}
