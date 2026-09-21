<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Receipt {{ $transaction->reference }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; font-size: 9px; color: #000; line-height: 1.4; padding: 8px; }
        .center { text-align: center; }
        .header { text-align: center; margin-bottom: 8px; }
        .header h1 { font-size: 13px; font-weight: 700; }
        .header .subtitle { font-size: 8px; color: #444; }
        .divider { border-top: 1px dashed #000; margin: 6px 0; }
        table.details { width: 100%; border-collapse: collapse; }
        table.details td { padding: 1px 0; vertical-align: top; }
        table.details td.label { color: #444; }
        table.details td.value { text-align: right; font-weight: 600; }
        .amount-row td { font-size: 11px; font-weight: 700; padding-top: 4px; }
        .reference { font-size: 11px; font-weight: 700; letter-spacing: 1px; }
        .barcode, .qr { text-align: center; margin: 8px 0; }
        .barcode img { width: 160px; }
        .qr img { width: 90px; }
        .barcode-text { font-size: 8px; letter-spacing: 2px; }
        .footer { margin-top: 8px; font-size: 7px; color: #444; text-align: center; }
        .status { display: inline-block; border: 1px solid #000; padding: 1px 6px; font-weight: 700; text-transform: uppercase; font-size: 8px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ config('app.name') }}</h1>
        <div class="subtitle">Currency Exchange Transaction Receipt</div>
        @if($transaction->branch)
            <div class="subtitle">{{ $transaction->branch->name }}</div>
        @endif
    </div>

    <div class="center reference">{{ $transaction->reference }}</div>

    <div class="divider"></div>

    <table class="details">
        <tr>
            <td class="label">Date / Time</td>
            <td class="value">{{ $transaction->created_at->format('d M Y H:i') }}</td>
        </tr>
        <tr>
            <td class="label">Teller</td>
            <td class="value">{{ $transaction->user->username ?? '-' }}</td>
        </tr>
        @if($transaction->till_id)
            <tr>
                <td class="label">Till</td>
                <td class="value">{{ $transaction->till_id }}</td>
            </tr>
        @endif
        <tr>
            <td class="label">Customer</td>
            <td class="value">{{ $transaction->customer->full_name ?? '-' }}</td>
        </tr>
    </table>

    <div class="divider"></div>

    <table class="details">
        <tr>
            <td class="label">Type</td>
            <td class="value">{{ $transaction->type->label() }}</td>
        </tr>
        <tr>
            <td class="label">Currency</td>
            <td class="value">{{ $transaction->currency_code }}</td>
        </tr>
        <tr>
            <td class="label">Quantity</td>
            <td class="value">{{ number_format((float) $transaction->quantity, 2) }} {{ $transaction->currency_code }}</td>
        </tr>
        <tr>
            <td class="label">Rate</td>
            <td class="value">{{ number_format((float) $transaction->rate, 6) }}</td>
        </tr>
        <tr class="amount-row">
            <td class="label">Total (MYR)</td>
            <td class="value">RM {{ number_format((float) $transaction->amount_myr, 2) }}</td>
        </tr>
        @if($transaction->purpose)
            <tr>
                <td class="label">Purpose</td>
                <td class="value">{{ $transaction->purpose }}</td>
            </tr>
        @endif
    </table>

    <div class="divider"></div>

    <div class="center"><span class="status">{{ $transaction->status->value }}</span></div>

    @if($barcodeImage)
        <div class="barcode">
            <img src="{{ $barcodeImage }}" alt="barcode">
            <div class="barcode-text">{{ $barcodeText }}</div>
        </div>
    @endif

    @if($qrCodeImage)
        <div class="qr">
            <img src="{{ $qrCodeImage }}" alt="verification QR">
            <div class="barcode-text">Scan to verify</div>
        </div>
    @endif

    <div class="divider"></div>

    <div class="footer">
        <div>Thank you for your business.</div>
        <div>Verify this receipt at {{ route('verification.transaction', ['reference' => $transaction->reference]) }}</div>
        <div>Printed {{ now()->format('d M Y H:i:s') }}</div>
    </div>
</body>
</html>
