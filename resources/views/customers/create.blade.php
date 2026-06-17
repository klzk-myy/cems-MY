<x-app-layout title="Create Customer">
    <div class="p-6">
        <x-page-header title="Create Customer" description="Add a new customer to the system" class="mb-6" />

        <x-card title="Customer Information" description="Enter the customer's details below" class="max-w-2xl">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf

                <div class="p-6 space-y-6">
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

                    <div>
                        <label for="address" class="block text-sm font-medium text-ink-muted mb-2">Address</label>
                        <textarea
                            id="address"
                            name="address"
                            rows="2"
                            class="w-full px-4 py-2.5 text-sm bg-surface border border-border rounded-lg text-ink placeholder:text-ink-muted/50 focus:outline-none focus:ring-2 focus:ring-primary/10 focus:border-primary disabled:bg-canvas-subtle disabled:text-ink-muted @error('address') border-danger @enderror"
                        >{{ old('address') }}</textarea>
                        @error('address')
                            <p class="mt-1 text-sm text-danger-text">{{ $message }}</p>
                        @enderror
                    </div>

                    <x-input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth') }}" />

                    <x-radio-group
                        name="risk_level"
                        label="Risk Level"
                        :options="['low' => 'Low', 'medium' => 'Medium', 'high' => 'High']"
                        :selected="old('risk_level')"
                    />
                </div>

                <div class="px-6 py-4 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('customers.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Customer</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
