<p>Hello {{ $userName }},</p>

<p>You have <strong>{{ $totalCount }}</strong> unread notification(s) from the past {{ $period }}.</p>

<table style="border-collapse: collapse; width: 100%; max-width: 400px;">
    <tr>
        <th style="text-align: left; padding: 8px; border-bottom: 1px solid #ddd;">Type</th>
        <th style="text-align: right; padding: 8px; border-bottom: 1px solid #ddd;">Count</th>
    </tr>
    @foreach($byType as $type => $count)
    <tr>
        <td style="padding: 8px; border-bottom: 1px solid #eee;">{{ $type }}</td>
        <td style="text-align: right; padding: 8px; border-bottom: 1px solid #eee;">{{ $count }}</td>
    </tr>
    @endforeach
</table>

<p>Log in to CEMS-MY to view and manage your notifications.</p>

<p>Regards,<br>CEMS-MY</p>