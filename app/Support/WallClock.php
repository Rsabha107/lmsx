<?php

namespace App\Support;

use DateTimeInterface;

/**
 * API dates keep the shape clients already parse: venue wall-clock digits labelled +00:00.
 * The mobile app drops the offset, so the digits are what it shows.
 */
final class WallClock
{
    public static function iso(?DateTimeInterface $date): ?string
    {
        return $date ? $date->format('Y-m-d\TH:i:s').'+00:00' : null;
    }
}
