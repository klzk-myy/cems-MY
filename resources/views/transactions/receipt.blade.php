<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #{{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
            background: #fff;
        }
        .receipt {
            width: 226px;
            margin: 0 auto;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 1px dashed #000;
            padding-bottom: 8px;
            margin-bottom: 8px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .company-reg {
            font-size: 8px;
            margin-bottom: 4px;
        }
        .receipt-title {
            font-size: 12px;
            font-weight: bold;
            margin: 6px 0;
        }
        .info-block {
            margin-bottom: 8px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .info-label {
            font-weight: normal;
        }
        .info-value {
            font-weight: bold;
            text-align: right;
        }
        .divider {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        .amount-block {
            margin: 8px 0;
        }
        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3px;
        }
        .amount-label {
            font-size: 10px;
        }
        .amount-value {
            font-weight: bold;
            text-align: right;
        }
        .total-row {
            display: flex;
            justify-content: space-between;
            font-size: 12px;
            font-weight: bold;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            padding: 4px 0;
            margin: 4px 0;
        }
        .customer-block {
            margin: 8px 0;
            padding: 6px;
            border: 1px solid #ccc;
            font-size: 9px;
        }
        .customer-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2px;
        }
        .barcode-section {
            text-align: center;
            margin: 10px 0;
            padding: 8px;
            border-top: 1px dashed #000;
            border-bottom: 1px dashed #000;
        }
        .barcode-section img {
            max-width: 180px;
            height: auto;
        }
        .barcode-text {
            font-family: 'Courier New', monospace;
            font-size: 10px;
            letter-spacing: 2px;
            margin-top: 4px;
        }
        .qr-section {
            text-align: center;
            margin: 8px 0;
        }
        .qr-section img {
            max-width: 80px;
            height: auto;
        }
        .footer {
            text-align: center;
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px dashed #000;
            font-size: 8px;
        }
        .footer-text {
            margin-bottom: 4px;
        }
        .status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
        }
        .status-completed {
            background: #d4edda;
            color: #155724;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="receipt">
        <div class="header">
            <div class="company-name">CEMS Currency Exchange</div>
            <div class="company-reg">Company Reg: 123456789</div>
            <div class="company-reg">Bank Negara Malaysia MSB License</div>
            <div class="receipt-title">TRANSACTION RECEIPT</div>
            <div class="receipt-number">No: {{ str_pad($transaction->id, 8, '0', STR_PAD_LEFT) }}</div>
        </div>

        <div class="info-block">
            <div class="info-row">
                <span class="info-label">Date:</span>
                <span class="info-value">{{ $transaction->created_at->format('d/m/Y') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Time:</span>
                <span class="info-value">{{ $transaction->created_at->format('H:i:s') }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Counter:</span>
                <span class="info-value">{{ $transaction->counter->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Branch:</span>
                <span class="info-value">{{ $transaction->branch->name ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Staff:</span>
                <span class="info-value">{{ $transaction->creator->username ?? 'N/A' }}</span>
            </div>
            <div class="info-row">
                <span class="info-label">Status:</span>
                <span class="status status-completed">COMPLETED</span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="amount-block">
            <div class="amount-row">
                <span class="amount-label">Transaction Type:</span>
                <span class="amount-value">{{ strtoupper($transaction->type->value) }}</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Currency:</span>
                <span class="amount-value">{{ $transaction->currency_code }}</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Foreign Amount:</span>
                <span class="amount-value">{{ number_format($transaction->amount_foreign, 2) }} {{ $transaction->currency_code }}</span>
            </div>
            <div class="amount-row">
                <span class="amount-label">Exchange Rate:</span>
                <span class="amount-value">{{ number_format($transaction->rate, 4) }}</span>
            </div>
        </div>

        <div class="divider"></div>

        <div class="total-row">
            <span>TOTAL (MYR)</span>
            <span>{{ number_format($transaction->amount_local, 2) }}</span>
        </div>

        <div class="customer-block">
            <div class="customer-row">
                <span>Customer:</span>
                <span>{{ $transaction->customer->full_name ?? 'N/A' }}</span>
            </div>
            <div class="customer-row">
                <span>ID Type:</span>
                <span>{{ $transaction->customer->id_type ?? 'N/A' }}</span>
            </div>
            <div class="customer-row">
                <span>Purpose:</span>
                <span>{{ $transaction->purpose ?? 'N/A' }}</span>
            </div>
            <div class="customer-row">
                <span>Source of Funds:</span>
                <span>{{ $transaction->source_of_funds ?? 'N/A' }}</span>
            </div>
        </div>

        @if($barcodeImage)
        <div class="barcode-section">
            <img src="{{ $barcodeImage }}" alt="Barcode">
            <div class="barcode-text">{{ $barcodeText }}</div>
        </div>
        @endif

        @if($qrCodeImage)
        <div class="qr-section">
            <img src="{{ $qrCodeImage }}" alt="QR Code">
            <div style="font-size: 7px; margin-top: 4px;">Scan to verify</div>
        </div>
        @endif

        <div class="footer">
            <div class="footer-text">Thank you for your transaction</div>
            <div class="footer-text">Please retain this receipt for your records</div>
            <div class="footer-text" style="margin-top: 4px;">Generated: {{ now()->format('d/m/Y H:i:s') }}</div>
        </div>
    </div>
</body>
</html>
