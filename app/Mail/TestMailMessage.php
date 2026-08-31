<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The admin console's "send test email" message — proof that the stored
 * SMTP settings actually deliver.
 */
class TestMailMessage extends Mailable
{
    use Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test email from your HRM admin console');
    }

    public function content(): Content
    {
        return new Content(htmlString: <<<'HTML'
            <p>This is a test message from your HRM admin console.</p>
            <p>If you received it, outbound email is configured correctly.</p>
            HTML);
    }
}
