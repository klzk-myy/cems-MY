<x-app-layout title="Open Counter - {{ $counter->code }}">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-6">
        <x-page-header title="Open Counter: {{ $counter->code }}" />

        <x-card>
            <x-card-section title="Counter Details">
                <dl class="mt-4 grid grid-cols-2 gap-4">
                    <div>
                        <dt class="text-sm text-ink-muted">Counter Code</dt>
                        <dd class="text-sm font-medium text-ink">{{ $counter->code }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-muted">Branch</dt>
                        <dd class="text-sm font-medium text-ink">{{ $counter->branch->name ?? '-' }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-ink-muted">Status</dt>
                        <dd class="text-sm font-medium text-ink">{{ $counter->status }}</dd>
                    </div>
                </dl>
            </x-card-section>

            @if(session('error'))
                <x-alert type="error">{{ session('error') }}</x-alert>
            @endif

            <form method="POST" action="{{ route('counters.open', $counter) }}">
                @csrf

                <x-card-section title="Opening Floats">
                    <div class="space-y-4" id="opening-floats">
                        @foreach($currencies as $currency)
                            <div class="flex items-center gap-4">
                                <label class="w-20 text-sm font-medium text-ink-muted">{{ $currency->code }}</label>
                                <x-input type="text" name="opening_floats[{{ $currency->code }}]" class="flex-1 w-full" placeholder="0.00" value="{{ old('opening_floats.' . $currency->code, '0.00') }}" inputmode="decimal" inline />
                                @error('opening_floats.' . $currency->code)
                                    <p class="text-sm text-danger-text">{{ $message }}</p>
                                @enderror
                            </div>
                        @endforeach
                    </div>
                </x-card-section>

                <div class="flex items-center justify-end gap-4">
                    <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                    <x-button type="submit" variant="primary">Open Counter</x-button>
                </div>
            </form>
        </x-card>
    </div>
</x-app-layout>
