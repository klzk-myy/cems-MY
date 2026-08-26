<x-app-layout title="Customer Screening">
    <div class="space-y-6">
        <x-page-header
            title="Customer Screening"
            :description="'Sanctions screening profile for '.$customer->full_name"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('customers.show', $customer) }}">
                    View Customer Profile
                </x-button>
                <form method="POST" action="{{ route('compliance.screening.screen', $customer->id) }}">
                    @csrf
                    <x-button variant="primary" type="submit">Re-screen Customer</x-button>
                </form>
            </x-slot:actions>
        </x-page-header>

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @if (session('warning'))
            <x-alert type="warning">{{ session('warning') }}</x-alert>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Customer Information">
                <dl class="space-y-4">
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Name</dt>
                        <dd class="text-sm text-ink font-medium text-right">{{ $customer->full_name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Customer ID</dt>
                        <dd class="text-sm text-ink">#{{ $customer->id }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">ID Type / Number</dt>
                        <dd class="text-sm text-ink">{{ $customer->id_type }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Nationality</dt>
                        <dd class="text-sm text-ink">{{ $customer->nationality }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Risk Rating</dt>
                        <dd><x-risk-badge :customer="$customer" /></dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">PEP Status</dt>
                        <dd>
                            @if ($customer->pep_status)
                                <x-badge variant="purple">PEP</x-badge>
                            @else
                                <span class="text-sm text-ink-muted">Not a PEP</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Account Status</dt>
                        <dd>
                            @if ($customer->is_frozen)
                                <x-badge variant="danger">Frozen</x-badge>
                            @elseif (! $customer->is_active)
                                <x-badge variant="gray">Inactive</x-badge>
                            @else
                                <x-badge variant="success">Active</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Current Screening Status">
                @php
                    $resultVariant = match ($status['last_result'] ?? null) {
                        'clear' => 'success',
                        'flag' => 'warning',
                        'block' => 'danger',
                        default => 'gray',
                    };
                @endphp
                <dl class="space-y-4">
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-sm text-ink-muted">Latest Result</dt>
                        <dd>
                            @isset($status['last_result'])
                                <x-badge :variant="$resultVariant">{{ ucfirst($status['last_result']) }}</x-badge>
                            @else
                                <x-badge variant="gray">Never Screened</x-badge>
                            @endisset
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Last Match Score</dt>
                        <dd class="text-sm text-ink tabular-nums">
                            @isset($status['last_match_score'])
                                {{ number_format($status['last_match_score'], 1) }}%
                            @else
                                —
                            @endisset
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-sm text-ink-muted">Last Screened At</dt>
                        <dd class="text-sm text-ink">
                            @isset($status['last_screened_at'])
                                {{ \Illuminate\Support\Carbon::parse($status['last_screened_at'])->format('d M Y H:i') }}
                            @else
                                —
                            @endisset
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center">
                        <dt class="text-sm text-ink-muted">Sanctions Hit Flag</dt>
                        <dd>
                            @if ($status['sanction_hit'])
                                <x-badge variant="danger">Hit</x-badge>
                            @else
                                <x-badge variant="success">Clear</x-badge>
                            @endif
                        </dd>
                    </div>
                </dl>

                <div class="mt-6 pt-4 border-t border-border">
                    <a href="{{ route('compliance.screening.matches.index', ['customer_id' => $customer->id]) }}"
                       class="text-sm text-primary hover:underline">
                        View pending screening matches for this customer &rarr;
                    </a>
                </div>
            </x-card>
        </div>

        <x-card title="Screening History">
            <div class="overflow-x-auto">
                <x-table>
                    <x-slot:thead>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Date</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Screened Name</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Match Score</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Result</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-ink-muted uppercase">Matched Fields</th>
                    </x-slot:thead>
                    <x-slot:tbody>
                        @forelse ($history as $row)
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink-muted whitespace-nowrap">
                                    {{ \Illuminate\Support\Carbon::parse($row['created_at'])->format('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 text-sm text-ink">{{ $row['screened_name'] ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-ink tabular-nums">
                                    @isset($row['match_score'])
                                        {{ number_format((float) $row['match_score'] * 100, 1) }}%
                                    @else
                                        —
                                    @endisset
                                </td>
                                <td class="px-4 py-3 text-sm">
                                    @php
                                        $variant = match ($row['result'] ?? null) {
                                            'clear' => 'success',
                                            'flag' => 'warning',
                                            'block' => 'danger',
                                            default => 'gray',
                                        };
                                    @endphp
                                    <x-badge :variant="$variant">{{ ucfirst($row['result'] ?? 'Unknown') }}</x-badge>
                                </td>
                                <td class="px-4 py-3 text-sm text-ink-muted">
                                    @if (! empty($row['matched_fields']))
                                        {{ collect($row['matched_fields'])
                                            ->map(fn ($v, $k) => is_array($v) ? $k : (is_string($k) ? $k.': '.$v : $v))
                                            ->implode(', ') }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <x-empty-state message="No screening history recorded for this customer yet." :colspan="5" />
                        @endforelse
                    </x-slot:tbody>
                </x-table>
            </div>
        </x-card>
    </div>
</x-app-layout>
