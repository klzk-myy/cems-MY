@extends('layouts.base')

@section('title', 'Journal Entry #' . ($entry->id ?? ''))

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Journal Entry JE-{{ str_pad($entry->id ?? '', 6, '0', STR_PAD_LEFT) }}</h3>
        @php
            $statusClass = match($entry->status->value ?? '') {
                'Posted' => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700',
                'Pending' => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700',
                default => 'inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-gray-100 text-gray-700'
            };
        @endphp
        <span class="{{ $statusClass }}">{{ $entry->status->label() ?? 'Draft' }}</span>
    </div>
    <div class="p-6">
        <p><strong>Date:</strong> {{ $entry->entry_date->format('d M Y') }}</p>
        <p><strong>Description:</strong> {{ $entry->description }}</p>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Debit (MYR)</th>
                    <th>Credit (MYR)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($entry->lines ?? [] as $line)
                <tr>
                    <td>{{ $line->account->name ?? 'N/A' }} ({{ $line->account_code }})</td>
                    <td class="font-mono">{{ $line->debit > 0 ? number_format($line->debit, 2) : '-' }}</td>
                    <td class="font-mono">{{ $line->credit > 0 ? number_format($line->credit, 2) : '-' }}</td>
                </tr>
                @endforeach
                @if(empty($entry->lines ?? []))
                <tr><td colspan="3" class="text-[--color-ink-muted]">No journal lines.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection