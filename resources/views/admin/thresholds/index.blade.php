<x-app-layout title="Thresholds">
    <div class="space-y-6">
        <x-page-header title="Thresholds" description="View and override compliance and operational thresholds. Edits are stored as audited database overrides — they take precedence over environment variables until reset." />

        @if($activeOverrideCount > 0)
            <x-alert type="warning" :dismissible="false">
                {{ $activeOverrideCount }} threshold(s) are driven by database overrides — .env changes will not affect them. Use Reset to return a key to its config default.
            </x-alert>
        @endif

        @if($errors->any())
            <x-alert type="error" :dismissible="true">
                <ul class="list-disc list-inside">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-alert>
        @endif

        <form method="POST" action="{{ route('admin.thresholds.update') }}">
            @csrf
            <div class="space-y-6">
                <x-card>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="grow">
                            <x-input
                                label="Change reason"
                                name="reason"
                                required
                                placeholder="e.g. BNM circular 2026-02 — raise STR reference"
                                :value="old('reason')"
                                help="Recorded on every audit row written by this save."
                            />
                        </div>
                        <x-button type="submit" variant="primary">Save Thresholds</x-button>
                    </div>
                </x-card>

                @foreach($groups as $category => $group)
                    <x-card>
                        <h3 class="text-sm font-semibold text-ink mb-1">{{ $group['meta']['label'] }}</h3>
                        @if($group['meta']['description'] !== '')
                            <p class="text-xs text-ink-muted mb-3 pb-2 border-b border-border">{{ $group['meta']['description'] }}</p>
                        @endif
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                        <th class="px-4 py-3 text-left">Threshold</th>
                                        <th class="px-4 py-3 text-left">Config</th>
                                        <th class="px-4 py-3 text-left">Active</th>
                                        <th class="px-4 py-3 text-left">Source</th>
                                        <th class="px-4 py-3 text-left">Last change</th>
                                        <th class="px-4 py-3 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($group['rows'] as $row)
                                        <tr class="border-t border-border hover:bg-canvas-subtle">
                                            <td class="px-4 py-3">
                                                <div class="text-sm font-medium text-ink">{{ $row['meta']['label'] }}</div>
                                                <div class="text-xs text-ink-muted mt-0.5">
                                                    {{ $row['category'] }}.{{ $row['key'] }}
                                                    @if($row['meta']['unit'] !== '') · {{ $row['meta']['unit'] }} @endif
                                                </div>
                                                @if($row['meta']['description'] !== '')
                                                    <div class="text-xs text-ink-muted mt-0.5">{{ $row['meta']['description'] }}</div>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-sm text-ink-muted font-mono">
                                                {{ $row['config'] === null ? '—' : $row['config'] }}
                                            </td>
                                            <td class="px-4 py-3">
                                                <input
                                                    type="text"
                                                    inputmode="decimal"
                                                    name="values[{{ $row['category'] }}][{{ $row['key'] }}]"
                                                    value="{{ old("values.{$row['category']}.{$row['key']}", $row['active']) }}"
                                                    class="w-32 px-2 py-1.5 text-sm font-mono bg-canvas-subtle border border-border rounded-lg text-ink focus:bg-surface focus:outline-none focus:ring-2 focus:ring-primary {{ $errors->has("values.{$row['category']}.{$row['key']}") ? 'border-danger' : '' }}"
                                                >
                                                @error("values.{$row['category']}.{$row['key']}")
                                                    <p class="mt-1 text-xs text-danger-text">{{ $message }}</p>
                                                @enderror
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($row['source'] === 'db')
                                                    <x-badge variant="warning">override</x-badge>
                                                @else
                                                    <x-badge variant="gray">config</x-badge>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-xs text-ink-muted">
                                                @if($row['override'])
                                                    <div>{{ $row['override']->changed_at?->format('Y-m-d H:i') }}</div>
                                                    <div>{{ $row['override']->user?->email ?? 'console' }}</div>
                                                    @if($row['override']->change_reason)
                                                        <div class="italic">{{ $row['override']->change_reason }}</div>
                                                    @endif
                                                @else
                                                    —
                                                @endif
                                            </td>
                                            <td class="px-4 py-3 text-right">
                                                {{-- Reset needs a config default to revert to; persisted-only keys have none. --}}
                                                @if($row['source'] === 'db' && $row['config'] !== null)
                                                    <x-button
                                                        type="submit"
                                                        form="reset-{{ $row['category'] }}-{{ $row['key'] }}"
                                                        variant="ghost"
                                                        size="sm"
                                                    >Reset</x-button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endforeach

                <x-card>
                    <div class="flex items-center justify-end gap-3">
                        <x-button type="submit" variant="primary">Save Thresholds</x-button>
                    </div>
                </x-card>
            </div>
        </form>

        {{-- Per-row reset forms live outside the edit form; each row's Reset
             button submits its own via the form attribute. --}}
        @foreach($groups as $group)
            @foreach($group['rows'] as $row)
                @if($row['source'] === 'db' && $row['config'] !== null)
                    <form id="reset-{{ $row['category'] }}-{{ $row['key'] }}" method="POST" action="{{ route('admin.thresholds.reset') }}">
                        @csrf
                        <input type="hidden" name="category" value="{{ $row['category'] }}">
                        <input type="hidden" name="key" value="{{ $row['key'] }}">
                    </form>
                @endif
            @endforeach
        @endforeach

        <x-card>
            <h3 class="text-sm font-semibold text-ink mb-3 pb-2 border-b border-border">Audit history</h3>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                            <th class="px-4 py-3 text-left">When</th>
                            <th class="px-4 py-3 text-left">Threshold</th>
                            <th class="px-4 py-3 text-left">Old</th>
                            <th class="px-4 py-3 text-left">New</th>
                            <th class="px-4 py-3 text-left">Changed by</th>
                            <th class="px-4 py-3 text-left">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($history as $audit)
                            <tr class="border-t border-border hover:bg-canvas-subtle">
                                <td class="px-4 py-3 text-sm text-ink-muted">{{ $audit->changed_at?->format('Y-m-d H:i') }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-ink">{{ $audit->category }}.{{ $audit->key }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-ink-muted">{{ $audit->old_value }}</td>
                                <td class="px-4 py-3 text-sm font-mono text-ink">{{ $audit->new_value }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted">{{ $audit->user?->email ?? 'console' }}</td>
                                <td class="px-4 py-3 text-sm text-ink-muted">{{ $audit->change_reason ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-border">
                                <td colspan="6" class="px-4 py-6 text-center text-sm text-ink-muted">No threshold changes recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($history->hasPages())
                <div class="mt-4">{{ $history->links() }}</div>
            @endif
        </x-card>
    </div>
</x-app-layout>
