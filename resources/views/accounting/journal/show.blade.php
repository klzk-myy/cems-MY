@extends('layouts.base')

@section('title', 'Journal Entry #' . ($entry->id ?? ''))

@section('content')
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Journal Entry JE-{{ str_pad($entry->id ?? '', 6, '0', STR_PAD_LEFT) }}</h3>
        @php
            $statusClass = match($entry->status->value ?? '') {
                'Posted' => 'badge-success',
                'Pending' => 'badge-warning',
                default => 'badge-default'
            };
        @endphp
        <span class="badge {{ $statusClass }}">{{ $entry->status->label() ?? 'Draft' }}</span>
    </div>
    <div class="card-body">
        <p><strong>Date:</strong> {{ $entry->entry_date->format('d M Y') }}</p>
        <p><strong>Description:</strong> {{ $entry->description }}</p>
    </div>
    <div class="table-container">
        <table class="table">
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
                <tr><td colspan="3" class="text-muted">No journal lines.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
