@php
    // Conflict texts end with " — 30 minutes overlap." style notes; shown next to the type.
    $split = function (string $text): array {
        $i = mb_strrpos($text, ' — ');

        return $i ? [mb_substr($text, 0, $i), rtrim(mb_substr($text, $i + 3), '.')] : [$text, null];
    };
    // "Wed 14 Oct 04:40" → ["Wed 14 Oct", "04:40"]
    $parts = fn (?string $v) => $v && preg_match('/^(.*\S)\s+(\d{2}:\d{2})$/', $v, $m) ? [$m[1], $m[2]] : [null, $v];
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Flight changes</title>
</head>
<body style="margin:0;padding:0;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2430;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;">
<tr><td align="center" style="padding:24px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px;background:#ffffff;border:1px solid #e3e6ea;border-radius:10px;">
    <tr><td style="padding:22px 24px 6px;">
        <span style="background:#8A1538;color:#ffffff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:5px;">LMS</span>
        <span style="font-size:13px;color:#5b6472;margin-left:8px;">NAQLA · Flight changes</span>
        <h1 style="font-size:20px;line-height:1.3;margin:16px 0 6px;">{{ ucfirst($counts) }}</h1>
        <p style="font-size:13px;line-height:1.5;margin:0 0 4px;color:#5b6472;">
            A flight's scheduled time changed under these jobs. Jobs not started yet were re-timed from the new time;
            started jobs kept their times. Their supervisors were notified.
        </p>
    </td></tr>

    @foreach ($flights as $group)
        <tr><td style="padding:14px 24px 0;">
            {{-- Flight header --}}
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f4f5f7;border-radius:8px 8px 0 0;border:1px solid #e3e6ea;border-bottom:none;">
                <tr><td style="padding:10px 14px;font-size:13px;">
                    <span style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#5b6472;">Flight</span>
                    &nbsp; <strong style="font-family:Consolas,Menlo,monospace;font-size:14px;">{{ $group['number'] }}</strong>
                    @if ($group['from'])
                        &nbsp; <span style="color:#8a919c;text-decoration:line-through;font-family:Consolas,Menlo,monospace;">{{ $group['from'] }}</span>
                        <span style="color:#8a919c;">&nbsp;&rarr;&nbsp;</span>
                        <strong style="font-family:Consolas,Menlo,monospace;">{{ $group['to'] }}</strong>
                        @if ($group['day'])<span style="color:#5b6472;font-size:12px;">&nbsp; {{ $group['day'] }}</span>@endif
                    @endif
                    @if ($group['shift'])
                        &nbsp;<span style="background:#DBEAFE;color:#1E40AF;font-size:11px;font-weight:700;padding:2px 8px;border-radius:999px;white-space:nowrap;">{{ $group['shift'] }}</span>
                    @endif
                </td></tr>
            </table>

            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e3e6ea;border-radius:0 0 8px 8px;">
            @foreach ($group['jobs'] as $job)
                @php
                    $retimed = $job['kind'] === 'retimed';
                    [$beforeDay, $beforeTime] = $parts($job['start_before']);
                    [$afterDay, $afterTime] = $parts($job['start_after']);
                    $sameDay = $beforeDay && $beforeDay === $afterDay;
                @endphp
                <tr><td style="padding:14px;{{ $loop->first ? '' : 'border-top:1px solid #e3e6ea;' }}">
                    {{-- Job + badge --}}
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                        <td style="font-size:14px;">
                            <a href="{{ $job['url'] }}" style="color:#8A1538;font-weight:700;text-decoration:none;font-family:Consolas,Menlo,monospace;">{{ $job['job_ref'] }}</a>
                            @if ($job['team'])<span style="color:#1f2430;"> &middot; {{ $job['team'] }}</span>@endif
                        </td>
                        <td align="right" style="white-space:nowrap;">
                            @if ($retimed)
                                <span style="background:#DBEAFE;color:#1E40AF;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;">Re-timed</span>
                            @else
                                <span style="background:#FEF3C7;color:#92400E;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;">Started · needs review</span>
                            @endif
                        </td>
                    </tr></table>

                    {{-- Start time --}}
                    <table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:10px;"><tr>
                        <td style="font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#8a919c;padding-right:12px;white-space:nowrap;">Job start</td>
                        <td style="font-size:14px;">
                            @if ($retimed)
                                <span style="color:#8a919c;text-decoration:line-through;font-family:Consolas,Menlo,monospace;">{{ $sameDay ? $beforeTime : ($job['start_before'] ?? '—') }}</span>
                                <span style="color:#8a919c;">&nbsp;&rarr;&nbsp;</span>
                                <strong style="font-family:Consolas,Menlo,monospace;">{{ $sameDay ? $afterTime : ($job['start_after'] ?? '—') }}</strong>
                                @if ($sameDay)<span style="color:#5b6472;font-size:12px;">&nbsp; {{ $afterDay }}</span>@endif
                            @else
                                <strong style="font-family:Consolas,Menlo,monospace;">{{ $job['start_before'] ?? '—' }}</strong>
                                <span style="color:#92400E;font-size:12px;font-weight:700;">&nbsp; kept: the job has started</span>
                            @endif
                        </td>
                    </tr></table>

                    {{-- Clashes --}}
                    @if ($job['clashes'])
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top:12px;background:#FFFBEB;border:1px solid #FCD34D;border-radius:8px;">
                            <tr><td style="padding:9px 12px 2px;font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#92400E;">Check at the new time</td></tr>
                            @foreach ($job['clashes'] as $clash)
                                @php
                                    $type = is_array($clash) ? ($clash['type'] ?? null) : null;
                                    [$main, $note] = $split(is_array($clash) ? (string) $clash['text'] : (string) $clash);
                                @endphp
                                <tr><td style="padding:8px 12px;{{ $loop->first ? '' : 'border-top:1px dashed #FCD34D;' }}">
                                    @if ($type || $note)
                                        <div style="margin-bottom:4px;">
                                            @if ($type)<span style="background:#FDE68A;color:#78350F;font-size:11px;font-weight:700;padding:2px 7px;border-radius:4px;">{{ $type }}</span>@endif
                                            @if ($note)<span style="color:#B91C1C;font-size:12px;font-weight:700;">&nbsp; {{ $note }}</span>@endif
                                        </div>
                                    @endif
                                    <div style="font-size:13px;line-height:1.5;color:#1f2430;">{{ $main }}</div>
                                </td></tr>
                            @endforeach
                        </table>
                    @elseif (! $retimed)
                        <p style="font-size:12.5px;color:#5b6472;margin:10px 0 0;">Check it, and adjust it from the Jobs Queue (More &rarr; Override) if needed.</p>
                    @endif

                    <p style="margin:12px 0 0;">
                        <a href="{{ $job['url'] }}" style="display:inline-block;border:1px solid #8A1538;color:#8A1538;text-decoration:none;font-size:13px;font-weight:700;padding:6px 14px;border-radius:6px;">Open {{ $job['job_ref'] }}</a>
                    </p>
                </td></tr>
            @endforeach
            </table>
        </td></tr>
    @endforeach

    <tr><td style="padding:18px 24px 22px;">
        <p style="font-size:12px;line-height:1.5;color:#8a919c;margin:0;">
            Lists jobs whose flight moved at least the minutes set in Settings &rarr; Conflict rules (Email on job re-time).
            Drivers are not notified by the app.
        </p>
    </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
