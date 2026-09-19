<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-15 — verifikasi memberi tahu pendaftar.
 *
 * Kemampuannya berubah tanpa ia melakukan apa pun; tanpa pemberitahuan, ia
 * baru tahu saat kebetulan mencoba lagi.
 */
class NidnVerifiedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'UserVerified';

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Anda sudah terverifikasi')
            ->line('NIDN Anda sudah diverifikasi Operator LPPM.')
            ->line('Pengajuan yang sudah Anda siapkan kini dapat dikirim.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'nidn_verified',
            title: 'Akun terverifikasi',
            message: 'NIDN Anda sudah diverifikasi. Pengajuan kini dapat dikirim.',
        ))->toArray();
    }
}
