<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Delete request</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2430;">
<div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ea;border-radius:10px;padding:24px;">
    <div style="margin-bottom:20px;">
        <span style="background:#8A1538;color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:5px;">LMS</span>
        <span style="font-size:13px;color:#5b6472;margin-left:8px;">NAQLA · Recycle bin</span>
    </div>

    <h1 style="font-size:20px;margin:0 0 6px;">
        @if ($deletion->trigger === 'brake') Mass-delete brake @else Approval needed @endif
    </h1>
    <p style="font-size:14px;margin:0 0 16px;">
        <strong>{{ $deletion->requester_name }}</strong> wants to delete <strong>{{ $what }}</strong>@if ($deletion->event) in {{ $deletion->event->name }}@endif.
    </p>

    @if ($why)
        <p style="font-size:13px;color:#5b6472;margin:0 0 12px;">Why it needs a second admin: {{ $why }}.</p>
    @endif
    @if ($deletion->reason)
        <p style="font-size:13px;margin:0 0 12px;">Reason given: “{{ $deletion->reason }}”</p>
    @endif

    @if ($labels)
        <ul style="font-size:13px;color:#1f2430;padding-left:18px;margin:0 0 16px;">
            @foreach ($labels as $label)
                <li>{{ $label }}</li>
            @endforeach
            @if ($deletion->item_count > count($labels))
                <li style="color:#5b6472;">and {{ $deletion->item_count - count($labels) }} more</li>
            @endif
        </ul>
    @endif

    <p style="margin:0 0 20px;">
        <a href="{{ $url }}" style="display:inline-block;background:#8A1538;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:10px 18px;border-radius:7px;">Review in the recycle bin</a>
    </p>
    <p style="font-size:12px;color:#5b6472;margin:0;">
        Nothing is deleted until another admin approves. The request expires on {{ $deletion->expires_at?->format('D, d M Y H:i') }}.
    </p>
</div>
</body>
</html>
