@extends('layouts.base')

@section('title', 'Sanction Lists')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Sanction Lists</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage sanctions lists and entries</p>
</div>
@endsection

@section('header-actions')
<a href="/compliance/sanctions/import-logs" class="btn btn-ghost">
    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
    </svg>
    Import Logs
</a>
@endsection

@section('content')
<div class="grid grid-cols-4 gap-4 mb-6">
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total Lists</p>
            <p class="text-2xl font-bold">{{ count($lists) }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Active Lists</p>
            <p class="text-2xl font-bold">{{ collect($lists)->where('is_active', true)->count() }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Total Entries</p>
            <p class="text-2xl font-bold">{{ collect($lists)->sum('entry_count') }}</p>
        </div>
    </div>
    <div class="card">
        <div class="card-body">
            <p class="text-sm text-[--color-ink-muted]">Last Import</p>
            <p class="text-2xl font-bold">{{ collect($lists)->whereNotNull('last_updated_at')->sortByDesc('last_updated_at')->first()?->last_updated_at?->format('d M Y') ?? 'N/A' }}</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3 class="card-title">All Sanction Lists</h3>
    </div>
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Entries</th>
                    <th>Status</th>
                    <th>Last Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($lists as $list)
                <tr>
                    <td>
                        <a href="/compliance/sanctions/{{ $list['id'] }}" class="font-medium text-[--color-accent] hover:underline">{{ $list['name'] }}</a>
                    </td>
                    <td>{{ $list['list_type'] ?? 'N/A' }}</td>
                    <td>{{ number_format($list['entry_count'] ?? 0) }}</td>
                    <td>
                        @if(($list['is_active'] ?? false))
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td>{{ isset($list['last_updated_at']) ? \Carbon\Carbon::parse($list['last_updated_at'])->format('d M Y') : 'Never' }}</td>
                    <td>
                        <div class="flex gap-2">
                            <a href="/compliance/sanctions/{{ $list['id'] }}" class="btn btn-ghost btn-sm">View</a>
                            <form method="POST" action="/compliance/sanctions/{{ $list['id'] }}/import" class="inline">
                                @csrf
                                <button type="submit" class="btn btn-ghost btn-sm">Trigger Import</button>
                            </form>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center py-12 text-[--color-ink-muted]">No sanction lists found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
