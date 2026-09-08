<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-15 — user yang dinonaktifkan diberi tahu.
 *
 * Ia tidak akan menemukan sendiri: yang pertama ia temui adalah layar login
 * yang menolaknya, tanpa penjelasan mengapa.
 */
class AccountDeactivatedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'UserDeactivated';

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Akun Anda dinonaktifkan')
            ->line('Akun Anda di SIMPAS dinonaktifkan Operator LPPM.')
            ->line('Data dan riwayat pengajuan Anda tetap tersimpan.')
            ->line('Hubungi lppm@itk.ac.id kalau ini keliru.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'account_deactivated',
            'title' => 'Akun dinonaktifkan',
            'message' => 'Akun Anda dinonaktifkan. Data dan riwayat pengajuan tetap tersimpan.',
        ];
    }
}
