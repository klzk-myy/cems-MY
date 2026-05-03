<x-app-layout title="Customer Details">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold">{{ $customer->full_name }}</h1>
            <a href="{{ route('customers.index') }}" class="px-4 py-2 border rounded hover:bg-gray-50">Back</a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Personal Information</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Full Name</dt>
                        <dd>{{ $customer->full_name }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ID Type</dt>
                        <dd>{{ $customer->id_type }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">ID Number</dt>
                        <dd>{{ $customer->id_number }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Date of Birth</dt>
                        <dd>{{ $customer->date_of_birth?->format('M d, Y') }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Nationality</dt>
                        <dd>{{ $customer->nationality }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Phone</dt>
                        <dd>{{ $customer->phone ?? 'N/A' }}</dd>
                    </div>
                </dl>
            </div>

            <div class="bg-white rounded-lg shadow p-6">
                <h2 class="text-lg font-semibold mb-4">Risk & Compliance</h2>
                <dl class="space-y-3">
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Risk Level</dt>
                        <dd>
                            <span class="px-2 py-1 rounded text-xs 
                                @if($customer->risk_level === 'Low') bg-green-100 text-green-800
                                @elseif($customer->risk_level === 'Medium') bg-yellow-100 text-yellow-800
                                @else bg-red-100 text-red-800 @endif">
                                {{ $customer->risk_level ?? 'N/A' }}
                            </span>
                        </dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">CDD Level</dt>
                        <dd>{{ $customer->cdd_level ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">KYC Status</dt>
                        <dd>{{ $customer->kyc_status ?? 'N/A' }}</dd>
                    </div>
                    <div class="flex justify-between">
                        <dt class="text-gray-500">Created At</dt>
                        <dd>{{ $customer->created_at?->format('M d, Y') }}</dd>
                    </div>
                </dl>
            </div>
        </div>
    </div>
</x-app-layout>