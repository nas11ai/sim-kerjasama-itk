<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-REV-06 — putaran review selesai dengan catatan reviewer masih terbuka; peneliti utama
 * menerima semua catatan putaran itu sekaligus.
 */
class RevisionRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'RevisionRequested';

    public function __construct(private readonly FormSubmission $submission) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pengajuan perlu revisi | SIMPAS')
            ->line($this->message());
    }

    /**
     * W39 — tanpa `action_url`: tujuan "Buka" dihitung NotificationDestination saat diklik.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'revision_requested',
            title: 'Pengajuan perlu revisi',
            message: $this->message(),
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }

    private function message(): string
    {
        return "Penilai meminta revisi atas pengajuan #{$this->submission->id}. Baca catatan penilai lalu kirim ulang.";
    }
}
