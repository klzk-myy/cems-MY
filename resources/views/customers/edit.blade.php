<x-app-layout title="Edit Customer">
    <div class="p-6">
        <x-page-header title="Edit Customer" description="Update customer information" class="mb-6" />

        <x-card class="max-w-2xl">
            <form method="POST" action="{{ route('customers.update', $customer ?? 1) }}" class="p-6">
                @csrf
                @method('PUT')

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <x-input name="full_name" label="Full Name" value="{{ old('full_name', $customer->full_name ?? '') }}" required />
                    <x-input type="email" name="email" label="Email" value="{{ old('email', $customer->email ?? '') }}" />

                    <x-select
                        name="id_type"
                        label="ID Type"
                        :options="['IC' => 'NRIC / IC', 'PASSPORT' => 'Passport', 'OTHERS' => 'Other ID']"
                        placeholder="-- Select --"
                        selected="{{ old('id_type', $customer->id_type ?? '') }}"
                        required
                    />
                    <div>
                        <label class="block text-sm font-medium text-ink-muted mb-2">ID Number (masked)</label>
                        <div class="px-4 py-2.5 text-sm bg-canvas-subtle border border-border rounded-lg">
                            {{ $decryptedIdNumber ? substr($decryptedIdNumber, 0, 4).'****'.substr($decryptedIdNumber, -4) : '****-****-****' }}
                        </div>
                    </div>

                    <x-select
                        name="nationality"
                        label="Nationality"
                        :options="['MY' => 'Malaysian', 'SG' => 'Singaporean', 'US' => 'American', 'GB' => 'British', 'OTHER' => 'Other']"
                        placeholder="-- Select --"
                        selected="{{ old('nationality', $customer->nationality ?? '') }}"
                        required
                    />
                    <x-input name="phone" label="Phone Number" value="{{ old('phone', $customer->phone ?? '') }}" />
                </div>

                <div class="mt-6">
                    <label for="address" class="block text-sm font-medium text-ink-muted mb-2">Address</label>
                    <textarea
                        id="address"
                        name="address"
                        rows="2"
                        class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg focus:outline-none focus:ring-2 focus:ring-black/5 focus:border-black"
                    >{{ old('address', $customer->address ?? '') }}</textarea>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <x-input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth', $customer->date_of_birth ?? '') }}" />
                    <x-select
                        name="risk_level"
                        label="Risk Level"
                        :options="['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']"
                        selected="{{ old('risk_level', $customer->risk_level ?? '') }}"
                    />
                </div>

                <div class="mt-6 flex gap-3">
                    <x-button type="submit" variant="primary">Update Customer</x-button>
                    <x-button href="{{ route('customers.show', $customer ?? 1) }}" variant="secondary">Cancel</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
