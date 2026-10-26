<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Invites someone outside SC/LOC to create a NAQLA LMS account with a password. */
class UserInvitationMail extends Mailable
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $events
     */
    public function __construct(
        public UserInvitation $invitation,
        public string $link,
        public array $roles,
        public array $events,
        public string $inviter,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "NAQLA LMS · {$this->inviter} invited you to create your account");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.user-invitation');
    }
}
