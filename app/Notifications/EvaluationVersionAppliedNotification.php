<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\FormSubmission;
use App\Models\ReviewEvaluationForm;
use App\Models\ReviewEvaluationFormVersion;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BR-REV-19 — Operator menerapkan versi baru Formulir Evaluasi ke angkatan reviewer ini; penilaiannya
 * dibuka ulang dan harus diisi lagi.
 */
class EvaluationVersionAppliedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'EvaluationVersionApplied';

    public function __construct(
        private readonly FormSubmission $submission,
        private readonly ReviewEvaluationForm $form,
        private readonly ReviewEvaluationFormVersion $version,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Penilaian perlu diisi ulang | SIMPAS')
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
            type: 'evaluation_version_applied',
            title: 'Penilaian perlu diisi ulang',
            message: $this->message(),
            context: ['form_submission_id' => $this->submission->id],
        ))->toArray();
    }

    private function message(): string
    {
        return "Lembar \"{$this->form->title}\" untuk pengajuan #{$this->submission->id} diganti ke {$this->version->label()}. "
            .'Isi ulang penilaian Anda; jawaban lama tetap tersimpan sebagai riwayat.';
    }
}
