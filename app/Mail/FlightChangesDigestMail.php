<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Every job one save, bulk refresh, import or scheduled sync moved enough to mention (FlightChangeDigest). */
class FlightChangesDigestMail extends Mailable
{
    /** @param  array<int, array<string, mixed>>  $items */
    public function __construct(public array $items) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'NAQLA LMS · Flight changes: '.$this->counts());
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.flight-changes-digest',
            with: [
                'counts' => $this->counts(),
                // Grouped by flight change, in the order they happened.
                'flights' => collect($this->items)->groupBy('flight')->map(fn ($jobs, $flight) => $this->flightParts($flight) + [
                    'flight' => $flight,
                    'shift' => $this->shiftText($jobs->first()['shift']),
                    'jobs' => $jobs->map(fn ($j) => $j + ['url' => url('/job/'.$j['job_ref'])])->values()->all(),
                ])->values()->all(),
            ],
        );
    }

    public function counts(): string
    {
        $retimed = count(array_filter($this->items, fn ($i) => $i['kind'] === 'retimed'));
        $review = count($this->items) - $retimed;

        return implode(', ', array_filter([
            $retimed ? $retimed.' '.($retimed === 1 ? 'job' : 'jobs').' re-timed' : null,
            $review ? $review.' started '.($review === 1 ? 'job needs' : 'jobs need').' review' : null,
        ]));
    }

    /** "QR893 Wed 14 Oct 04:40 → Wed 14 Oct 07:40" as number, from / to times and the day when both share it. */
    private function flightParts(string $label): array
    {
        $when = '(\w{3} \d{1,2} \w{3}) (\d{2}:\d{2})';
        if (! preg_match("/^(.*?)\\s*{$when} → {$when}$/u", $label, $m)) {
            return ['number' => $label, 'from' => null, 'to' => null, 'day' => null];
        }
        $same = $m[2] === $m[4];

        return [
            'number' => $m[1],
            'from' => $same ? $m[3] : "{$m[2]} {$m[3]}",
            'to' => $same ? $m[5] : "{$m[4]} {$m[5]}",
            'day' => $same ? $m[2] : null,
        ];
    }

    private function shiftText(?int $shift): string
    {
        if ($shift === null) {
            return '';
        }
        $m = abs($shift);
        $size = $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? sprintf(' %02dm', $m % 60) : '') : "{$m} min";

        return $size.' '.($shift < 0 ? 'earlier' : 'later');
    }
}
