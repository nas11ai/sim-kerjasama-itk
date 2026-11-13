<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionPeriodActiveNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'SubmissionPeriodActive';

    public function __construct(private readonly SubmissionPeriod $submissionPeriod) {}

    /**
     * W39 — tanpa `action_url`: tujuan "Buka" dihitung NotificationDestination saat diklik.
     *
     * @return array<string, int|string|null>
     */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'submission_period_active',
            title: 'Periode Pengajuan Baru Aktif',
            message: "Periode pengajuan '{$this->submissionPeriod->name}' telah aktif dari {$this->startDate()} hingga {$this->endDate()}.",
            context: ['submission_period_id' => $this->submissionPeriod->id],
        ))->toArray();
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Periode Pengajuan Baru Aktif | SIMPAS')
            ->line("Periode pengajuan '{$this->submissionPeriod->name}' telah dibuka.")
            ->line("Berlangsung dari {$this->startDate()} hingga {$this->endDate()}.")
            ->action('Buka Beranda', route('dashboard'));
    }

    /** @return Collection<int, SubmissionDate> */
    private function sortedDates(): Collection
    {
        return $this->submissionPeriod->submissionDates->sortBy('datetime');
    }

    private function startDate(): string
    {
        $first = $this->sortedDates()->first();

        return $first ? $first->datetime->format('d F Y') : '-';
    }

    private function endDate(): string
    {
        $last = $this->sortedDates()->last();

        return $last ? $last->datetime->format('d F Y') : '-';
    }
}
