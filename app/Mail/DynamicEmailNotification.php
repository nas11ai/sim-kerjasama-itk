<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * @deprecated Jangan tambah pemanggil baru. Satu-satunya pemanggilnya,
 * `App\Services\EmailNotificationService`, sendiri sudah usang. Penggantinya
 * Laravel Notification satu class dua channel (`database` + `mail`) lewat
 * `toMail()` — contohnya `App\Notifications\ProposalSubmittedNotification`.
 */
class DynamicEmailNotification extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public array $data
    ) {
        Log::info('DynamicEmailNotification constructed', [
            'subject' => $data['subject'] ?? 'N/A',
            'view' => $data['view'] ?? 'N/A',
        ]);
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->data['subject'] ?? 'Notification',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: $this->data['view'] ?? 'emails.template',
            with: $this->data['params'] ?? [],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
