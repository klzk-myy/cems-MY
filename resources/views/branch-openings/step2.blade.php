@extends('layouts.app')

@section('title', 'Step 2: Currency Pools Setup')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Step 2: Currency Pools Setup</h1>
                <div class="text-sm text-gray-600">Step 2 of 3</div>
            </div>

            <p class="text-gray-600 mb-6">
                Set initial balances for each currency in the branch pool. These amounts will be available for teller allocations.
            </p>

            <form action="{{ route('branch-openings.step2.process', $branch->id) }}" method="POST">
                @csrf

                <div class="space-y-4 mb-8">
                    @foreach($currencies as $currency)
                    <div class="border rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="font-semibold">{{ $currency->code }} - {{ $currency->name }}</h3>
                                <p class="text-sm text-gray-500">Symbol: {{ $currency->symbol }}</p>
                            </div>
                            <div class="w-48">
                                <label for="pool_{{ $currency->code }}" class="block text-sm font-medium text-gray-700">
                                    Initial Amount
                                </label>
                                <input type="number" name="pool_{{ $currency->code }}" id="pool_{{ $currency->code }}" 
                                       value="{{ old("pool_{$currency->code}", $existingPools[$currency->code] ?? 0) }}"
                                       step="0.01" min="0"
                                       class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            </div>
                        </div>
                    </div>
                    @endforeach
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <a href="{{ route('branch-openings.step1') }}" class="text-gray-600 hover:text-gray-800">
                        Back to Step 1
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                        Continue to Step 3
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
