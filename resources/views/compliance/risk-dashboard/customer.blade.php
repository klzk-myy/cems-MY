<x-app-layout title="Customer Risk Dashboard">
    <div class="space-y-6"
         x-data="riskCustomer">
        <x-page-header
            title="Customer Risk Dashboard"
            :description="'Risk assessment for '.$customer->full_name"
        >
            <x-slot:actions>
                <x-button variant="secondary" href="{{ route('compliance.risk-dashboard.index') }}">
                    Back to Dashboard
                </x-button>
            </x-slot:actions>
        </x-page-header>

        @if (session('success'))
            <x-alert type="success">{{ session('success') }}</x-alert>
        @endif

        @if (session('error'))
            <x-alert type="danger">{{ session('error') }}</x-alert>
        @endif

        @php
            $snapshot = $customer->latestRiskSnapshot;
            $currentScore = $trends['current_score'] ?? $snapshot?->overall_score ?? $customer->risk_score;
        @endphp

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <x-card title="Customer Profile" class="lg:col-span-2">
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex justify-between gap-4 md:block">
                        <dt class="text-sm text-ink-muted">Customer</dt>
                        <dd class="text-sm text-ink font-medium">
                            <x-customer-link :customer="$customer" />
                            <span class="text-xs text-ink-muted">#{{ $customer->id }}</span>
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 md:block">
                        <dt class="text-sm text-ink-muted">Customer Type</dt>
                        <dd class="text-sm text-ink">{{ ucfirst($customer->customer_type) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center md:block">
                        <dt class="text-sm text-ink-muted mb-1">Risk Rating</dt>
                        <dd><x-risk-badge :customer="$customer" /></dd>
                    </div>
                    <div class="flex justify-between gap-4 md:block">
                        <dt class="text-sm text-ink-muted">CDD Level</dt>
                        <dd class="text-sm text-ink">{{ $customer->cdd_level_label }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center md:block">
                        <dt class="text-sm text-ink-muted mb-1">PEP Status</dt>
                        <dd>
                            @if ($customer->pep_status)
                                <x-badge variant="purple">PEP</x-badge>
                            @else
                                <x-badge variant="gray">Not a PEP</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 items-center md:block">
                        <dt class="text-sm text-ink-muted mb-1">Account Status</dt>
                        <dd>
                            @if ($customer->is_frozen)
                                <x-badge variant="danger">Frozen</x-badge>
                            @elseif ($customer->transactions_blocked)
                                <x-badge variant="warning">Transactions Blocked</x-badge>
                            @elseif (! $customer->is_active)
                                <x-badge variant="gray">Inactive</x-badge>
                            @else
                                <x-badge variant="success">Active</x-badge>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between gap-4 md:block">
                        <dt class="text-sm text-ink-muted">Current Score</dt>
                        <dd class="text-2xl font-bold tabular-nums text-ink">{{ $currentScore }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 md:block">
                        <dt class="text-sm text-ink-muted">Next Screening Date</dt>
                        <dd class="text-sm text-ink">
                            {{ $snapshot?->next_screening_date?->format('d M Y') ?? 'Not scheduled' }}
                        </dd>
                    </div>
                </dl>
            </x-card>

            <x-card title="Actions">
                <div class="flex flex-col gap-3">
                    <x-button variant="secondary" href="{{ route('customers.show', $customer) }}">
                        View Full Profile
                    </x-button>
                    <x-button variant="secondary" href="{{ route('compliance.screening.show', $customer->id) }}">
                        Screening Details
                    </x-button>
                    @if (auth()->user()?->role->canPerform(\App\Enums\Permission::ManageRiskScreening))
                        <x-button variant="primary" type="button" @click="showRescreen = true">
                            Trigger Re-screening
                        </x-button>
                    @endif
                </div>
            </x-card>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <x-card title="Risk Score Trend (Last 6 Months)">
                @if (count($trends['snapshots'] ?? []) > 0)
                    <x-chart-trend
                        title=""
                        :labels="collect($trends['snapshots'])->map(fn ($s) => \Illuminate\Support\Carbon::parse($s['date'])->format('M j'))->all()"
                        :values="collect($trends['snapshots'])->map(fn ($s) => (int) $s['score'])->all()"
                        color="red"
                    />
                @else
                    <p class="text-sm text-ink-muted py-8 text-center">No risk score snapshots recorded in the last 6 months.</p>
                @endif
            </x-card>

            <x-card title="Latest Risk Factor Breakdown">
                @if ($snapshot)
                    <div class="space-y-4">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-ink-muted">Transaction Velocity</span>
                                <span class="font-medium text-ink tabular-nums">{{ $snapshot->velocity_score }}</span>
                            </div>
                            <x-progress-bar :value="$snapshot->velocity_score" />
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-ink-muted">Structuring</span>
                                <span class="font-medium text-ink tabular-nums">{{ $snapshot->structuring_score }}</span>
                            </div>
                            <x-progress-bar :value="$snapshot->structuring_score" />
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-ink-muted">Geographic</span>
                                <span class="font-medium text-ink tabular-nums">{{ $snapshot->geographic_score }}</span>
                            </div>
                            <x-progress-bar :value="$snapshot->geographic_score" />
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span class="text-ink-muted">Amount</span>
                                <span class="font-medium text-ink tabular-nums">{{ $snapshot->amount_score }}</span>
                            </div>
                            <x-progress-bar :value="$snapshot->amount_score" />
                        </div>
                        <p class="pt-2 border-t border-border text-xs text-ink-muted">
                            Snapshot date: {{ $snapshot->snapshot_date?->format('d M Y') }}
                            @isset($snapshot->trend)
                                · Trend: {{ ucfirst($snapshot->trend->value) }}
                            @endisset
                        </p>
                    </div>
                @else
                    <p class="text-sm text-ink-muted py-8 text-center">No risk score snapshot available for this customer.</p>
                @endif
            </x-card>
        </div>

        <x-card title="Risk History">
            @forelse ($customer->riskHistory as $entry)
                <div class="p-3 bg-canvas-subtle rounded-lg">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="font-medium tabular-nums">{{ $entry->old_score }} → {{ $entry->new_score }}</span>
                            @if ($entry->old_rating?->value !== $entry->new_rating?->value)
                                <x-badge :variant="$entry->new_rating?->color() ?? 'gray'">
                                    {{ $entry->old_rating?->value ?? '-' }} → {{ $entry->new_rating?->value ?? '-' }}
                                </x-badge>
                            @endif
                        </div>
                        <span class="text-xs text-ink-muted">
                            {{ $entry->created_at?->format('d M Y H:i') }}
                            · {{ $entry->assessor?->name ?? 'System' }}
                        </span>
                    </div>
                    <div class="text-xs text-ink-muted mt-1">Trigger: {{ ucwords(str_replace('_', ' ', $entry->change_reason)) }}</div>
                </div>
            @empty
                <p class="text-sm text-ink-muted py-4 text-center">No risk score changes recorded yet.</p>
            @endforelse
        </x-card>

        @if (auth()->user()?->role->canPerform(\App\Enums\Permission::ManageRiskScreening))
            <div x-show="showRescreen" x-cloak @keydown.escape.window="showRescreen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50" @click="showRescreen = false"></div>
                <div x-show="showRescreen"
                     x-transition
                     class="relative bg-surface border border-border rounded-xl shadow-lg w-full max-w-md p-6">
                    <h3 class="text-lg font-semibold text-ink mb-1">Trigger Re-screening</h3>
                    <p class="text-sm text-ink-muted mb-4">
                        Recalculate the risk score for {{ $customer->full_name }}.
                    </p>
                    <form method="POST" action="{{ route('compliance.risk-dashboard.rescreen') }}">
                        @csrf
                        <input type="hidden" name="customer_id" value="{{ $customer->id }}">
                        <div class="flex justify-end gap-3">
                            <x-button variant="secondary" type="button" @click="showRescreen = false">Cancel</x-button>
                            <x-button variant="primary" type="submit">Re-screen Now</x-button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</x-app-layout>
