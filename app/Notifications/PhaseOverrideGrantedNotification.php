<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * BR-FE-08, BR-FE-16 — akses dibuka kembali diberitahukan; sebab tanpa itu
 * peneliti yang sudah menutup layar tak lagi coba masuk. Pesan sebut sampai kapan,
 * bukan alasannya — itu hanya untuk Operator/Admin (BR-FE-16).
 */
class PhaseOverrideGrantedNotification extends Notification
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'PhaseOverrideGranted';

    public function __construct(
        private readonly FormSubmission $submission,
        private readonly FormPhaseDetail $detail,
        private readonly ?Carbon $expiresAt,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Perpanjangan akses diberikan')
            ->line('Operator LPPM membuka kembali akses Anda ke sebuah fase pengajuan.')
            // FormSubmission tidak punya kolom judul — itu milik Form. Kode lama
            // pakai `$submission->title`, yang selalu null, jadi baris ini selalu
            // "Pengajuan #id", tak menolong peneliti berpengajuan majemuk.
            ->line('Pengajuan: '.$this->submission->displayTitle().' (#'.$this->submission->id.')')
            ->line($this->deadlineLine());
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'phase_override_granted',
            title: 'Perpanjangan akses diberikan',
            message: $this->deadlineLine(),
            context: [
                'form_submission_id' => $this->submission->id,
                'form_phase_detail_id' => $this->detail->id,
                'expires_at' => $this->expiresAt?->toDateTimeString(),
            ],
        ))->toArray();
    }

    private function deadlineLine(): string
    {
        return $this->expiresAt === null
            ? 'Akses dibuka tanpa batas waktu tambahan.'
            : 'Akses berlaku sampai '.$this->expiresAt->translatedFormat('d F Y H:i').'.';
    }
}
