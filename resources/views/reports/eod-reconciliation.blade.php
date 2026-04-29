<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EOD Reconciliation Report - {{ $date }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #333; line-height: 1.5; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #333; padding-bottom: 16px; }
        .header h1 { font-size: 20px; font-weight: 700; margin-bottom: 4px; }
        .header .subtitle { font-size: 14px; color: #666; }
        .meta-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px; }
        .meta-box { background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px; }
        .meta-box h3 { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #666; margin-bottom: 8px; }
        .meta-box .value { font-size: 14px; font-weight: 600; }
        .variance-alert { padding: 12px 16px; border-radius: 4px; margin-bottom: 24px; text-align: center; }
        .variance-alert.ok { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
        .variance-alert.warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; }
        .variance-alert.critical { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
        .variance-alert.minor { background: #ffeaa7; border: 1px solid #fdcb6e; color: #784019; }
        .summary-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 12px; margin-bottom: 24px; }
        .summary-card { background: #f8f8f8; border: 1px solid #e0e0e0; border-radius: 4px; padding: 12px; text-align: center; }
        .summary-card .label { font-size: 10px; font-weight: 600; text-transform: uppercase; color: #666; margin-bottom: 4px; }
        .summary-card .value { font-size: 18px; font-weight: 700; }
        .totals-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .totals-table th, .totals-table td { padding: 10px 12px; text-align: right; border-bottom: 1px solid #e0e0e0; }
        .totals-table th { background: #f8f8f8; font-weight: 600; text-align: left; }
        .totals-table td:first-child { text-align: left; }
        .totals-table tr.total-row { font-weight: 700; background: #f0f0f0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { padding: 8px 10px; text-align: left; border: 1px solid #e0e0e0; }
        th { background: #333; color: #fff; font-weight: 600; font-size: 10px; text-transform: uppercase; }
        tr:nth-child(even) { background: #f9f9f9; }
        .section-title { font-size: 14px; font-weight: 700; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #333; }
        .footer { margin-top: 32px; padding-top: 16px; border-top: 1px solid #ccc; font-size: 10px; color: #666; text-align: center; }
        .page-break { page-break-before: always; }
    </style>
</head>
<body>
    <div class="header">
        <h1>EOD Reconciliation Report</h1>
        <div class="subtitle">CEMS-MY Currency Exchange Management System</div>
        <div class="subtitle">Report Date: {{ $date }}</div>
    </div>

    <div class="meta-grid">
        <div class="meta-box">
            <h3>Report Information</h3>
            <div class="value">Generated: {{ $generatedAt }}</div>
            <div style="margin-top: 4px;">Branch: {{ $report['branch_name'] ?? 'All Branches' }}</div>
            <div style="margin-top: 4px;">Type: {{ strtoupper($report['report_type'] ?? 'daily') }} Report</div>
        </div>
        <div class="meta-box">
            <h3>Report Metadata</h3>
            <div class="value">{{ $report['report_metadata']['generated_by'] ?? 'System' }}</div>
            <div style="margin-top: 4px;">Version: {{ $report['report_metadata']['version'] ?? '1.0' }}</div>
            @if(isset($report['report_metadata']['counter_filter']))
                <div style="margin-top: 4px;">Counter ID: {{ $report['report_metadata']['counter_filter'] }}</div>
            @endif
        </div>
    </div>

    @php
        $varianceStatus = $report['variance_status'] ?? ['status' => 'ok', 'severity' => 'none'];
        $statusClass = match($varianceStatus['status'] ?? 'ok') {
            'critical' => 'critical',
            'warning' => 'warning',
            'minor' => 'minor',
            default => 'ok'
        };
        $varianceLabel = match($varianceStatus['status'] ?? 'ok') {
            'critical' => 'CRITICAL VARIANCE DETECTED',
            'warning' => 'VARIANCE WARNING',
            'minor' => 'Minor Variance',
            default => 'No Variance'
        };
    @endphp

    <div class="variance-alert {{ $statusClass }}">
        <strong>{{ $varianceLabel }}</strong>
        @if(isset($varianceStatus['variance_amount']))
            — Variance Amount: RM {{ number_format((float) $varianceStatus['variance_amount'], 2) }}
        @endif
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <div class="label">Total Counters</div>
            <div class="value">{{ $report['summary']['total_counters'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Active</div>
            <div class="value">{{ $report['summary']['active_counters'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Closed</div>
            <div class="value">{{ $report['summary']['closed_counters'] ?? 0 }}</div>
        </div>
        <div class="summary-card">
            <div class="label">Handed Over</div>
            <div class="value">{{ $report['summary']['handed_over_counters'] ?? 0 }}</div>
        </div>
    </div>

    <h2 class="section-title">Cash Flow Totals</h2>
    <table class="totals-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Amount (RM)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Opening Float</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['opening_float'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Cash Received</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['cash_received'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Cash Paid Out</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['cash_paid_out'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Closing Expected</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['closing_expected'] ?? 0), 2) }}</td>
            </tr>
            <tr>
                <td>Closing Actual</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['closing_actual'] ?? 0), 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>Variance</td>
                <td style="text-align: right;">{{ number_format((float) ($report['totals']['variance'] ?? 0), 2) }}</td>
            </tr>
        </tbody>
    </table>

    @if(!empty($report['counter_summaries']))
        <h2 class="section-title">Counter Details</h2>
        <table>
            <thead>
                <tr>
                    <th>Counter</th>
                    <th>Teller</th>
                    <th>Opening</th>
                    <th>Received</th>
                    <th>Paid Out</th>
                    <th>Expected</th>
                    <th>Actual</th>
                    <th>Variance</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['counter_summaries'] as $summary)
                    <tr>
                        <td>{{ $summary['counter_name'] ?? 'N/A' }}</td>
                        <td>{{ $summary['teller_name'] ?? 'N/A' }}</td>
                        <td>{{ number_format((float) ($summary['opening_float'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($summary['total_cash_received'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($summary['total_cash_paid_out'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($summary['closing_float_expected'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($summary['closing_float_actual'] ?? 0), 2) }}</td>
                        <td>{{ number_format((float) ($summary['variance'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($report['large_transactions']['transactions']) && $report['large_transactions']['count'] > 0)
        <div class="page-break"></div>
        <h2 class="section-title">Large Transactions ({{ $report['large_transactions']['count'] }})</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Amount (RM)</th>
                    <th>Currency</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['large_transactions']['transactions'] as $tx)
                    <tr>
                        <td>{{ $tx['id'] ?? 'N/A' }}</td>
                        <td>{{ $tx['customer_name'] ?? $tx['customer_id'] ?? 'N/A' }}</td>
                        <td>{{ $tx['type'] ?? 'N/A' }}</td>
                        <td>{{ number_format((float) ($tx['amount_local'] ?? 0), 2) }}</td>
                        <td>{{ $tx['currency_code'] ?? 'MYR' }}</td>
                        <td>{{ $tx['status'] ?? 'N/A' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    @if(!empty($report['flagged_transactions']['transactions']) && $report['flagged_transactions']['count'] > 0)
        <h2 class="section-title">Flagged Transactions ({{ $report['flagged_transactions']['count'] }})</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Flag Type</th>
                    <th>Status</th>
                    <th>Transaction ID</th>
                    <th>Amount (RM)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($report['flagged_transactions']['transactions'] as $flag)
                    <tr>
                        <td>{{ $flag['id'] ?? 'N/A' }}</td>
                        <td>{{ $flag['flag_type'] ?? 'N/A' }}</td>
                        <td>{{ $flag['status'] ?? 'N/A' }}</td>
                        <td>{{ $flag['transaction_id'] ?? 'N/A' }}</td>
                        <td>{{ number_format((float) ($flag['transaction']['amount_local'] ?? 0), 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="footer">
        <p>CEMS-MY EOD Reconciliation Report — Generated on {{ $generatedAt }}</p>
        <p>Confidential — For internal use only</p>
    </div>
</body>
</html>
