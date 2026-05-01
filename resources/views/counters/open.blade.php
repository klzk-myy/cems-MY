@extends('layouts.base')

@section('title', 'Open Counter')

@section('content')
<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Open Counter - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="p-6">
        <form method="POST" action="{{ route('counters.open', $counter->id ?? 0) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Opening Float (MYR)</label>
                    <input type="number" step="0.01" name="opening_float" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">Notes</label>
                    <textarea name="notes" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" rows="2"></textarea>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Open Counter</button>
                <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection