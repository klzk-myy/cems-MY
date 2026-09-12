<x-app-layout title="Edit Currency">
    <div class="space-y-6">
        <x-page-header title="Edit Currency" description="Update {{ $currency->code }} details" />

        @if(session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        <x-card title="Currency Details" description="The currency code cannot be changed once created">
            <form method="POST" action="{{ route('system.currencies.update', $currency) }}">
                @csrf
                @method('PUT')

                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <x-input name="code_display" label="Currency Code" value="{{ $currency->code }}" readonly disabled />
                        <x-input name="name" label="Name" value="{{ old('name', $currency->name) }}" required />
                        <x-input name="symbol" label="Symbol" value="{{ old('symbol', $currency->symbol) }}" />
                        <x-select
                            name="decimal_places"
                            label="Decimal Places"
                            :options="[0 => '0', 1 => '1', 2 => '2', 3 => '3', 4 => '4']"
                            :selected="(string) old('decimal_places', (string) $currency->decimal_places)"
                            required
                        />
                    </div>
                </div>

                <div class="px-5 py-3 border-t border-border flex items-center justify-end gap-3">
                    <x-button href="{{ route('system.currencies.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Save Changes</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
