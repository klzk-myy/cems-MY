<x-app-layout title="Setup">
    <x-page-header title="Setup" description="Initial system setup" />

    <x-card>
        <form method="POST" action="{{ route('setup.quick') }}" class="space-y-4">
            @csrf
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <x-input name="company_name" label="Company Name" :required="true" />
                <x-input name="business_registration" label="Business Registration No." :required="true" />
            </div>
            <x-textarea name="business_address" label="Business Address" :required="true" />
            <div class="space-y-2">
                <x-checkbox name="currency_codes[]" label="USD" :checked="true" />
                <x-checkbox name="currency_codes[]" label="EUR" :checked="true" />
                <x-checkbox name="currency_codes[]" label="SGD" />
            </div>
            <x-checkbox name="use_default_rates" label="Use Default Rates" :checked="true" />
            <div class="flex justify-end gap-3">
                <x-button type="submit" variant="primary" class="bg-primary text-on-primary">Complete Setup</x-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
