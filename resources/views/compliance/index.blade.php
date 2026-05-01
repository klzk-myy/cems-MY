@extends('layouts.base')

@section('title', 'Compliance - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">AML/CFT monitoring and regulatory reporting</p>
    </div>
    <div class="flex gap-3">
        <a href="{{ route('compliance.alerts.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
            View Alerts
        </a>
        @role('compliance_officer')
        <a href="{{ route('compliance.cases.index') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            New Case
        </a>
        @endrole
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="card p-6">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['open'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted] mt-1">Open Alerts</div>
        <div class="text-xs text-red-500 mt-2">{{ $stats['high_priority'] ?? 0 }} Critical</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['under_review'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted] mt-1">Under Review</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $stats['resolved_today'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted] mt-1">Resolved Today</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $strStats['submitted'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted] mt-1">STR Submitted</div>
        <div class="text-xs text-[--color-ink-muted] mt-2">This month</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Recent Alerts</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Alert ID</th>
                    <th>Type</th>
                    <th>Severity</th>
                    <th>Customer</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($flags as $flag)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="font-mono text-xs text-[--color-ink]">{{ $flag->id }}</td>
                    <td class="text-[--color-ink]">{{ $flag->flag_type ?? 'Unknown' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if(in_array($flag->flag_type, ['Sanction_Match', 'Structuring', 'Velocity'])) bg-red-100 text-red-700
                            @elseif($flag->flag_type === 'High_Risk') bg-orange-100 text-orange-700
                            @else bg-yellow-100 text-yellow-700
                            @endif">
                            {{ $flag->flag_type ?? 'Medium' }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">{{ $flag->transaction->customer->full_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($flag->status === 'Open') bg-yellow-100 text-yellow-700
                            @elseif($flag->status === 'Under_Review') bg-blue-100 text-blue-700
                            @elseif($flag->status === 'Resolved') bg-green-100 text-green-700
                            @else bg-gray-100 text-gray-700
                            @endif">
                            {{ str_replace('_', ' ', $flag->status?->value ?? 'Open') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No alerts found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($flags->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $flags->links() }}
    </div>
    @endif
</div>
@endsection
