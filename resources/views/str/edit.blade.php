@extends('layouts.base')

@section('title', 'Edit STR - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Edit STR</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Draft STR #{{ $str->id }}</p>
    </div>
    <a href="{{ route('str.show', $str) }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
        Back
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">STR Details</h3>
    </div>
    <div class="p-6">
        <form action="{{ route('str.update', $str) }}" method="POST">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-2">STR Reason (min 20 chars)</label>
                    <textarea name="reason" rows="4" class="w-full px-3 py-2 border border-[--color-border] rounded-lg text-sm"
                        placeholder="Describe why this transaction is suspicious...">{{ $str->reason }}</textarea>
                </div>
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                    Update STR
                </button>
            </div>
        </form>
    </div>
</div>
@endsection