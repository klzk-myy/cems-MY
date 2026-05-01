@extends('layouts.base')

@section('title', 'EDD Records - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">EDD Records</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Enhanced Due Diligence documentation</p>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All EDD Records</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($eddRecords ?? [] as $record)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $record->id }}</td>
                    <td class="text-[--color-ink]">{{ $record->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">{{ $record->edd_type ?? 'Standard' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($record->status === 'completed') bg-green-100 text-green-700
                            @elseif($record->status === 'pending') bg-yellow-100 text-yellow-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ ucfirst($record->status) }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-sm">{{ $record->created_at->format('Y-m-d') }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('compliance.edd.show', $record) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No EDD records found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection