<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NotificationDigestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $userName,
        public int $totalCount,
        public array $byType,
        public string $period,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'CEMS-MY: Notification Digest',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.notification-digest',
        );
    }

    /**
     * Digest recipients are stored keyed by email address so the bulk sender
     * can audit exactly which addresses received each digest.
     *
     * @param  array<string, string|null>|string  $address
     * @param  string|null  $name
     */
    public function to($address, $name = null)
    {
        $this->to = is_array($address) ? $address : [$address => $name];

        return $this;
    }

    protected function buildRecipients($message)
    {
        foreach ($this->to as $email => $name) {
            $message->to($email, $name);
        }

        return $this;
    }
}
