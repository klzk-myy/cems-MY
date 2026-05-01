@extends('layouts.base')

@section('title', 'Branches - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Branches</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Branch management</p>
    </div>
    @role('admin')
    <a href="{{ route('branches.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Branch
    </a>
    @endrole
</div>

<div class="card">
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($branches as $branch)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-mono font-medium">{{ $branch->code }}</td>
                    <td class="text-[--color-ink]">{{ $branch->name }}</td>
                    <td class="text-[--color-ink]">{{ $branch->type ?? 'Branch' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $branch->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $branch->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-sm">{{ $branch->created_at->format('Y-m-d') }}</td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('branches.show', $branch) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-ink-muted]">No branches found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($branches->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $branches->links() }}
    </div>
    @endif
</div>
@endsection