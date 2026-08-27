<x-app-layout title="Edit Customer">
    <x-page-header title="Edit Customer" description="Update customer information" />

    <x-card>
        <form method="POST" action="{{ route('customers.update', $customer) }}" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="name" label="Full Name" :required="true" />
                <x-input name="email" label="Email" type="email" :required="true" />
                <x-input name="phone" label="Phone" />
                <x-select name="id_type" label="ID Type" :options="['ic' => 'IC', 'passport' => 'Passport']" :required="true" />
                <x-input name="id_number" label="ID Number" :required="true" />
            </div>
            <x-textarea name="address" label="Address" />
            <x-textarea name="notes" label="Notes" />
            <div class="flex justify-end gap-3">
                <a href="{{ route('customers.index') }}"><x-button variant="secondary">Cancel</x-button></a>
                <x-button type="submit" variant="primary">Update Customer</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
