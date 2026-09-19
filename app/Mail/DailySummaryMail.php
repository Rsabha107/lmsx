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
        $event = $this->summary['event']['short_name'] ?? $this->summary['event']['name'] ?? 'NAQLA LMS';

        return new Envelope(
            subject: "Logistics Snapshot · {$event} · ".$this->summary['date'],
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-summary',
            with: ['summary' => $this->summary],
        );
    }
}
