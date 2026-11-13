<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\ExportJob;
use App\Notifications\Concerns\UsesConfiguredChannels;
use App\Support\Reporting\ExportType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-RPT-08 — kegagalan final memberi tahu Operator yang meminta, dan
 * menyebut bahwa sistem **berhenti mencoba** — tanpa itu Operator menunggu
 * percobaan berikutnya yang tak pernah datang, dan ekspornya tak diulang.
 */
class ExportFailedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ExportFailed';

    public function __construct(private readonly ExportJob $job) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ekspor gagal')
            ->line(ExportType::label($this->job->type).' yang Anda minta gagal diproses.')
            ->line('Sistem tidak akan mengulangnya sendiri, jalankan ekspor ulang dari layar Ekspor Data.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'export_failed',
            title: 'Export gagal, coba lagi',
            message: ExportType::label($this->job->type).' gagal setelah '.$this->job->attempts.' percobaan. '
                .'Sistem tidak akan mengulang sendiri.',
            context: ['export_job_id' => $this->job->id],
        ))->toArray();
    }
}
