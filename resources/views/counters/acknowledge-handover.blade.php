<x-app-layout title="Acknowledge Handover">
    <div class="p-6 space-y-6">
        <x-page-header
            title="Acknowledge Counter Handover"
            description="Confirm receipt of counter custody from previous operator"
        />

        <x-card title="Handover Details" class="max-w-lg">
            <x-card-section>
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
                        <span class="text-ink-muted">Date &amp; Time</span>
                        <p class="font-medium">{{ $handover?->created_at?->format('d M Y, h:i A') ?? now()->format('d M Y, h:i A') }}</p>
                    </div>
                    <div>
                        <span class="text-ink-muted">Your Name</span>
                        <p class="font-medium">{{ $handover?->to_user?->name ?? auth()->user()?->name ?? 'Unknown User' }}</p>
                    </div>
                </div>
            </x-card-section>

            <x-card-section title="Opening Float">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Currency</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-ink-muted uppercase">Amount</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        <tr>
                            <td class="px-4 py-3 text-sm text-ink-muted">MYR Cash</td>
                            <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format($handover?->float_amount ?? 0, 2) }}</td>
                        </tr>
                        @isset($handover->currency_floats)
                            @foreach($handover->currency_floats as $currency => $amount)
                                <tr>
                                    <td class="px-4 py-3 text-sm text-ink-muted">{{ $currency }}</td>
                                    <td class="px-4 py-3 text-sm text-right font-medium">{{ number_format($amount, 2) }}</td>
                                </tr>
                            @endforeach
                        @endisset
                    </x-slot:tbody>
                </x-table>
            </x-card-section>

            @isset($notes)
                <x-card-section title="Notes">
                    <p class="text-sm text-ink-muted bg-canvas-subtle rounded-lg p-3">{{ $notes }}</p>
                </x-card-section>
            @endisset

            <x-card-section>
                <form method="POST" action="{{ route('counters.handover.acknowledge', $counter) }}">
                    @csrf
                    <div class="mb-4">
                        <x-input type="password" name="pin" label="Your PIN" required inline />
                    </div>
                    <div class="flex gap-3">
                        <x-button type="submit" variant="primary" class="flex-1">Confirm Receipt</x-button>
                        <x-button href="{{ route('counters.index') }}" variant="secondary">Cancel</x-button>
                    </div>
                </form>
            </x-card-section>
        </x-card>
    </div>
</x-app-layout>
