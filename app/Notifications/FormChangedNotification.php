<?php

namespace App\Notifications;

use App\Models\Form;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-FE-13 — pemilik draft diberi tahu bahwa formulirnya berubah.
 *
 * Isinya sengaja tidak merinci field mana yang berubah: yang perlu peneliti
 * lakukan adalah membuka kembali draftnya dan melihat sendiri, dan daftar
 * perubahan yang panjang justru membuat pesan ini tidak dibaca.
 */
class FormChangedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'FormChanged';

    public function __construct(private readonly Form $form) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Formulir pengajuan berubah')
            ->line('Formulir "'.$this->form->title.'" diperbarui Admin.')
            ->line('Buka kembali draft Anda untuk memeriksa isian yang terdampak.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'form_changed',
            'form_id' => $this->form->id,
            'title' => 'Formulir pengajuan berubah',
            'message' => 'Formulir "'.$this->form->title.'" diperbarui. Periksa kembali draft Anda.',
        ];
    }
}
