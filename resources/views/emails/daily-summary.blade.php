@php
    // Table-only layout with fixed widths and inline styles: Outlook / Exchange render mail with
    // Word, which ignores max-width, div padding, border-radius and list styling.
    $event = $summary['event']['short_name'] ?? $summary['event']['name'] ?? 'NAQLA LMS';
    $date = \Carbon\Carbon::parse($summary['date'])->format('D, d M Y');
    $y = $summary['yesterday'];
    $t = $summary['glance'];
    $yDate = \Carbon\Carbon::parse($y['date'])->format('D j M');
    $font = 'font-family:Arial,Helvetica,sans-serif;';
    $section = $font.'font-size:11px;font-weight:bold;letter-spacing:1px;text-transform:uppercase;color:#5b6472;padding:22px 0 8px 0;border-bottom:1px solid #e3e6ea;';
    $total = $summary['attentionTotal'];
@endphp
<!DOCTYPE html>
<html lang="en" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="x-apple-disable-message-reformatting">
    <title>{{ $summary['subject'] }}</title>
    <!--[if mso]><style>table,td,th,p,a,span{font-family:Arial,Helvetica,sans-serif !important;}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background-color:#f4f5f7;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#f4f5f7" style="background-color:#f4f5f7;">
<tr><td align="center" style="padding:24px 12px;">

<!--[if mso]><table role="presentation" width="680" cellpadding="0" cellspacing="0" border="0"><tr><td><![endif]-->
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff"
       style="width:100%;max-width:680px;background-color:#ffffff;border:1px solid #e3e6ea;">
