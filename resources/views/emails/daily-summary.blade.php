@php
    $event = $summary['event']['short_name'] ?? $summary['event']['name'] ?? 'NAQLA LMS';
    $date = \Carbon\Carbon::parse($summary['date'])->format('D, d M Y');
    $y = $summary['yesterday'];
    $t = $summary['glance'];
    $yDate = \Carbon\Carbon::parse($y['date'])->format('D j M');
    $h2 = 'font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#5b6472;border-bottom:1px solid #e3e6ea;padding-bottom:6px;margin:24px 0 10px;';
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $summary['subject'] }}</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2430;">
<div style="max-width:720px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ea;border-radius:10px;padding:24px;">
    <div style="margin-bottom:18px;">
        <span style="background:#8A1538;color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:5px;">LMS</span>
        <span style="font-size:13px;color:#5b6472;margin-left:8px;">{{ $event }} · Daily Email</span>
    </div>

    <h1 style="font-size:22px;margin:0 0 4px;">{{ $date }}</h1>

    @if ($summary['attentionTotal'] > 0)
        <p style="font-size:14px;margin:0 0 4px;color:#b45309;font-weight:700;">⚠ {{ $summary['attentionTotal'] }} {{ $summary['attentionTotal'] === 1 ? 'item needs' : 'items need' }} attention today or tomorrow</p>
    @else
        <p style="font-size:14px;margin:0 0 4px;color:#15803d;font-weight:700;">✓ Nothing needs attention today or tomorrow</p>
    @endif

    @foreach ($summary['attention'] as $block)
        <div style="border:1px solid #f3d9a8;background:#fffaf0;border-radius:8px;padding:12px 14px;margin-top:12px;">
            <div style="font-size:14px;font-weight:700;margin-bottom:6px;">{{ $block['title'] }} <span style="color:#b45309;">({{ $block['count'] }})</span></div>
            <ul style="margin:0 0 8px;padding-left:18px;font-size:13px;color:#1f2430;">
                @foreach ($block['rows'] as $row)
                    <li style="margin-bottom:2px;">{{ $row }}</li>
                @endforeach
                @if ($block['count'] > count($block['rows']))
                    <li style="color:#5b6472;">and {{ $block['count'] - count($block['rows']) }} more</li>
                @endif
            </ul>
            <a href="{{ $block['link'] }}" style="font-size:13px;font-weight:700;color:#8A1538;">{{ $block['linkLabel'] }} →</a>
        </div>
    @endforeach

    <h2 style="{{ $h2 }}">Yesterday ({{ $yDate }})</h2>
    <p style="font-size:13px;margin:0;line-height:1.6;">
        <strong>{{ $y['jobsCompleted'] }} of {{ $y['jobsPlanned'] }}</strong> jobs completed ·
        on time <strong>{{ $y['onTimePct'] !== null ? $y['onTimePct'].'%' : '—' }}</strong>@if ($y['judged']) <span style="color:#5b6472;">({{ $y['judged'] }} checkpoints judged)</span>@endif ·
        avg delay <strong>{{ $y['avgDelay'] !== null ? $y['avgDelay'].' min' : '—' }}</strong> ·
        {{ $y['skipped'] }} checkpoint{{ $y['skipped'] === 1 ? '' : 's' }} skipped ·
        issues {{ $y['issuesRaised'] }} raised / {{ $y['issuesResolved'] }} resolved
    </p>

    <h2 style="{{ $h2 }}">Today at a glance</h2>
    <p style="font-size:13px;margin:0;line-height:1.6;">
        @if ($t['movements'] > 0)
            <strong>{{ $t['movements'] }}</strong> movement{{ $t['movements'] === 1 ? '' : 's' }}, first at <strong>{{ $t['first'] }}</strong>, last at <strong>{{ $t['last'] }}</strong>.
            Busiest hour {{ $t['busiestHour'] }} ({{ $t['busiestCount'] }}).
        @else
            No movements today.
        @endif
        <a href="{{ $t['link'] }}" style="color:#8A1538;font-weight:700;">Open the Day Board →</a>
    </p>

    @if (count($summary['schedule']))
        <h2 style="{{ $h2 }}">Today's movements</h2>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:12.5px;">
            <thead>
            <tr style="background:#f8f9fb;">
                <th align="left" style="padding:6px 8px;font-size:11px;color:#5b6472;">Time</th>
                <th align="left" style="padding:6px 8px;font-size:11px;color:#5b6472;">Team</th>
                <th align="left" style="padding:6px 8px;font-size:11px;color:#5b6472;">Route</th>
                <th align="left" style="padding:6px 8px;font-size:11px;color:#5b6472;">Vehicle</th>
                <th align="left" style="padding:6px 8px;font-size:11px;color:#5b6472;">Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($summary['schedule'] as $mv)
                <tr>
                    <td style="padding:7px 8px;border-bottom:1px solid #e3e6ea;white-space:nowrap;">{{ $mv['dep'] }}–{{ $mv['arr'] }}</td>
                    <td style="padding:7px 8px;border-bottom:1px solid #e3e6ea;">{{ $mv['code'] }}</td>
                    <td style="padding:7px 8px;border-bottom:1px solid #e3e6ea;">{{ $mv['from'] }} &rarr; {{ $mv['to'] }}</td>
                    <td style="padding:7px 8px;border-bottom:1px solid #e3e6ea;">{{ $mv['vehicle'] }}</td>
                    <td style="padding:7px 8px;border-bottom:1px solid #e3e6ea;">{{ $mv['status'] }}@if ($mv['delay']) (+{{ $mv['delay'] }} min)@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif

    <p style="margin-top:24px;padding-top:16px;border-top:1px solid #e3e6ea;font-size:11px;color:#8b93a1;text-align:center;">
        Generated by NAQLA LMS · {{ $date }} · Confidential
    </p>
</div>
</body>
</html>
