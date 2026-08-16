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
 * @deprecated Mailable buatan tangan ini dipakai satu-satunya oleh
 * `App\Services\EmailNotificationService`, yang sendiri sudah `@deprecated`.
 * Pola penggantinya adalah Laravel Notification satu class dua channel
 * (`database` + `mail`) lewat method `toMail()` — lihat
 * `App\Notifications\ProposalSubmittedNotification` sebagai contoh. Jangan
 * tambah pemanggil baru ke class ini.
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
