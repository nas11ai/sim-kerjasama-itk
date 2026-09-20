<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\ExportJob;
use App\Notifications\Concerns\UsesConfiguredChannels;
use App\Support\Reporting\ExportRetention;
use App\Support\Reporting\ExportType;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-RPT-07 — ekspor selesai memberi tahu **yang memintanya**, bukan semua
 * Operator: yang lain tidak menunggu berkas ini, dan notifikasi ke semua
 * pemegang peran berhenti dibaca oleh yang benar-benar dituju.
 */
class ExportCompletedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ExportCompleted';

    public function __construct(private readonly ExportJob $job) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Ekspor selesai')
            ->line(ExportType::label($this->job->type).' yang Anda minta sudah siap diunduh.')
            ->line('Tautan unduhan berlaku '.ExportRetention::SIGNED_URL_HOURS.' jam, dan berkasnya '
                .'dihapus otomatis setelah '.ExportRetention::HOURS.' jam.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'export_completed',
            title: 'Ekspor selesai',
            message: ExportType::label($this->job->type).' siap diunduh ('.$this->job->row_count.' baris).',
            context: ['export_job_id' => $this->job->id],
        ))->toArray();
    }
}
