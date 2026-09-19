<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-13, BR-IAM-15 — penolakan verifikasi beserta **alasannya**.
 *
 * Alasan ikut terkirim, bukan sekadar fakta penolakannya: itulah satu-satunya
 * yang dipunyai pendaftar untuk memperbaiki datanya, dan mendaftar ulang
 * mustahil karena email serta NIDN-nya sudah terpakai.
 */
class NidnVerificationRejectedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'UserVerificationRejected';

    public function __construct(private readonly string $reason) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi NIDN belum dapat disetujui')
            ->line('Operator LPPM belum dapat memverifikasi NIDN Anda.')
            ->line('Alasan: '.$this->reason)
            ->line('Akun Anda tidak dihapus. Hubungi lppm@itk.ac.id untuk koreksi.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'nidn_verification_rejected',
            title: 'Verifikasi NIDN ditolak',
            message: 'Alasan: '.$this->reason,
            context: ['reason' => $this->reason],
        ))->toArray();
    }
}
