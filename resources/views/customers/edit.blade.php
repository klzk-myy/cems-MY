<x-app-layout title="Edit Customer">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Edit Customer</h1>
            <p class="text-gray-500 text-sm mt-1">Update customer information</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-2xl">
            <form method="POST" action="{{ route('customers.update', $customer ?? 1) }}">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" value="{{ old('full_name', $customer->full_name ?? '') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email', $customer->email ?? '') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Type *</label>
                        <select name="id_type" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                            <option value="IC" {{ ($customer->id_type ?? '') === 'IC' ? 'selected' : '' }}>NRIC / IC</option>
                            <option value="PASSPORT" {{ ($customer->id_type ?? '') === 'PASSPORT' ? 'selected' : '' }}>Passport</option>
                            <option value="OTHERS" {{ ($customer->id_type ?? '') === 'Others' ? 'selected' : '' }}>Other ID</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number (masked)</label>
                        <div class="px-4 py-2.5 text-sm bg-gray-50 border border-[#e5e5e5] rounded-lg">
                            {{ $decryptedIdNumber ? substr($decryptedIdNumber, 0, 4).'****'.substr($decryptedIdNumber, -4) : '****-****-****' }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality *</label>
                        <select name="nationality" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                            <option value="MY" {{ ($customer->nationality ?? '') === 'MY' ? 'selected' : '' }}>Malaysian</option>
                            <option value="SG" {{ ($customer->nationality ?? '') === 'SG' ? 'selected' : '' }}>Singaporean</option>
                            <option value="US" {{ ($customer->nationality ?? '') === 'US' ? 'selected' : '' }}>American</option>
                            <option value="GB" {{ ($customer->nationality ?? '') === 'GB' ? 'selected' : '' }}>British</option>
                            <option value="OTHER" {{ ($customer->nationality ?? '') === 'OTHER' ? 'selected' : '' }}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone', $customer->phone ?? '') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('address', $customer->address ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                        <input type="date" name="date_of_birth" value="{{ old('date_of_birth', $customer->date_of_birth ?? '') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Risk Level</label>
                        <select name="risk_level" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                            <option value="low" {{ ($customer->risk_level ?? '') === 'low' ? 'selected' : '' }}>Low</option>
                            <option value="medium" {{ ($customer->risk_level ?? '') === 'medium' ? 'selected' : '' }}>Medium</option>
                            <option value="high" {{ ($customer->risk_level ?? '') === 'high' ? 'selected' : '' }}>High</option>
                        </select>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Update Customer
                    </button>
                    <a href="{{ route('customers.show', $customer ?? 1) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>