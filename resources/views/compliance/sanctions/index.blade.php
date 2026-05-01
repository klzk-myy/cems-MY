@extends('layouts.base')

@section('title', 'Sanctions - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Sanctions List</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Watchlist monitoring and management</p>
    </div>
    <div class="flex items-center gap-3">
    @if($lists && count($lists) > 0)
    <form method="POST" action="{{ route('compliance.sanctions.import', $lists[0]['id'] ?? 0) }}" class="inline">
        @csrf
        <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path>
            </svg>
            Import List
        </button>
    </form>
    @endif
</div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Watchlist Entries</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>List Type</th>
                    <th>Score</th>
                    <th>Entity Type</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($entries ?? [] as $entry)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $entry->entity_name }}</td>
                    <td class="text-[--color-ink]">{{ $entry->list_name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink] font-mono">{{ round($entry->score ?? 0, 1) }}%</td>
                    <td class="text-[--color-ink]">{{ $entry->entity_type ?? 'Individual' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $entry->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $entry->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No sanctions entries found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection