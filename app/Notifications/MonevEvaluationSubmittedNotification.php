<?php

namespace App\Notifications;

use App\Models\FormSubmission;
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

    public function __construct(private readonly FormSubmission $report) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Hasil evaluasi monev tersedia')
            ->line('Reviewer sudah menyelesaikan penilaian atas laporan monev Anda.')
            ->line('Skor dan catatannya dapat dibaca sekarang.');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monev_evaluation_submitted',
            'form_submission_id' => $this->report->id,
            'title' => 'Hasil evaluasi monev tersedia',
            'message' => 'Reviewer sudah menyelesaikan penilaian atas laporan monev Anda.',
        ];
    }
}
