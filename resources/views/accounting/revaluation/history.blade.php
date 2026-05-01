@extends('layouts.base')

@section('title', 'Revaluation History')

@section('content')
<div class="bg-white border border-[--color-border] rounded-xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Revaluation History - {{ $month ?? date('F Y') }}</h3></div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Currency</th>
                    <th>Old Rate</th>
                    <th>New Rate</th>
                    <th>P/L Effect</th>
                </tr>
            </thead>
            <tbody>
                @forelse($history ?? [] as $entry)
                <tr>
                    <td>{{ $entry->created_at->format('d M Y') }}</td>
                    <td class="font-mono">{{ $entry->currency_code }}</td>
                    <td class="font-mono">{{ number_format($entry->old_rate, 4) }}</td>
                    <td class="font-mono">{{ number_format($entry->new_rate, 4) }}</td>
                    <td class="font-mono {{ $entry->pl_effect >= 0 ? 'text-[--color-success]' : 'text-[--color-danger]' }}">
                        {{ number_format($entry->pl_effect, 2) }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="5" class="text-center py-8 text-[--color-ink-muted]">No history</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection