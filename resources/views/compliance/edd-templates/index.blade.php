@extends('layouts.base')

@section('title', 'EDD Templates - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">EDD Templates</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Enhanced Due Diligence questionnaire templates</p>
    </div>
    <a href="{{ route('compliance.edd-templates.create') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New Template
    </a>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="card p-4">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $statistics['total'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Total Templates</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-green-600">{{ $statistics['active'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Active</div>
    </div>
    <div class="card p-4">
        <div class="text-2xl font-bold text-[--color-ink]">{{ $statistics['usage_count'] ?? 0 }}</div>
        <div class="text-sm text-[--color-ink-muted]">Times Used</div>
    </div>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Templates</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Questions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($templates as $template)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $template->name }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">
                            {{ str_replace('_', ' ', ucfirst($template->type)) }}
                        </span>
                    </td>
                    <td class="text-[--color-ink] text-sm">{{ count($template->questions ?? []) }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $template->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $template->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">
                        <a href="{{ route('compliance.edd-templates.show', $template) }}" class="text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No templates found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection