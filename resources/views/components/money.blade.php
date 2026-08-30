@props([
    'amount' => 0,
    'currency' => 'MYR',
    'decimals' => 2,
    'symbol' => null,
])

@php
$symbols = [
    'MYR' => 'RM',
    'USD' => '$',
    'EUR' => '€',
    'GBP' => '£',
    'SGD' => 'S$',
    'THB' => '฿',
    'IDR' => 'Rp',
    'JPY' => '¥',
    'AUD' => 'A$',
    'CAD' => 'C$',
];

$sym = $symbol ?? ($symbols[$currency] ?? $currency);
$formatted = number_format((float) $amount, (int) $decimals);
@endphp
{{ $sym }} {{ $formatted }}
