<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Enums\DeadlineType;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use App\Queries\Monev\MonevCycles;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-MON-10 — siklus monev dibuka: satu pesan, dua penerima yang menunggu hal
 * beda — peneliti mengisi laporan, reviewer menilainya. Tenggatnya disebut sebab
 * itulah yang membuat pesan ini berguna; jadwal monev bulanan.
 */
class MonevStageOpenedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'MonevStageOpened';

    public function __construct(
        private readonly FormSubmission $parent,
        private readonly FormPhaseDetail $detail,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Siklus monev dibuka')
            ->line('Siklus monev untuk pengajuan #'.$this->parent->id.' sudah dibuka.')
            ->line($this->deadlineLine());
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'monev_stage_opened',
            title: 'Siklus monev dibuka',
            message: $this->deadlineLine(),
            context: [
                'form_submission_id' => $this->parent->id,
                'form_phase_detail_id' => $this->detail->id,
            ],
        ))->toArray();
    }

    /** BR-FE-26 — tanggal siklus di kalender periode induk. */
    private function deadlineLine(): string
    {
        $deadline = $this->parent->submissionPeriod?->deadlineFor($this->detail, DeadlineType::Submit);

        if ($deadline === null) {
            return 'Jadwal '.MonevCycles::nameOf($this->detail).' belum diatur di periode ini. Hubungi Operator LPPM.';
        }

        return 'Batas waktu siklus ini: '.$deadline->datetime->translatedFormat('d F Y H:i').'.';
    }
}
