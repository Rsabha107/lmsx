<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailySummaryMail extends Mailable
{
    use SerializesModels;

    /**
     * @param  array  $summary  Output of DailySummaryService::build().
     */
    public function __construct(public array $summary)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->summary['subject']);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-summary',
            with: ['summary' => $this->summary],
        );
    }
}
