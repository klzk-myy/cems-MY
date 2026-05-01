@extends('layouts.base')

@section('title', 'Customers - CEMS-MY')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Customers</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Manage customer records and due diligence</p>
    </div>
    <a href="{{ route('customers.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add Customer
    </a>
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Customers</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>ID Number</th>
                    <th>CDD Level</th>
                    <th>Risk Score</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($customers as $customer)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $customer->full_name }}</td>
                    <td class="font-mono text-xs text-[--color-ink-muted]">{{ $customer->id_number }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($customer->cdd_level === 'Simplified') bg-green-100 text-green-700
                            @elseif($customer->cdd_level === 'Standard') bg-blue-100 text-blue-700
                            @else bg-orange-100 text-orange-700
                            @endif">
                            {{ $customer->cdd_level }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($customer->risk_rating === 'Low') bg-green-100 text-green-700
                            @elseif($customer->risk_rating === 'Medium') bg-yellow-100 text-yellow-700
                            @else bg-red-100 text-red-700
                            @endif">
                            {{ $customer->risk_rating }}
                        </span>
                    </td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $customer->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $customer->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No customers found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($customers->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $customers->links() }}
    </div>
    @endif
</div>
@endsection