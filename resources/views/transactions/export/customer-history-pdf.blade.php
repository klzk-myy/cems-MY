<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Customer Transaction History</title>
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
        <h1>Customer Transaction History</h1>
        <div class="meta">
            <p>Customer: {{ $customer->full_name }} ({{ $customer->id_number_masked ?? 'N/A' }})</p>
            <p>Generated: {{ $generatedAt->format('Y-m-d H:i:s') }}</p>
            @if(!empty($filters))
                <p>Filters: {{ json_encode($filters) }}</p>
            @endif
        </div>
    </div>
    @if(!empty($data))
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Type</th>
                    <th>Currency</th>
                    <th>Amount</th>
                    <th>Rate</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data as $row)
                    <tr>
                        <td>{{ $row['date'] ?? $row['created_at'] ?? '' }}</td>
                        <td>{{ $row['type'] ?? '' }}</td>
                        <td>{{ $row['currency_code'] ?? '' }}</td>
                        <td>{{ $row['amount_foreign'] ?? '' }}</td>
                        <td>{{ $row['rate'] ?? '' }}</td>
                        <td>{{ $row['status'] ?? '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No transaction data available for this customer.</p>
    @endif
</body>
</html>