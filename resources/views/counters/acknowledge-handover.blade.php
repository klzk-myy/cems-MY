<x-app-layout title="Acknowledge Handover">
    <div class="p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold">Acknowledge Counter Handover</h1>
            <p class="text-ink-muted text-sm mt-1">Confirm receipt of counter custody from previous operator</p>
        </div>

        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6 max-w-lg">
            <div class="mb-6">
                <h2 class="text-lg font-semibold mb-4">Handover Details</h2>
                <div class="grid grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-ink-muted">Counter</span>
                        <p class="font-medium">{{ $counter?->name ?? 'Unknown Counter' }}</p>
                    </div>
                    <div>
                        <span class="text-ink-muted">Handed Over By</span>
                        <p class="font-medium">{{ $handover?->from_user?->name ?? 'Unknown User' }}</p>
                    </div>
                    <div>
                        <span class="text-ink-muted">Date & Time</span>
                        <p class="font-medium">{{ $handover?->created_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                    </div>
                    <div>
                        <span class="text-ink-muted">Your Name</span>
                        <p class="font-medium">{{ $handover?->to_user?->name ?? auth()->user()?->name ?? 'Unknown User' }}</p>
                    </div>
                </div>
            </div>

            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Opening Float</h3>
                <div class="bg-canvas-subtle rounded-lg p-4">
                    <table class="w-full text-sm">
                        <tr>
                            <td class="text-ink-muted">MYR Cash</td>
                            <td class="text-right font-medium">{{ number_format($handover?->float_amount ?? 0, 2) }}</td>
                        </tr>
                        @isset($handover->currency_floats)
                        @foreach($handover->currency_floats as $currency => $amount)
                        <tr>
                            <td class="text-ink-muted">{{ $currency }}</td>
                            <td class="text-right font-medium">{{ number_format($amount, 2) }}</td>
                        </tr>
                        @endforeach
                        @endisset
                    </table>
                </div>
            </div>

            @isset($notes)
            <div class="mb-6">
                <h3 class="text-sm font-medium text-gray-700 mb-2">Notes</h3>
                <p class="text-sm text-gray-600 bg-canvas-subtle rounded-lg p-3">{{ $notes }}</p>
            </div>
            @endisset

            <form method="POST" action="{{ route('counters.handover.acknowledge', $counter) }}">
                @csrf
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Your PIN</label>
                    <input type="password" name="pin" class="w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                </div>
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                        Confirm Receipt
                    </button>
                    <a href="{{ route('counters.index') }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] hover:bg-canvas-subtle">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>