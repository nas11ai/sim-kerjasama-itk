<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\User;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-IAM-15 — pendaftaran baru memberi tahu Operator; arahnya sengaja beda dari
 * tiga event IAM lainnya — di sini Operator yang bertindak, bukan pendaftarnya,
 * karena dialah yang harus memverifikasi NIDN.
 */
class NewRegistrationAwaitingVerification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'UserRegistered';

    public function __construct(private readonly User $registrant) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pendaftar baru menunggu verifikasi NIDN')
            ->line($this->registrant->name.' mendaftar dan menunggu verifikasi NIDN.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'new_registration_awaiting_verification',
            title: 'Pendaftar baru menunggu verifikasi',
            message: $this->registrant->name.' mendaftar dan menunggu verifikasi NIDN.',
            context: ['user_id' => $this->registrant->id],
        ))->toArray();
    }
}
