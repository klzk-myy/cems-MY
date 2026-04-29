<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Audit Log Export</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #171717; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border: 1px solid #e5e5e5; padding: 6px 8px; text-align: left; }
        th { background: #f5f5f5; font-weight: 600; font-size: 10px; text-transform: uppercase; }
        h1 { font-size: 18px; margin-bottom: 4px; }
        .meta { color: #6b6b6b; font-size: 10px; margin-bottom: 20px; }
        .header { border-bottom: 2px solid #171717; padding-bottom: 12px; margin-bottom: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Audit Log</h1>
        <div class="meta">
            <p>Date Range: {{ $dateFrom }} to {{ $dateTo }}</p>
            <p>Generated: {{ now()->format('Y-m-d H:i:s') }}</p>
        </div>
    </div>
    @if($logs->isNotEmpty())
        <table>
            <thead>
                <tr>
                    <th>Date/Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                @foreach($logs as $log)
                    <tr>
                        <td>{{ $log->created_at ?? '' }}</td>
                        <td>{{ $log->user_id ?? $log->user?->name ?? '' }}</td>
                        <td>{{ $log->action ?? $log->event ?? '' }}</td>
                        <td>{{ is_string($log->details ?? $log->description ?? '') ? Str::limit($log->details ?? $log->description ?? '', 80) : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No audit log entries found for this date range.</p>
    @endif
</body>
</html>