@props([
    'level' => null,
    'customer' => null,
])

@php
// Accepts either an explicit `level` ('low'|'medium'|'high'|'critical') or a
// `customer` model whose risk_rating (RiskRating enum or string) is mapped.
$resolved = $level;

if ($resolved === null && $customer !== null) {
    $rating = $customer->risk_rating ?? null;
    $resolved = $rating instanceof \App\Enums\RiskRating ? $rating->value : (string) $rating;
}

$resolved = strtolower((string) ($resolved ?: 'low'));

$levels = [
    'low' => 'bg-success-subtle text-success-text',
    'medium' => 'bg-warning-subtle text-warning-text',
    'high' => 'bg-danger-subtle text-danger-text',
    'critical' => 'bg-danger text-on-danger',
];
@endphp

<span {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ' . ($levels[$resolved] ?? $levels['low'])]) }}>
    {{ ucfirst($resolved) }}
</span>
