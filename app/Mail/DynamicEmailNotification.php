<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;

class DynamicEmailNotification extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    protected $data;

    /**
     * Create a new message instance.
     */
    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /**
     * Build the message.
     */
    public function build()
    {
        $view = $this->data['view'] ?? 'emails.template';
        $subject = $this->data['subject'] ?? 'Notifikasi';
        $params = $this->data['params'] ?? [];

        return $this->subject($subject)
            ->view($view)
            ->with($params);
    }
}
