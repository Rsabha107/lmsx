<?php

namespace App\Ai\Tools;

use Carbon\Carbon;

/** Parses a tool's from / to days (YYYY-MM-DD) into [start of from, start of the day after to]. */
final class DateRange
{
    /**
     * @return array{0: Carbon|string, 1: ?Carbon}  [error message, null] when invalid
     */
    public static function from(mixed $from, mixed $to, int $defaultDays, int $maxDays): array
    {
        $valid = fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && checkdate((int) substr($d, 5, 2), (int) substr($d, 8, 2), (int) substr($d, 0, 4));

        $from = trim((string) $from);
        $to = trim((string) $to);
        if (($from !== '' && ! $valid($from)) || ($to !== '' && ! $valid($to))) {
            return ['from and to must be valid YYYY-MM-DD dates.', null];
        }

        $start = $from !== '' ? Carbon::parse($from)->startOfDay() : now()->startOfDay();
        $end = $to !== '' ? Carbon::parse($to)->startOfDay()->addDay() : $start->copy()->addDays($defaultDays);

        if ($end <= $start) {
            return ['to must be on or after from.', null];
        }
        if ($start->diffInDays($end) > $maxDays) {
            return ["A range can cover at most {$maxDays} days.", null];
        }

        return [$start, $end];
    }
}
