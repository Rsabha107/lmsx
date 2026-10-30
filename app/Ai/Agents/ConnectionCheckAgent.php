<?php

namespace App\Ai\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

/** One short round trip, used by Settings → AI provider → Test connection. */
class ConnectionCheckAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Reply with the single word OK.';
    }

    public function timeout(): int
    {
        return 20;
    }
}
