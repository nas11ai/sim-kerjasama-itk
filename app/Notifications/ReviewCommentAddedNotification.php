<?php

namespace App\Notifications;

use App\Data\Contract\NotificationPayload;
use App\Models\ReviewComment;
use App\Notifications\Concerns\UsesConfiguredChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Komentar atau balasan baru pada ulir diskusi review. */
class ReviewCommentAddedNotification extends Notification implements ShouldQueue
{
    use Queueable;
    use UsesConfiguredChannels;

    /** BR-NOT-06 — kunci deklarasi channel di notification_channel_settings. */
    public const EVENT_TYPE = 'ReviewCommentAdded';

    public function __construct(
        private readonly ReviewComment $comment,
        private readonly int $submissionId,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Komentar baru pada ulir review | SIMPAS')
            ->line($this->message());
    }

    /** @return array<string, int|string|null> */
    public function toArray(object $notifiable): array
    {
        return (new NotificationPayload(
            type: 'review_comment_added',
            title: 'Komentar baru pada ulir review',
            message: $this->message(),
            context: [
                'form_submission_id' => $this->submissionId,
                'review_summary_id' => $this->comment->review_summary_id,
                'review_comment_id' => $this->comment->id,
            ],
        ))->toArray();
    }

    private function message(): string
    {
        $kind = $this->comment->parent_comment_id === null ? 'Komentar' : 'Balasan';

        return "{$kind} baru masuk pada ulir review atas pengajuan #{$this->submissionId}.";
    }
}
