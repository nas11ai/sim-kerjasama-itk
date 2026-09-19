<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-MON-10 — reviewer menyelesaikan penilaian monev.
 *
 * Peneliti diberi tahu karena hasilnya **langsung terbuka** untuknya
 * (BR-MON-09) — berbeda dari pengajuan awal, yang menahan skor sampai
 * keputusan akhir. Monev formatif: umpan balik yang datang terlambat tidak
 * bisa lagi mengoreksi arah kegiatan.
 */
class MonevEvaluationSubmittedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'MonevEvaluationSubmitted';

    public function __construct(private readonly FormSubmission $report) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hasil evaluasi monev tersedia')
            ->line('Reviewer sudah menyelesaikan penilaian atas laporan monev Anda.')
            ->line('Skor dan catatannya dapat dibaca sekarang.');
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'monev_evaluation_submitted',
            title: 'Hasil evaluasi monev tersedia',
            message: 'Reviewer sudah menyelesaikan penilaian atas laporan monev Anda.',
            context: ['form_submission_id' => $this->report->id],
        ))->toArray();
    }
}
