<x-app-layout title="Create Customer">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Create New Customer</h1>
            <p class="text-gray-500 text-sm mt-1">Add a new customer to the system</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-2xl">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                        <input type="text" name="full_name" value="{{ old('full_name') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input type="email" name="email" value="{{ old('email') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Type *</label>
                        <select name="id_type" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                            <option value="">-- Select --</option>
                            <option value="IC">NRIC / IC</option>
                            <option value="PASSPORT">Passport</option>
                            <option value="MILITARY">Military ID</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">ID Number *</label>
                        <input type="text" name="id_number" value="{{ old('id_number') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nationality *</label>
                        <select name="nationality" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                            <option value="">-- Select --</option>
                            <option value="MY">Malaysian</option>
                            <option value="SG">Singaporean</option>
                            <option value="US">American</option>
                            <option value="GB">British</option>
                            <option value="OTHER">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Phone Number</label>
                        <input type="text" name="phone" value="{{ old('phone') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Address</label>
                    <textarea name="address" rows="2" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">{{ old('address') }}</textarea>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
                    <input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                </div>

                <div class="mb-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Risk Level</label>
                    <div class="flex gap-4">
                        <label class="flex items-center gap-2">
                            <input type="radio" name="risk_level" value="low" class="text-blue-600">
                            <span class="text-sm">Low</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="risk_level" value="medium" class="text-blue-600">
                            <span class="text-sm">Medium</span>
                        </label>
                        <label class="flex items-center gap-2">
                            <input type="radio" name="risk_level" value="high" class="text-blue-600">
                            <span class="text-sm">High</span>
                        </label>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Create Customer
                    </button>
                    <a href="{{ route('customers.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-gray-50">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>