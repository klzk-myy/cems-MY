@extends('layouts.base')

@section('title', 'Customers')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Customers</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage customer records and KYC</p>
</div>
@endsection

@section('header-actions')
<div class="flex items-center gap-3">
    <a href="/customers/create" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Customer
    </a>
</div>
@endsection

@section('content')
{{-- Filters --}}
<div class="card mb-6">
    <div class="card-body">
        <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="form-group mb-0">
                <label class="form-label">Search</label>
                <input type="text" name="search" class="form-input" placeholder="Name..." value="{{ request('search') }}">
            </div>
            <div class="form-group mb-0">
                <label class="form-label">CDD Level</label>
                <select name="cdd_level" class="form-select">
                    <option value="">All Levels</option>
                    <option value="Simplified" {{ request('cdd_level') === 'Simplified' ? 'selected' : '' }}>Simplified</option>
                    <option value="Standard" {{ request('cdd_level') === 'Standard' ? 'selected' : '' }}>Standard</option>
                    <option value="Enhanced" {{ request('cdd_level') === 'Enhanced' ? 'selected' : '' }}>Enhanced</option>
                </select>
            </div>
            <div class="form-group mb-0">
                <label class="form-label">Risk Level</label>
                <select name="risk_level" class="form-select">
                    <option value="">All Levels</option>
                    <option value="Low" {{ request('risk_level') === 'Low' ? 'selected' : '' }}>Low</option>
                    <option value="Medium" {{ request('risk_level') === 'Medium' ? 'selected' : '' }}>Medium</option>
                    <option value="High" {{ request('risk_level') === 'High' ? 'selected' : '' }}>High</option>
                </select>
            </div>
            <div class="md:col-span-4 flex justify-end gap-3">
                <a href="/customers" class="btn btn-ghost">Clear Filters</a>
                <button type="submit" class="btn btn-secondary">Apply Filters</button>
            </div>
        </form>
    </div>
</div>

{{-- Customers Table --}}
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>Customer</th>
                    <th>ID Type</th>
                    <th>CDD Level</th>
                    <th>Risk Level</th>
                    <th>Total Transactions</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers ?? [] as $customer)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center">
                                <span class="font-semibold">{{ substr($customer->full_name, 0, 1) }}</span>
                            </div>
                            <div>
                                <p class="font-medium">{{ $customer->full_name }}</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $customer->email ?? 'No email' }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="font-mono">{{ $customer->id_type ?? 'N/A' }}</td>
                    <td>
                        @php
                            $cddClass = match($customer->cdd_level ?? '') {
                                'Simplified' => 'badge-info',
                                'Standard' => 'badge-warning',
                                'Enhanced' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $cddClass }}">{{ $customer->cdd_level ?? 'N/A' }}</span>
                    </td>
                    <td>
                        @php
                            $riskClass = match($customer->risk_level ?? '') {
                                'Low' => 'badge-success',
                                'Medium' => 'badge-warning',
                                'High' => 'badge-danger',
                                'Critical' => 'badge-danger',
                                default => 'badge-default'
                            };
                        @endphp
                        <span class="badge {{ $riskClass }}">{{ $customer->risk_level ?? 'N/A' }}</span>
                    </td>
                    <td class="font-mono">{{ number_format($customer->transactions_count ?? 0) }}</td>
                    <td>
                        @if($customer->sanction_hit ?? false)
                            <span class="badge badge-danger">Sanctioned</span>
                        @elseif($customer->pep_status ?? false)
                            <span class="badge badge-warning">PEP</span>
                        @else
                            <span class="badge badge-success">Active</span>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="/customers/{{ $customer->id }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            <a href="/customers/{{ $customer->id }}/edit" class="btn btn-ghost btn-icon" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7">
                        <div class="empty-state py-12">
                            <div class="empty-state-icon">
                                <svg class="w-8 h-8 text-[--color-ink-muted]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <p class="empty-state-title">No customers found</p>
                            <p class="empty-state-description">Start by adding your first customer</p>
                            <a href="/customers/create" class="btn btn-primary mt-4">Add Customer</a>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($customers && $customers->hasPages())
        <div class="card-footer">
            {{ $customers->withQueryString()->links() }}
        </div>
    @endif
</div>
@endsection
