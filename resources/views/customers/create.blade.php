<x-app-layout title="Create Customer">
    <div class="p-6">
        <x-page-header title="Create Customer" description="Add a new customer to the system" class="mb-6" />

        <x-card class="max-w-2xl">
            <form method="POST" action="{{ route('customers.store') }}" class="p-6">
                @csrf

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-input name="full_name" label="Full Name" value="{{ old('full_name') }}" required />
                    <x-input type="email" name="email" label="Email" value="{{ old('email') }}" />

                    <x-select
                        name="id_type"
                        label="ID Type"
                        :options="['IC' => 'NRIC / IC', 'PASSPORT' => 'Passport', 'MILITARY' => 'Military ID']"
                        placeholder="-- Select --"
                        required
                    />
                    <x-input name="id_number" label="ID Number" value="{{ old('id_number') }}" required />

                    <x-select
                        name="nationality"
                        label="Nationality"
                        :options="['MY' => 'Malaysian', 'SG' => 'Singaporean', 'US' => 'American', 'GB' => 'British', 'OTHER' => 'Other']"
                        placeholder="-- Select --"
                        required
                    />
                    <x-input name="phone" label="Phone Number" value="{{ old('phone') }}" />
                </div>

                <div class="mt-6">
                    <label for="address" class="block text-sm font-medium text-ink-muted mb-2">Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="2"
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black"
                    >{{ old('address') }}</textarea>
                </div>

                <x-input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth') }}" class="mt-6" />

                <div class="mt-6">
                    <label class="block text-sm font-medium text-ink-muted mb-2">Risk Level</label>
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

                <div class="mt-6 flex gap-3">
                    <x-button type="submit" variant="primary">Create Customer</x-button>
                    <x-button href="{{ route('customers.index') }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
