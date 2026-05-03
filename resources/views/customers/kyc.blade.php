<x-app-layout title="Customer KYC">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold">Customer KYC</h1>
                <p class="text-gray-500 text-sm mt-1">{{ $customer->full_name ?? 'Customer Name' }} - Know Your Customer documentation</p>
            </div>
            <a href="{{ route('customers.show', $customer ?? 1) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                Back to Customer
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
                    <div class="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <span class="text-gray-500">Full Name</span>
                            <p class="font-medium">{{ $customer->full_name ?? 'Ahmad bin Abu' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">ID Type</span>
                            <p class="font-medium">{{ $customer->id_type ?? 'IC' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">ID Number</span>
                            <p class="font-medium">{{ $customer->id_number ?? '701203-14-1234' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Nationality</span>
                            <p class="font-medium">{{ $customer->nationality ?? 'MY' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Date of Birth</span>
                            <p class="font-medium">{{ $customer->date_of_birth?->format('d M Y') ?? '15 March 1970' }}</p>
                        </div>
                        <div>
                            <span class="text-gray-500">Risk Level</span>
                            <p class="font-medium">
                                @if(($customer->risk_level ?? '') === 'high')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-red-100 text-red-700">High</span>
                                @elseif(($customer->risk_level ?? '') === 'medium')
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Medium</span>
                                @else
                                    <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Low</span>
                                @endif
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-semibold mb-4">KYC Documents</h2>
                    <table class="w-full">
                        <thead>
                            <tr class="text-left text-sm text-gray-500 border-b">
                                <th class="pb-3">Document Type</th>
                                <th class="pb-3">Status</th>
                                <th class="pb-3">Uploaded</th>
                                <th class="pb-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($kycDocuments ?? [] as $doc)
                            <tr class="border-b">
                                <td class="py-3 text-sm">{{ $doc->type }}</td>
                                <td class="py-3">
                                    @if($doc->verified)
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Verified</span>
                                    @else
                                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded bg-yellow-100 text-yellow-700">Pending</span>
                                    @endif
                                </td>
                                <td class="py-3 text-sm text-gray-500">{{ $doc->uploaded_at?->format('d M Y') ?? 'N/A' }}</td>
                                <td class="py-3">
                                    <button class="text-blue-600 hover:underline text-sm">View</button>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">
                                    <div class="mb-4">No KYC documents uploaded yet.</div>
                                    <button class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Upload Document
                                    </button>
                                </td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h2 class="text-lg font-semibold mb-4">CDD Assessment</h2>
                    <div class="grid grid-cols-2 gap-4 mb-4">
                        <div>
                            <span class="text-gray-500 text-sm">CDD Level</span>
                            <p class="font-medium text-lg">
                                @if(($customer->cdd_level ?? '') === 'enhanced')
                                    <span class="text-red-600">Enhanced</span>
                                @elseif(($customer->cdd_level ?? '') === 'standard')
                                    <span class="text-yellow-600">Standard</span>
                                @else
                                    <span class="text-green-600">Simplified</span>
                                @endif
                            </p>
                        </div>
                        <div>
                            <span class="text-gray-500 text-sm">Last Assessment</span>
                            <p class="font-medium text-lg">{{ $customer->cdd_assessed_at?->format('d M Y') ?? 'N/A' }}</p>
                        </div>
                    </div>

                    <h3 class="text-sm font-medium text-gray-700 mb-2">Transaction Summary (90 days)</h3>
                    <div class="bg-gray-50 rounded-lg p-4">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-2xl font-bold">{{ $transactionStats['count'] ?? 12 }}</div>
                                <div class="text-xs text-gray-500">Transactions</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold">RM {{ number_format($transactionStats['total'] ?? 85000, 2) }}</div>
                                <div class="text-xs text-gray-500">Total Value</div>
                            </div>
                            <div>
                                <div class="text-2xl font-bold">{{ $transactionStats['currencies'] ?? 4 }}</div>
                                <div class="text-xs text-gray-500">Currencies</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h3 class="text-sm font-semibold mb-4">Compliance Alerts</h3>
                    @forelse($alerts ?? [] as $alert)
                    <div class="mb-3 p-3 rounded-lg {{ $alert->severity === 'high' ? 'bg-red-50' : 'bg-yellow-50' }}">
                        <div class="text-sm font-medium">{{ $alert->type }}</div>
                        <div class="text-xs text-gray-500">{{ $alert->created_at->format('d M Y') }}</div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500">No active alerts.</p>
                    @endforelse
                </div>

                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <h3 class="text-sm font-semibold mb-4">Actions</h3>
                    <div class="space-y-2">
                        <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] text-left">
                            Run CDD Assessment
                        </button>
                        <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50 text-left">
                            Request EDD
                        </button>
                        <button class="w-full px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50 text-left">
                            Update Risk Score
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>