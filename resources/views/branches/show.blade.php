@extends('layouts.base')

@section('title', 'Branch Details - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">{{ $branch->code }} - {{ $branch->name }}</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Branch management</p>
    </div>
    <div class="flex items-center gap-3">
        <a href="{{ route('branches.index') }}" class="px-4 py-2 border border-[--color-border] text-[--color-ink] text-sm font-medium rounded-lg hover:bg-[--color-canvas-subtle]">
            Back
        </a>
        @role('admin')
        <a href="{{ route('branches.edit', $branch) }}" class="px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
            Edit Branch
        </a>
        @endrole
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Branch Information</h3>
        </div>
        <div class="p-6 space-y-4">
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Code</span>
                <span class="text-sm font-mono text-[--color-ink]">{{ $branch->code }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Name</span>
                <span class="text-sm text-[--color-ink]">{{ $branch->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Type</span>
                <span class="text-sm text-[--color-ink]">{{ $branch->type ?? 'Branch' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Status</span>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $branch->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                    {{ $branch->is_active ? 'Active' : 'Inactive' }}
                </span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Address</span>
                <span class="text-sm text-[--color-ink]">{{ $branch->address ?? 'N/A' }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">Phone</span>
                <span class="text-sm text-[--color-ink]">{{ $branch->phone ?? 'N/A' }}</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Statistics</h3>
        </div>
        <div class="p-6 space-y-4">
            @foreach($stats as $key => $value)
            <div class="flex justify-between">
                <span class="text-sm text-[--color-ink-muted]">{{ ucfirst(str_replace('_', ' ', $key)) }}</span>
                <span class="text-sm font-semibold text-[--color-ink]">{{ is_numeric($value) ? number_format($value) : $value }}</span>
            </div>
            @endforeach
        </div>
    </div>
</div>

@if($childBranches->count() > 0)
<div class="card mt-6">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">Child Branches</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($childBranches as $child)
                <tr class="border-b border-[--color-border]">
                    <td class="text-[--color-ink] font-mono">{{ $child->code }}</td>
                    <td class="text-[--color-ink]">{{ $child->name }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $child->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $child->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection