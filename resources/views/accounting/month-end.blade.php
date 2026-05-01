@extends('layouts.base')

@section('title', 'Month-End Close - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Month-End Close</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Status for {{ $selectedDate }}</p>
</div>

<div class="card mb-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Status for {{ $selectedDate }}</h3>
    </div>
    <div class="p-6">
        @if(isset($status))
            <div class="space-y-3">
                @foreach($status as $key => $value)
                    <div class="flex justify-between py-2 border-b border-[--color-border] last:border-0">
                        <span class="text-sm text-[--color-ink-muted]">{{ ucwords(str_replace('_', ' ', $key)) }}</span>
                        <span class="text-sm font-medium">{{ is_bool($value) ? ($value ? 'Yes' : 'No') : $value }}</span>
                    </div>
                @endforeach
            </div>
        @else
            <p class="text-sm text-[--color-ink-muted]">No status information available.</p>
        @endif
    </div>
</div>

<form method="POST" action="{{ route('accounting.month-end.close') }}" class="card">
    @csrf
    <input type="hidden" name="date" value="{{ $selectedDate }}">
    <div class="p-6">
        <p class="text-sm text-[--color-ink-muted] mb-4">Run month-end close for {{ $selectedDate }}. This will finalize all accounting periods and prevent further modifications.</p>
        <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]" onclick="return confirm('Are you sure you want to run month-end close?')">
            Run Month-End Close
        </button>
    </div>
</form>
@endsection