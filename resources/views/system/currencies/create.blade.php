<x-app-layout title="Create Currency">
    <div class="space-y-6">
        <x-page-header title="Create Currency" description="Add a new currency to the system" />

        <x-card title="Currency Details" description="ISO alpha-3 codes are uppercase (e.g. USD)">
            <form method="POST" action="{{ route('system.currencies.store') }}">
                @csrf

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input
                            name="code"
                            label="Currency Code"
                            value="{{ old('code') }}"
                            placeholder="USD"
                            maxlength="3"
                            required
                            help="Exactly 3 uppercase letters (ISO alpha-3). Cannot be changed later."
                        />
                        <x-input name="name" label="Name" value="{{ old('name') }}" required />
                        <x-input name="symbol" label="Symbol" value="{{ old('symbol') }}" placeholder="$" />
                        <x-select
                            name="decimal_places"
                            label="Decimal Places"
                            :options="[0 => '0', 1 => '1', 2 => '2', 3 => '3', 4 => '4']"
                            :selected="(string) old('decimal_places', '2')"
                            required
                        />
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('system.currencies.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Create Currency</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
