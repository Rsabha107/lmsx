<?php

namespace App\Ai\Tools\Concerns;

use App\Models\User;

/**
 * Every tool executes within the same HTTP request as the /ai/query POST
 * that triggered it, so the authenticated user and active-event session
 * value are read the same way any controller in this app already reads
 * them — no special context-passing plumbing needed.
 */
trait ResolvesRequestContext
{
    protected function currentUser(): ?User
    {
        return auth()->user();
    }

    protected function currentEventId(): ?int
    {
        $eventId = session('active_event_id');

        return $eventId ? (int) $eventId : null;
    }
}
