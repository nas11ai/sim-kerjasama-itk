<?php

namespace App\Notifications;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-MON-10 — siklus monev dibuka.
 *
 * Satu pesan untuk dua penerima yang menunggu hal berbeda: peneliti menunggu
 * mengisi laporan, reviewer menunggu menilainya. Tenggatnya disebut karena
 * itulah yang membuat pesan ini berguna — jadwal monev berjarak bulanan.
 */
class MonevStageOpenedNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly FormSubmission $parent,
        private readonly FormPhaseDetail $detail,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Siklus monev dibuka')
            ->line('Siklus monev untuk pengajuan #'.$this->parent->id.' sudah dibuka.')
            ->line($this->deadlineLine());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'monev_stage_opened',
            'form_submission_id' => $this->parent->id,
            'form_phase_detail_id' => $this->detail->id,
            'title' => 'Siklus monev dibuka',
            'message' => $this->deadlineLine(),
        ];
    }

    private function deadlineLine(): string
    {
        return 'Batas waktu siklus ini: '
            .$this->detail->submissionDate->datetime->translatedFormat('d F Y H:i').'.';
    }
}
