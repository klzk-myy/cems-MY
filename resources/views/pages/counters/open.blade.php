<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Open Counter - {{ $counter->code }}</title>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">Open Counter: {{ $counter->code }}</h1>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    <div class="mb-6">
                        <h2 class="text-lg font-medium text-gray-900">Counter Details</h2>
                        <dl class="mt-4 grid grid-cols-2 gap-4">
                            <div>
                                <dt class="text-sm text-gray-500">Counter Code</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $counter->code }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Branch</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $counter->branch->name ?? '-' }}</dd>
                            </div>
                            <div>
                                <dt class="text-sm text-gray-500">Status</dt>
                                <dd class="text-sm font-medium text-gray-900">{{ $counter->status }}</dd>
                            </div>
                        </dl>
                    </div>

                    @if(session('error'))
                        <x-alert type="error">{{ session('error') }}</x-alert>
                    @endif

                    <form method="POST" action="{{ route('counters.open', $counter) }}">
                        @csrf

                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-4">Opening Floats</h3>
                            <div class="space-y-4" id="opening-floats">
                                @foreach($currencies as $currency)
                                    <div class="flex items-center gap-4">
                                        <label class="w-20 text-sm font-medium text-gray-700">{{ $currency->code }}</label>
                                        <x-input type="text" name="opening_floats[{{ $currency->code }}]" class="flex-1 w-full" placeholder="0.00" value="{{ old('opening_floats.' . $currency->code, '0.00') }}" inputmode="decimal" />
                                        @error('opening_floats.' . $currency->code)
                                            <p class="text-sm text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex items-center justify-end gap-4">
                            <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                            <x-button type="submit" variant="primary">Open Counter</x-button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>