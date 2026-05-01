@extends('layouts.base')

@section('title', 'Journal Entries')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Journal Entries</h1>
    <p class="text-sm text-[--color-ink-muted]">Double-entry accounting records</p>
</div>
@endsection

@section('header-actions')
<a href="/accounting/journal/create" class="px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-primary]/90">
    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    New Entry
</a>
@endsection

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Entry No.</th>
                    <th>Description</th>
                    <th>Accounts</th>
                    <th>Amount</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                 @forelse($entries ?? [] as $entry)
                 <tr>
                     <td>{{ $entry->entry_date?->format('d M Y') ?? 'N/A' }}</td>
                     <td class="font-mono text-xs">JE-{{ str_pad($entry->id, 6, '0', STR_PAD_LEFT) }}</td>
                     <td>{{ $entry->description }}</td>
                     <td class="text-[--color-ink-muted]">{{ $entry->lines->count() }} accounts</td>
                     <td class="font-mono">{{ number_format($entry->getTotalDebits(), 2) }} MYR</td>
                     <td>
                         @php
                             $statusValue = $entry->status instanceof \App\Enums\JournalEntryStatus
                                 ? $entry->status->value
                                 : (string)$entry->status;
                             $statusLabel = $entry->status instanceof \App\Enums\JournalEntryStatus
                                 ? $entry->status->label()
                                 : (string)$entry->status;
                             $statusClass = match($statusValue) {
                                 'Posted' => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700',
                                 'Pending' => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700',
                                 'Draft' => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700',
                                 default => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700'
                             };
                         @endphp
                         <span class="{{ $statusClass }}">{{ $statusLabel }}</span>
                     </td>
                     <td>
                         <a href="/accounting/journal/{{ $entry->id }}" class="px-3 py-1.5 text-xs font-medium rounded-lg hover:bg-[--color-canvas-subtle]">View</a>
                     </td>
                 </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center py-12 text-[--color-ink-muted]">No journal entries found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection