@props([
    'customer' => null,
    'fallback' => 'N/A',
    'field' => 'full_name',
])

@php
$attrs = ($attributes ?? new \Illuminate\View\ComponentAttributeBag);
$display = $customer?->{$field};
@endphp

@if($customer && $display && auth()->user()?->can('view', $customer))
    <a href="{{ route('customers.show', $customer) }}" {{ $attrs->merge(['class' => 'text-primary hover:underline']) }}>{{ $display }}</a>
@else
    {{ $display ?? $fallback }}
@endif
