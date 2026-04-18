@extends('layouts.base')

@section('title', 'Financial Ratios')

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Financial Ratios</h3>
        <span class="text-sm text-[--color-ink-muted]">As of {{ $asOfDate ?? date('d M Y') }}</span>
    </div>
    <div class="card-body">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            @forelse($ratios ?? [] as $category => $items)
            <div>
                <h4 class="font-semibold mb-3">{{ $category }}</h4>
                @forelse($items as $name => $value)
                <div class="flex justify-between py-2 border-b border-[--color-border]">
                    <span class="text-sm">{{ $name }}</span>
                    <span class="font-mono font-medium">{{ is_numeric($value) ? number_format($value, 2) : $value }}</span>
                </div>
                @empty
                @endforelse
            </div>
            @empty
            <p class="text-[--color-ink-muted]">No ratio data available</p>
            @endforelse
        </div>
    </div>
</div>
@endsection
