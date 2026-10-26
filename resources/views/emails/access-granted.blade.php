<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>NAQLA LMS access</title>
</head>
<body style="margin:0;padding:24px;background:#f4f5f7;font-family:Arial,Helvetica,sans-serif;color:#1f2430;">
<div style="max-width:620px;margin:0 auto;background:#ffffff;border:1px solid #e3e6ea;border-radius:10px;padding:24px;">
    <div style="margin-bottom:20px;">
        <span style="background:#8A1538;color:#fff;font-size:11px;font-weight:700;padding:3px 8px;border-radius:5px;">LMS</span>
        <span style="font-size:13px;color:#5b6472;margin-left:8px;">NAQLA · Logistics Management System</span>
    </div>

    <h1 style="font-size:20px;margin:0 0 6px;">You have access to NAQLA LMS</h1>
    <p style="font-size:14px;margin:0 0 16px;">
        Hello {{ $user->name }}, <strong>{{ $grantedBy }}</strong> gave you access to NAQLA LMS.
    </p>

    <table style="font-size:13px;border-collapse:collapse;margin:0 0 18px;">
        <tr>
            <td style="padding:3px 14px 3px 0;color:#5b6472;">Role</td>
            <td style="padding:3px 0;">{{ collect($roles)->map(fn ($r) => ucwords(str_replace('_', ' ', $r)))->join(', ') ?: '—' }}</td>
        </tr>
        <tr>
            <td style="padding:3px 14px 3px 0;color:#5b6472;">Events</td>
            <td style="padding:3px 0;">{{ $events ? implode(', ', $events) : 'All events' }}</td>
        </tr>
    </table>

    <p style="font-size:14px;margin:0 0 14px;">
        Sign in with <strong>Sign in with your SC/LOC account</strong>, using {{ $user->email }}. You don't need a separate password.
    </p>
    <p style="margin:0 0 14px;">
        <a href="{{ $loginUrl }}" style="display:inline-block;background:#8A1538;color:#ffffff;text-decoration:none;font-size:14px;font-weight:700;padding:10px 18px;border-radius:7px;">Open NAQLA LMS</a>
    </p>
    <p style="font-size:12px;color:#5b6472;margin:0;">{{ $loginUrl }}</p>
</div>
</body>
</html>