<tr><td style="padding:24px 28px;{{ $font }}color:#1f2430;">

    {{-- Header --}}
    <table role="presentation" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td bgcolor="#8A1538" style="background-color:#8A1538;padding:3px 7px;{{ $font }}font-size:11px;font-weight:bold;color:#ffffff;">LMS</td>
            <td style="padding-left:8px;{{ $font }}font-size:13px;color:#5b6472;">{{ $event }} &middot; Daily Email</td>
        </tr>
    </table>

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="padding:14px 0 4px 0;{{ $font }}font-size:22px;font-weight:bold;color:#1f2430;">{{ $date }}</td></tr>
        <tr><td style="padding:0 0 6px 0;{{ $font }}font-size:14px;font-weight:bold;color:{{ $total ? '#b45309' : '#15803d' }};">
            @if ($total)
                &#9888; {{ $total }} {{ $total === 1 ? 'item needs' : 'items need' }} attention today or tomorrow
            @else
                &#10003; Nothing needs attention today or tomorrow
            @endif
        </td></tr>
    </table>

    {{-- Needs attention: one boxed table per block --}}
    @foreach ($summary['attention'] as $block)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top:12px;">
            <tr><td bgcolor="#fffaf0" style="background-color:#fffaf0;border:1px solid #f3d9a8;border-left:4px solid #d97706;padding:12px 14px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr><td colspan="2" style="padding:0 0 8px 0;{{ $font }}font-size:14px;font-weight:bold;color:#1f2430;">
                        {{ $block['title'] }} <span style="color:#b45309;">({{ $block['count'] }})</span>
                    </td></tr>
                    @foreach ($block['rows'] as $row)
                        <tr>
                            <td width="14" valign="top" style="width:14px;padding:2px 0;{{ $font }}font-size:13px;line-height:18px;color:#b45309;">&bull;</td>
                            <td valign="top" style="padding:2px 0;{{ $font }}font-size:13px;line-height:18px;color:#1f2430;">{{ $row }}</td>
                        </tr>
                    @endforeach
                    @if ($block['count'] > count($block['rows']))
                        <tr>
                            <td width="14" style="width:14px;"></td>
                            <td style="padding:2px 0;{{ $font }}font-size:13px;line-height:18px;color:#5b6472;">and {{ $block['count'] - count($block['rows']) }} more</td>
                        </tr>
                    @endif
                    <tr><td colspan="2" style="padding:10px 0 0 0;{{ $font }}font-size:13px;">
                        <a href="{{ $block['link'] }}" style="color:#8A1538;font-weight:bold;text-decoration:none;">{{ $block['linkLabel'] }} &rarr;</a>
                    </td></tr>
                </table>
            </td></tr>
        </table>
    @endforeach

    {{-- Yesterday --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="{{ $section }}">Yesterday ({{ $yDate }})</td></tr>
        <tr><td style="padding:10px 0 0 0;{{ $font }}font-size:13px;line-height:20px;color:#1f2430;">
            <strong>{{ $y['jobsCompleted'] }} of {{ $y['jobsPlanned'] }}</strong> jobs completed &middot;
            on time <strong>{{ $y['onTimePct'] !== null ? $y['onTimePct'].'%' : '—' }}</strong>@if ($y['judged']) <span style="color:#5b6472;">({{ $y['judged'] }} checkpoints judged)</span>@endif &middot;
            avg delay <strong>{{ $y['avgDelay'] !== null ? $y['avgDelay'].' min' : '—' }}</strong> &middot;
            {{ $y['skipped'] }} checkpoint{{ $y['skipped'] === 1 ? '' : 's' }} skipped &middot;
            issues {{ $y['issuesRaised'] }} raised / {{ $y['issuesResolved'] }} resolved
        </td></tr>
    </table>

    {{-- Today at a glance --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="{{ $section }}">Today at a glance</td></tr>
        <tr><td style="padding:10px 0 0 0;{{ $font }}font-size:13px;line-height:20px;color:#1f2430;">
            @if ($t['movements'] > 0)
                <strong>{{ $t['movements'] }}</strong> movement{{ $t['movements'] === 1 ? '' : 's' }}, first at <strong>{{ $t['first'] }}</strong>, last at <strong>{{ $t['last'] }}</strong>.
                Busiest hour {{ $t['busiestHour'] }} ({{ $t['busiestCount'] }}).
            @else
                No movements today.
            @endif
            <a href="{{ $t['link'] }}" style="color:#8A1538;font-weight:bold;text-decoration:none;">Open the Day Board &rarr;</a>
        </td></tr>
    </table>

    {{-- Today's movements --}}
    @if (count($summary['schedule']))
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
            <tr><td style="{{ $section }}">Today's movements</td></tr>
        </table>
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;margin-top:8px;">
            <tr>
                @foreach (['Time' => 90, 'Team' => 70, 'Route' => 0, 'Vehicle' => 100, 'Status' => 100] as $head => $w)
                    <th align="left" bgcolor="#f8f9fb" @if ($w) width="{{ $w }}" @endif
                        style="background-color:#f8f9fb;padding:6px 8px;{{ $font }}font-size:11px;font-weight:bold;color:#5b6472;border-bottom:1px solid #e3e6ea;">{{ $head }}</th>
                @endforeach
            </tr>
            @foreach ($summary['schedule'] as $mv)
                @php $cell = 'padding:7px 8px;'.$font.'font-size:12px;color:#1f2430;border-bottom:1px solid #e3e6ea;'; @endphp
                <tr>
                    <td style="{{ $cell }}white-space:nowrap;">{{ $mv['dep'] }}&ndash;{{ $mv['arr'] }}</td>
                    <td style="{{ $cell }}">{{ $mv['code'] }}</td>
                    <td style="{{ $cell }}">{{ $mv['from'] }} &rarr; {{ $mv['to'] }}</td>
                    <td style="{{ $cell }}{{ $mv['vehicle'] === 'Unassigned' ? 'color:#9ca3af;' : '' }}">{{ $mv['vehicle'] }}</td>
                    <td style="{{ $cell }}{{ $mv['delay'] ? 'color:#b45309;' : '' }}">{{ ucfirst($mv['status']) }}@if ($mv['delay']) (+{{ $mv['delay'] }} min)@endif</td>
                </tr>
            @endforeach
        </table>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr><td style="padding:24px 0 0 0;font-size:1px;line-height:1px;">&nbsp;</td></tr>
        <tr><td align="center" style="padding:14px 0 0 0;border-top:1px solid #e3e6ea;{{ $font }}font-size:11px;color:#8b93a1;">
            Generated by NAQLA LMS &middot; {{ $date }} &middot; Confidential
        </td></tr>
    </table>

</td></tr>
</table>
<!--[if mso]></td></tr></table><![endif]-->

</td></tr>
</table>
</body>
</html>
