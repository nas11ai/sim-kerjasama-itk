<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-20, BR-IAM-15 — user yang akunnya diaktifkan kembali diberi tahu; tanpa itu ia
 * tidak tahu bahwa login sudah bisa dicoba lagi.
 */
class AccountReactivatedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'UserReactivated';

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Anda aktif kembali')
            ->line('Akun Anda di SIMPAS diaktifkan kembali oleh LPPM.')
            ->line('Anda bisa masuk lagi dengan email dan kata sandi yang sama.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'account_reactivated',
            title: 'Akun aktif kembali',
            message: 'Akun Anda diaktifkan kembali. Anda bisa masuk seperti biasa.',
        ))->toArray();
    }
}
