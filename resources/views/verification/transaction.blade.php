<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Verification - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-canvas-subtle text-ink flex items-center justify-center">
    <div class="w-full max-w-md p-6">
        <x-card>
            <div class="p-8 space-y-6">
                <x-page-header title="Transaction Verification" description="{{ config('app.name') }}" />

                @if($verified)
                    <div class="p-4 rounded-lg border bg-green-50 border-green-200 text-green-800 text-sm">
                        This receipt reference matches a recorded transaction.
                    </div>

                    <dl class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Reference</dt>
                            <dd class="font-mono font-semibold">{{ $reference }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Status</dt>
                            <dd class="font-semibold">{{ $status ?? 'Unknown' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Transaction Date</dt>
                            <dd class="font-semibold">{{ $date ?? 'N/A' }}</dd>
                        </div>
                    </dl>
                @else
                    <div class="p-4 rounded-lg border bg-yellow-50 border-yellow-200 text-yellow-800 text-sm">
                        This reference could not be verified. Please check the QR code on your receipt or contact the branch that issued it.
                    </div>
                @endif

                <p class="text-xs text-ink-muted text-center">
                    For security reasons, only the verification status is shown. No personal or transaction amount details are disclosed here.
                </p>
            </div>
        </x-card>
    </div>
</body>
</html>
