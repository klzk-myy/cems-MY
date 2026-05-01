@extends('layouts.base')

@section('title', 'Counter History')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Counter History - {{ $counter->name ?? 'N/A' }}</h3></div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Opened</th>
                    <th>Closed</th>
                    <th>User</th>
                    <th class="text-right">Opening Float</th>
                    <th class="text-right">Closing Float</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($sessions ?? [] as $session)
                <tr>
                    <td class="font-mono">{{ $session['opened_at'] ?? 'N/A' }}</td>
                    <td class="font-mono">{{ $session['closed_at'] ?? '-' }}</td>
                    <td>{{ $session['user'] ?? 'N/A' }}</td>
                    <td class="font-mono text-right">RM {{ number_format($session['opening_float'] ?? 0, 2) }}</td>
                    <td class="font-mono text-right">RM {{ number_format($session['closing_float'] ?? 0, 2) }}</td>
                    <td>
                        @if(isset($session['status']))
                            @statuslabel($session['status'])
                        @else
                            <span class="text-[--color-ink-muted]">N/A</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center py-8 text-[--color-ink-muted]">No sessions found</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection