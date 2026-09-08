<?php

namespace App\Notifications;

use App\Models\FormPhaseDetail;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * BR-FE-08, BR-FE-16 — peneliti diberi tahu bahwa aksesnya dibuka kembali.
 *
 * Perpanjangan yang tidak diberitahukan sama saja dengan tidak diberikan:
 * peneliti sudah menutup layar setelah ditolak, dan tidak punya alasan
 * mencoba lagi.
 *
 * Pesannya menyebut **sampai kapan**, tapi tidak menyebut **alasannya**.
 * Alasan hanya untuk Operator dan Admin (BR-FE-16).
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
            // FormSubmission tidak punya kolom judul — judulnya milik Form
            // yang diisi. `$submission->title` yang dipakai sebelumnya selalu
            // null, jadi barisnya selalu berbunyi "Pengajuan #id" dan tidak
            // pernah menolong peneliti yang punya beberapa pengajuan berjalan.
            ->line('Pengajuan: '.$this->submission->form->title.' (#'.$this->submission->id.')')
            ->line($this->deadlineLine());
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'phase_override_granted',
            'form_submission_id' => $this->submission->id,
            'form_phase_detail_id' => $this->detail->id,
            'expires_at' => $this->expiresAt?->toDateTimeString(),
            'title' => 'Perpanjangan akses diberikan',
            'message' => $this->deadlineLine(),
        ];
    }

    private function deadlineLine(): string
    {
        return $this->expiresAt === null
            ? 'Akses dibuka tanpa batas waktu tambahan.'
            : 'Akses berlaku sampai '.$this->expiresAt->translatedFormat('d F Y H:i').'.';
    }
}
