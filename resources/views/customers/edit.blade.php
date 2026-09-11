<x-app-layout title="Edit Customer">
    <x-page-header title="Edit Customer" description="Update customer information" />

    <x-card>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="name" label="Full Name" value="{{ $customer->full_name }}" :required="true" />
                <x-input name="email" label="Email" type="email" value="{{ $customer->email }}" :required="true" />
                <x-input name="phone" label="Phone" value="{{ $customer->phone }}" />
                <x-select name="id_type" label="ID Type" :options="$idTypes" value="{{ $customer->id_type }}" :required="true" />
                <x-input name="id_number" label="ID Number" value="{{ $decryptedIdNumber }}" :required="true" />
                <x-select name="nationality" label="Nationality" :options="$nationalities" value="{{ $customer->nationality }}" />
                <x-select name="risk_rating" label="Risk Rating" :options="$riskRatings" value="{{ $customer->risk_rating }}" />
                <x-input name="birth_date" label="Date of Birth" type="date" value="{{ $customer->birth_date ? $customer->birth_date->toDateString() : '' }}" />
            </div>
            <x-textarea name="address" label="Address" value="{{ $customer->address }}" />
            <x-textarea name="notes" label="Notes" value="{{ $customer->notes }}" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('customers.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Update Customer</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
