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

                    <x-textarea
                        name="address"
                        label="Address"
                        :required="$errors->has('address') ? true : false"
                        rows="2"
                    >{{ old('address') }}</x-textarea>

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
