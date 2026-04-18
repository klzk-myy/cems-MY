<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('subject', 'CEMS-MY Notification')</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            line-height: 1.6;
            color: #171717;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f7f7f8;
        }
        .email-header {
            background-color: #0a0a0a;
            color: white;
            padding: 24px;
            border-radius: 12px 12px 0 0;
            text-align: center;
        }
        .email-header .logo {
            font-size: 24px;
            font-weight: bold;
            color: #D4AF37;
        }
        .email-body {
            background-color: #ffffff;
            padding: 32px;
            border-radius: 0 0 12px 12px;
            border: 1px solid #e5e5e5;
        }
        .email-footer {
            text-align: center;
            padding: 20px;
            color: #6b6b6b;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0a0a0a;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 500;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin: 16px 0;
        }
        .alert-danger {
            background-color: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }
        .alert-success {
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #22c55e;
        }
        .alert-warning {
            background-color: #fffbeb;
            border: 1px solid #fde68a;
            color: #f59e0b;
        }
    </style>
</head>
<body>
    <div class="email-header">
        <div class="logo">CEMS-MY</div>
        <p style="margin: 8px 0 0; opacity: 0.8; font-size: 14px;">Currency Exchange Management System</p>
    </div>
    <div class="email-body">
        @yield('content')
    </div>
    <div class="email-footer">
        <p>CEMS-MY v1.0 - Bank Negara Malaysia Compliant MSB Management System</p>
        <p style="margin-top: 8px;">This is an automated notification. Please do not reply to this email.</p>
    </div>
</body>
</html>
