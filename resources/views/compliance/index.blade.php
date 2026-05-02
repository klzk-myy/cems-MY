@extends('layouts.base')

@section('title', 'Compliance Dashboard - CEMS-MY')

@section('content')
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[--color-ink]">Compliance Dashboard</h1>
            <p class="text-sm text-[--color-ink-muted] mt-1">AML/CFT monitoring and alerts</p>
        </div>
    </div>

    {{-- Stats Cards --}}
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <p class="text-sm text-[--color-ink-muted]">Open Flags</p>
            <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['open'] ?? 0 }}</p>
        </div>
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <p class="text-sm text-[--color-ink-muted]">Under Review</p>
            <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['under_review'] ?? 0 }}</p>
        </div>
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <p class="text-sm text-[--color-ink-muted]">Resolved Today</p>
            <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['resolved_today'] ?? 0 }}</p>
        </div>
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <p class="text-sm text-[--color-ink-muted]">High Priority</p>
            <p class="text-2xl font-semibold text-[--color-ink] mt-1">{{ $stats['high_priority'] ?? 0 }}</p>
        </div>
    </div>

    {{-- STR Stats --}}
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <p class="text-sm text-yellow-700">STR Draft</p>
            <p class="text-xl font-semibold text-yellow-800">{{ $strStats['draft'] ?? 0 }}</p>
        </div>
        <div class="bg-orange-50 border border-orange-200 rounded-xl p-4">
            <p class="text-sm text-orange-700">STR Pending Review</p>
            <p class="text-xl font-semibold text-orange-800">{{ $strStats['pending_review'] ?? 0 }}</p>
        </div>
        <div class="bg-blue-50 border border-blue-200 rounded-xl p-4">
            <p class="text-sm text-blue-700">STR Pending Approval</p>
            <p class="text-xl font-semibold text-blue-800">{{ $strStats['pending_approval'] ?? 0 }}</p>
        </div>
        <div class="bg-red-50 border border-red-200 rounded-xl p-4">
            <p class="text-sm text-red-700">STR Overdue</p>
            <p class="text-xl font-semibold text-red-800">{{ $strStats['overdue'] ?? 0 }}</p>
        </div>
        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-4">
            <p class="text-sm text-yellow-700">STR Near Deadline</p>
            <p class="text-xl font-semibold text-yellow-800">{{ $strStats['near_deadline'] ?? 0 }}</p>
        </div>
    </div>

    {{-- Flagged Transactions --}}
    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h2 class="text-lg font-semibold text-[--color-ink]">Flagged Transactions</h2>
        </div>
        <div class="p-6">
            @if(isset($flags) && $flags->count() > 0)
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-sm text-[--color-ink-muted]">
                            <th class="pb-3">ID</th>
                            <th class="pb-3">Type</th>
                            <th class="pb-3">Status</th>
                            <th class="pb-3">Assigned To</th>
                            <th class="pb-3">Created</th>
                        </tr>
                    </thead>
                    <tbody class="text-sm">
                        @foreach($flags as $flag)
                        <tr class="border-t border-[--color-border]">
                            <td class="py-3">{{ $flag->id }}</td>
                            <td class="py-3">
                                <span class="px-2 py-1 rounded text-xs font-medium bg-red-100 text-red-700">
                                    {{ $flag->flag_type }}
                                </span>
                            </td>
                            <td class="py-3">{{ $flag->status }}</td>
                            <td class="py-3">{{ $flag->assignedTo->username ?? 'Unassigned' }}</td>
                            <td class="py-3">{{ $flag->created_at->format('Y-m-d H:i') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="mt-4">
                    {{ $flags->links() }}
                </div>
            @else
                <p class="text-[--color-ink-muted] text-center py-8">No flagged transactions</p>
            @endif
        </div>
    </div>
</div>
@endsection