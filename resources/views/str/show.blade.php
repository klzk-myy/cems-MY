@extends('layouts.base')

@section('title', 'STR Detail - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">STR Detail</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Suspicious Transaction Report #{{ $str->id }}</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('str.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
            Back
        </a>
        @if($str->status->value === 'draft')
        <a href="{{ route('str.edit', $str) }}" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
            Edit STR
        </a>
        @endif
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">STR Information</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Reference</span>
                <span class="text-sm font-mono text-[--color-ink]">{{ $str->reference_number ?? 'DRAFT' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                    @if($str->status->value === 'draft') bg-gray-100 text-gray-700
                    @elseif($str->status->value === 'pending_review') bg-yellow-100 text-yellow-700
                    @elseif($str->status->value === 'pending_approval') bg-orange-100 text-orange-700
                    @elseif($str->status->value === 'submitted') bg-blue-100 text-blue-700
                    @else bg-green-100 text-green-700
                    @endif">
                    {{ $str->status->label() }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Created By</span>
                <span class="text-sm text-[--color-ink]">{{ $str->creator->username ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Created At</span>
                <span class="text-sm text-[--color-ink]">{{ $str->created_at->format('Y-m-d H:i') }}</span>
            </div>
            @if($str->submitted_at)
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Submitted At</span>
                <span class="text-sm text-[--color-ink]">{{ $str->submitted_at->format('Y-m-d H:i') }}</span>
            </div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Customer</h3>
        </div>
        <div class="p-6 space-y-4">
            @if($str->customer)
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Name</span>
                <span class="text-sm text-[--color-ink]">{{ $str->customer->full_name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">ID Type</span>
                <span class="text-sm text-[--color-ink]">{{ $str->customer->id_type }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Nationality</span>
                <span class="text-sm text-[--color-ink]">{{ $str->customer->nationality }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Risk Rating</span>
                <span class="text-sm text-[--color-ink]">{{ $str->customer->risk_rating }}</span>
            </div>
            @else
            <p class="text-sm text-[--color-ink-muted]">No customer associated</p>
            @endif
        </div>
    </div>
</div>

<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">STR Reason</h3>
    </div>
    <div class="p-6">
        <p class="text-sm text-[--color-ink]">{{ $str->reason ?? 'No reason provided' }}</p>
    </div>
</div>

@if($str->status->value === 'draft')
<div class="card mt-6">
    <div class="p-6">
        <div class="flex items-center gap-4">
            <form action="{{ route('str.submit-review', $str) }}" method="POST" class="inline">
                @csrf
                <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                    Submit for Review
                </button>
            </form>
        </div>
    </div>
</div>
@endif

@if($str->status->value === 'pending_review')
<div class="card mt-6">
    <div class="p-6">
        <form action="{{ route('str.submit-approval', $str) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
                Submit for Approval
            </button>
        </form>
    </div>
</div>
@endif

@if($str->status->value === 'pending_approval')
<div class="card mt-6">
    <div class="p-6">
        <form action="{{ route('str.approve', $str) }}" method="POST" class="inline">
            @csrf
            <button type="submit" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700">
                Approve & Submit to goAML
            </button>
        </form>
    </div>
</div>
@endif
@endsection