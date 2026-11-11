@props([
    'type' => 'line',
    'labels' => [],
    'datasets' => [],
    'options' => [],
    'height' => 280,
])

@php
$chartOptions = array_replace_recursive([
    'responsive' => true,
    'maintainAspectRatio' => false,
], $options);

$chartConfig = [
    'type' => $type,
    'data' => ['labels' => $labels, 'datasets' => $datasets],
    'options' => $chartOptions,
];
@endphp

{{-- Chart.js canvas: config is carried in data-config so the CSP-safe
     Alpine chartData component can build the chart without inline JS. --}}
<div
    x-data="chartData"
    data-config='@json($chartConfig)'
    {{ ($attributes ?? new \Illuminate\View\ComponentAttributeBag)->merge(['class' => 'relative']) }}
    style="height: {{ (int) $height }}px"
>
    <canvas></canvas>
</div>
