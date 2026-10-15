<?php

namespace App\Models\Concerns;

use DateTimeInterface;

/**
 * Sends dates as the stored venue wall-clock digits with a "Z" label, the format the
 * frontend reads as text. Laravel's default would convert Qatar time to UTC first.
 */
trait SerializesWallClock
{
    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d\TH:i:s.u\Z');
    }
}
