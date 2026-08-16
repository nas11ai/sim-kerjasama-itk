<?php

namespace App\Notifications;

use App\Models\SubmissionDate;
use App\Models\SubmissionPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubmissionPeriodActiveNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly SubmissionPeriod $submissionPeriod) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Periode Submission Baru Aktif',
            'message' => "Periode submission '{$this->submissionPeriod->name}' telah aktif dari {$this->startDate()} hingga {$this->endDate()}.",
            'action_url' => route('user.dashboard'),
            'type' => 'info',
            'meta' => ['submission_period_id' => $this->submissionPeriod->id],
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Periode Submission Baru Aktif — SIMPAS')
            ->line("Periode submission '{$this->submissionPeriod->name}' telah dibuka.")
            ->line("Berlangsung dari {$this->startDate()} hingga {$this->endDate()}.")
            ->action('Buka Dashboard', route('user.dashboard'));
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
