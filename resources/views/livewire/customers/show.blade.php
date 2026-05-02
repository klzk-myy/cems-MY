@extends('layouts.base')

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Customer Details</h2>
                <div class="flex items-center gap-2">
                    <a href="{{ route('customers.edit', $customer) }}" class="px-3 py-1.5 text-sm font-medium text-indigo-600 border border-indigo-600 rounded-lg hover:bg-indigo-50">
                        Edit
                    </a>
                    <a href="{{ route('customers.index') }}" class="px-3 py-1.5 text-sm font-medium text-gray-700 border border-gray-300 rounded-lg hover:bg-gray-50">
                        Back
                    </a>
                </div>
            </div>
        </div>

        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Full Name</label>
                        <p class="text-gray-900 font-medium">{{ $customer->full_name }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">ID Type</label>
                        <p class="text-gray-900">{{ $customer->id_type }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">ID Number</label>
                        <p class="text-gray-900">{{ $customer->ic_number ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Date of Birth</label>
                        <p class="text-gray-900">{{ $customer->date_of_birth ? $customer->date_of_birth->format('d M Y') : 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Nationality</label>
                        <p class="text-gray-900">{{ $customer->nationality }}</p>
                    </div>
                </div>

                <div class="space-y-4">
                    <div>
                        <label class="text-sm text-gray-500">Phone</label>
                        <p class="text-gray-900">{{ $customer->phone ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Email</label>
                        <p class="text-gray-900">{{ $customer->email ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Occupation</label>
                        <p class="text-gray-900">{{ $customer->occupation ?? 'N/A' }}</p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Risk Rating</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                                @if($customer->risk_rating === 'High') bg-red-100 text-red-800
                                @elseif($customer->risk_rating === 'Medium') bg-yellow-100 text-yellow-800
                                @else bg-green-100 text-green-800 @endif">
                                {{ $customer->risk_rating ?? 'N/A' }}
                            </span>
                        </p>
                    </div>
                    <div>
                        <label class="text-sm text-gray-500">Status</label>
                        <p class="mt-1">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full {{ $customer->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                {{ $customer->is_active ? 'Active' : 'Inactive' }}
                            </span>
                        </p>
                    </div>
                </div>
            </div>

            @if($customer->pep_status)
                <div class="mt-6 p-4 bg-orange-50 border border-orange-200 rounded-lg">
                    <span class="text-orange-800 font-medium">Politically Exposed Person (PEP)</span>
                </div>
            @endif

            @if($customer->sanction_hit)
                <div class="mt-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                    <span class="text-red-800 font-medium">Sanction Match Detected - High Risk Customer</span>
                </div>
            @endif

            <div class="mt-6 pt-6 border-t border-gray-200">
                <h3 class="text-sm font-medium text-gray-700 mb-3">Recent Transactions</h3>
                @if($customer->transactions && $customer->transactions->count() > 0)
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                    <th class="px-4 py-2 text-center text-xs font-medium text-gray-500 uppercase">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach($customer->transactions->take(5) as $txn)
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-600">{{ $txn->created_at->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900">{{ $txn->type }}</td>
                                        <td class="px-4 py-2 text-sm text-gray-900 text-right">{{ number_format((float)$txn->amount_local, 2) }}</td>
                                        <td class="px-4 py-2 text-center">
                                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-800">
                                                {{ $txn->status->label() }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-gray-500">No transactions yet.</p>
                @endif
            </div>
        </div>
    </div>
</div>