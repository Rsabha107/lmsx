<?php

namespace App\Mail;

use App\Models\Deletion;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells the other admins that a delete waits for their approval (or that the mass-delete brake tripped). */
class DeletionRequestMail extends Mailable
{
    public function __construct(public Deletion $deletion, public ?string $why = null)
    {
    }

    public function envelope(): Envelope
    {
        $what = Deletion::label($this->deletion->kind, $this->deletion->item_count);
        $subject = $this->deletion->trigger === 'brake'
            ? "Mass-delete brake: {$this->deletion->requester_name} wants to delete {$what}"
            : "Approval needed: {$this->deletion->requester_name} wants to delete {$what}";

        return new Envelope(subject: "NAQLA LMS · {$subject}");
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.deletion-request',
            with: [
                'deletion' => $this->deletion,
                'what' => Deletion::label($this->deletion->kind, $this->deletion->item_count),
                'why' => $this->why,
                'labels' => array_slice($this->deletion->summary['labels'] ?? [], 0, 15),
                'url' => url('/setups/recycle-bin'),
            ],
        );
    }
}
