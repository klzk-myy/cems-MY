@extends('layouts.base')

@section('title', 'Customers - CEMS-MY')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Customers</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Manage customer information and KYC records</p>
        </div>
        <a href="{{ route('customers.create') }}" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Add Customer</a>
    </div>

    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden mb-6">
        <div class="p-4 border-b border-[#e5e5e5] bg-[#f7f7f8]">
            <div class="flex flex-wrap gap-4">
                <div class="flex-1 min-w-[200px]">
                    <input type="text" wire:model.live="search" placeholder="Search by name..." class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                </div>
                <div class="w-36">
                    <select wire:model.live="riskRating" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">All Risk</option>
                        @foreach($riskRatings as $rating)
                            <option value="{{ $rating }}">{{ $rating }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="w-32">
                    <select wire:model.live="statusFilter" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">All Status</option>
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="w-28">
                    <select wire:model.live="pepFilter" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">PEP?</option>
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div class="w-40">
                    <select wire:model.live="nationalityFilter" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg focus:outline-none focus:border-[#d4a843] focus:ring-1 focus:ring-[#d4a843]/30">
                        <option value="">All Nations</option>
                        @foreach($nationalities as $nation)
                            <option value="{{ $nation }}">{{ $nation }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="p-6">
            @if($customers->isEmpty())
                <div class="text-center py-8 text-[#6b6b6b]">No customers found.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">ID Type</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Risk</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">PEP</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Transactions</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Created</th>
                                <th class="px-4 py-3 text-center text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#e5e5e5]">
                            @foreach($customers as $customer)
                                <tr class="hover:bg-[#f7f7f8]/50">
                                    <td class="px-4 py-3 text-sm font-medium text-[#171717]">
                                        <a href="{{ route('customers.show', $customer) }}" class="text-[#d4a843] hover:underline">{{ $customer->full_name }}</a>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#6b6b6b]">{{ $customer->id_type }} - {{ $customer->ic_number ? substr($customer->ic_number, 0, 4).'****'.substr($customer->ic_number, -4) : 'N/A' }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                            @if($customer->risk_rating === 'High') bg-red-100 text-red-800
                                            @elseif($customer->risk_rating === 'Medium') bg-yellow-100 text-yellow-800
                                            @else bg-green-100 text-green-800 @endif">
                                            {{ $customer->risk_rating ?? 'N/A' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        @if($customer->pep_status)
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-orange-100 text-orange-800">Yes</span>
                                        @else
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-[#f7f7f8] text-[#6b6b6b]">No</span>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ $customer->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-[#6b6b6b] text-center">{{ $customer->transactions_count }}</td>
                                    <td class="px-4 py-3 text-sm text-[#6b6b6b]">{{ $customer->created_at->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <a href="{{ route('customers.show', $customer) }}" class="text-[#d4a843] hover:underline text-sm">View</a>
                                            <a href="{{ route('customers.edit', $customer) }}" class="text-[#d4a843] hover:underline text-sm">Edit</a>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex justify-center">
                    <nav class="relative z-0 inline-flex -space-x-px rounded-md shadow-sm">
                        @if($customers->onFirstPage())
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#6b6b6b] border border-[#e5e5e5] rounded-l-md">Previous</span>
                        @else
                            <button wire:click="previousPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-l-md hover:bg-[#f7f7f8]">Previous</button>
                        @endif

                        @foreach($customers->getUrlRange(1, $customers->lastPage()) as $page => $url)
                            @if($page == $customers->currentPage())
                                <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-white bg-[#0a0a0a] border border-[#0a0a0a]">{{ $page }}</span>
                            @else
                                <button wire:click="gotoPage({{ $page }})" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] hover:bg-[#f7f7f8]">{{ $page }}</button>
                            @endif
                        @endforeach

                        @if($customers->hasMorePages())
                            <button wire:click="nextPage" class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#171717] bg-white border border-[#e5e5e5] rounded-r-md hover:bg-[#f7f7f8]">Next</button>
                        @else
                            <span class="relative inline-flex items-center px-3 py-2 text-sm font-medium text-[#6b6b6b] border border-[#e5e5e5] rounded-r-md">Next</span>
                        @endif
                    </nav>
                </div>
            @endif
        </div>
    </div>
@endsection