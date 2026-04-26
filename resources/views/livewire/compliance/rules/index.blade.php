@extends('layouts.base')

@section('title', 'AML Rules')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-gray-900">AML Rules</h1>
    <p class="text-sm text-gray-500">Configure AML rule engine</p>
</div>
@endsection

@section('header-actions')
<a href="{{ route('compliance.rules.create') }}" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    Add Rule
</a>
@endsection

@section('content')
{{-- Summary Stats --}}
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-blue-500/10 text-blue-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Total Rules</p>
        <p class="stat-card-value">{{ number_format($summary['total'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-green-600/10 text-green-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Active</p>
        <p class="stat-card-value">{{ number_format($summary['active'] ?? 0) }}</p>
    </div>

    <div class="stat-card">
        <div class="stat-card-header">
            <div class="stat-card-icon bg-gray-500/10 text-gray-500">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                </svg>
            </div>
        </div>
        <p class="stat-card-label">Inactive</p>
        <p class="stat-card-value">{{ number_format($summary['inactive'] ?? 0) }}</p>
    </div>
</div>

{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form wire:submit="applyFilters" class="flex flex-wrap gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" wire:model="search" class="form-input" placeholder="Rule name or code...">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Type</label>
                <select wire:model="type" class="form-select">
                    <option value="">All Types</option>
                    @foreach($ruleTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Status</label>
                <select wire:model="status" class="form-select">
                    <option value="">All</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="flex items-end gap-3">
                <button type="button" wire:click="clearFilters" class="btn btn-ghost">Clear Filters</button>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Rules Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Type</th>
                    <th>Action</th>
                    <th>Risk Score</th>
                    <th>Status</th>
                    <th>Hits</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($rules as $rule)
                <tr>
                    <td>
                        <div>
                            <p class="font-medium">{{ $rule->rule_name }}</p>
                            <p class="text-xs text-gray-500">{{ $rule->rule_code }}</p>
                        </div>
                    </td>
                    <td>
                        <span class="badge badge-default">{{ $rule->rule_type->label() ?? 'N/A' }}</span>
                    </td>
                    <td>
                        <span class="badge badge-{{ $rule->action === 'block' ? 'danger' : ($rule->action === 'hold' ? 'warning' : 'info') }}">
                            {{ ucfirst($rule->action) }}
                        </span>
                    </td>
                    <td class="font-mono">{{ $rule->risk_score }}</td>
                    <td>
                        @if($rule->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td class="font-mono">{{ number_format($rule->hit_count ?? 0) }}</td>
                    <td>
                        <div class="flex items-center gap-2">
                            <a href="{{ route('compliance.rules.show', $rule->id) }}" class="btn btn-ghost btn-sm">View</a>
                            <button
                                wire:click="toggleRule({{ $rule->id }})"
                                class="btn btn-ghost btn-sm"
                                title="{{ $rule->is_active ? 'Disable' : 'Enable' }}"
                            >
                                @if($rule->is_active)
                                    <svg class="w-4 h-4 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                    </svg>
                                @else
                                    <svg class="w-4 h-4 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                @endif
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No rules configured</p>
                            <p class="empty-state-description">Create your first AML rule to start monitoring transactions</p>
                            <a href="{{ route('compliance.rules.create') }}" class="btn btn-primary mt-4">Add Rule</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($rules->hasPages())
        <div class="card-footer">
            {{ $rules->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
