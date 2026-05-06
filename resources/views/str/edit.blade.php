<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit STR - {{ $str->str_number }}</title>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="flex items-center justify-between">
                    <h1 class="text-2xl font-semibold text-gray-900">Edit STR: {{ $str->str_number }}</h1>
                    <a href="{{ route('str.show', $str) }}" class="text-sm text-gray-600 hover:text-gray-900">
                        ← Back to STR
                    </a>
                </div>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                    @if(session('error'))
                        <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                            {{ session('error') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('str.update', $str) }}">
                        @csrf
                        @method('PUT')

                        <input type="hidden" name="reason" value="{{ e('STR edited by ' . auth()->user()->username) }}">

                        <!-- Customer Information (Read-only) -->
                        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Customer Information</h3>
                            @if($str->customer)
                                <dl class="grid grid-cols-2 gap-4">
                                    <div>
                                        <dt class="text-xs text-gray-500">Name</dt>
                                        <dd class="text-sm font-medium text-gray-900">{{ $str->customer->name }}</dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs text-gray-500">ID Number</dt>
                                        <dd class="text-sm font-medium text-gray-900">{{ $str->customer->id_number_masked }}</dd>
                                    </div>
                                </dl>
                            @else
                                <p class="text-sm text-gray-500">No customer linked</p>
                            @endif
                        </div>

                        <!-- Linked Transactions -->
                        <div class="mb-6">
                            <h3 class="text-sm font-medium text-gray-900 mb-3">Linked Transactions</h3>
                            @if($str->transactions->isNotEmpty())
                                <div class="border border-gray-200 rounded-lg overflow-hidden">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Select</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                                <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Amount</th>
                                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Currency</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200">
                                            @foreach($str->transactions as $tx)
                                                <tr>
                                                    <td class="px-4 py-3">
                                                        <input type="checkbox" name="transaction_ids[]" value="{{ $tx->id }}" checked class="rounded border-gray-300">
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $tx->transaction_date?->format('Y-m-d') ?? '-' }}</td>
                                                    <td class="px-4 py-3 text-sm">
                                                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $tx->type->value === 'buy' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700' }}">
                                                            {{ ucfirst($tx->type->value) }}
                                                        </span>
                                                    </td>
                                                    <td class="px-4 py-3 text-sm text-right text-gray-900">{{ number_format((float)$tx->amount, 2) }}</td>
                                                    <td class="px-4 py-3 text-sm text-gray-900">{{ $tx->currency_code }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-500">No transactions linked. Add transaction IDs below.</p>
                            @endif

                            <div class="mt-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Add Transaction IDs (one per line)</label>
                                <textarea name="new_transaction_ids" rows="3" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="Enter additional transaction IDs"></textarea>
                            </div>
                        </div>

                        <!-- Submit -->
                        <div class="flex items-center justify-between pt-6 border-t border-gray-200">
                            <a href="{{ route('str.show', $str) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                Cancel
                            </a>
                            <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                Update STR
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</body>
</html>