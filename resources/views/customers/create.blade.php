<x-app-layout title="Create Customer">
    <div class="space-y-6">
        <x-page-header title="Create Customer" description="Add a new customer to the system" />

        <x-card title="Customer Information" description="Enter the customer's details below" class="max-w-2xl">
            <form method="POST" action="{{ route('customers.store') }}">
                @csrf

                <div class="space-y-6">
                    <x-form-row cols="2" gap="4">
                        <x-input name="full_name" label="Full Name" value="{{ old('full_name') }}" required />
                        <x-input type="email" name="email" label="Email" value="{{ old('email') }}" />

                        <x-select
                            name="id_type"
                            label="ID Type"
                            :options="['MyKad' => 'MyKad (Malaysian IC)', 'Passport' => 'Passport', 'Others' => 'Other ID']"
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
                    </x-form-row>

                    <x-textarea
                        name="address"
                        label="Address"
                        rows="2"
                    >{{ old('address') }}</x-textarea>

                    <x-input type="date" name="date_of_birth" label="Date of Birth" value="{{ old('date_of_birth') }}" />


                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-2">
                    <x-button href="{{ route('customers.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Customer</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
