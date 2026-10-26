<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Tells an SC/LOC employee they now have access, and to sign in with their SC/LOC account. */
class AccessGrantedMail extends Mailable
{
    /**
     * @param  list<string>  $roles
     * @param  list<string>  $events
     */
    public function __construct(
        public User $user,
        public array $roles,
        public array $events,
        public string $grantedBy,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'NAQLA LMS · You have been given access');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.access-granted', with: ['loginUrl' => url('/login')]);
    }
}
