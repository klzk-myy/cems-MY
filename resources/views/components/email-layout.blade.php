<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $title ?? 'CEMS Notification' }}</title>
</head>
<body style="margin: 0; padding: 0; background-color: #f7f7f8; font-family: 'Geist', 'Inter', sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="max-width: 600px; margin: 0 auto; padding: 24px;">
        <tr>
            <td style="background-color: #ffffff; border-radius: 12px; padding: 32px; border: 1px solid #e5e5e5;">
                <h1 style="margin: 0 0 16px; font-size: 20px; font-weight: 700; color: #171717;">{{ $title ?? 'CEMS Notification' }}</h1>
                <div style="color: #171717; font-size: 14px; line-height: 1.6;">
                    {{ $slot }}
                </div>
                <hr style="margin: 24px 0; border: none; border-top: 1px solid #e5e5e5;">
                <p style="margin: 0; font-size: 12px; color: #6b6b6b;">This is an automated message from CEMS. Do not reply.</p>
            </td>
        </tr>
    </table>
</body>
</html>
