@extends('layouts.base')

@section('title', 'Ledger: ' . ($accountCode ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Account: {{ $accountCode ?? '' }}</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Entry</th>
                    <th>Description</th>
                    <th>Debit</th>
                    <th>Credit</th>
                    <th>Balance</th>
                </tr>
            </thead>
            <tbody>
                @forelse($ledger['entries'] ?? [] as $entry)
                <tr>
                    <td>{{ $entry->entry_date instanceof \Carbon\Carbon ? $entry->entry_date->format('d M Y') : $entry->entry_date }}</td>
                    <td class="font-mono text-xs">{{ $entry->id }}</td>
                    <td>{{ $entry->description ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $entry->debit && $entry->debit > 0 ? number_format((float)$entry->debit, 2) : '-' }}</td>
                    <td class="font-mono">{{ $entry->credit && $entry->credit > 0 ? number_format((float)$entry->credit, 2) : '-' }}</td>
                    <td class="font-mono font-medium">{{ number_format((float)$entry->getRunningBalance(), 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No entries</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
