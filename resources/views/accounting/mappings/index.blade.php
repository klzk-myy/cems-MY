<x-app-layout title="Account Mappings">
    <div class="space-y-6">
        <x-page-header title="Account Mappings" description="Map business events to chart-of-accounts codes. Every posting path (transactions, expenses, revaluation, period close, settlement) resolves through this table — changes apply to the next posting and are audit-logged." />

        @if(session('success'))
            <x-alert type="success" :dismissible="true">{{ session('success') }}</x-alert>
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

        @if(! $mappingsInstalled)
            <x-alert type="warning">
                The account_mappings table is not installed on this database — posting paths resolve the
                built-in defaults shown below and editing is disabled. Run
                <code class="font-mono">php artisan accounting:install-mappings</code> to enable editing.
            </x-alert>
        @endif

        @if($mappingsInstalled)
        <form method="POST" action="{{ route('accounting.mappings.update') }}">
            @csrf
        @endif
            <div class="space-y-6">
                @if($mappingsInstalled)
                <x-card>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="grow">
                            <x-input
                                label="Change reason"
                                name="reason"
                                required
                                placeholder="e.g. Accountant directive — move USD inventory to dedicated account"
                                :value="old('reason')"
                                help="Recorded on every audit row written by this save."
                            />
                        </div>
                        <x-button type="submit" variant="primary">Save Mappings</x-button>
                    </div>
                </x-card>
                @endif

                @php $i = 0; @endphp

                @foreach($sections as $sectionName => $rows)
                    <x-card>
                        <h3 class="text-sm font-semibold text-ink mb-3 pb-2 border-b border-border">{{ $sectionName }}</h3>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                        <th class="px-4 py-3 text-left">Mapping</th>
                                        <th class="px-4 py-3 text-left">Account</th>
                                        <th class="px-4 py-3 text-left">Source</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($rows as $row)
                                        @php
                                            $key = $row['key'];
                                            $index = $i++;
                                            $fieldName = "mappings.{$index}.account_code";
                                            $selected = old($fieldName, $row['account_code']);
                                            $options = ($accountsByType->get($key->expectedType()->value, collect()))
                                                ->mapWithKeys(fn ($a) => [$a->account_code => "{$a->account_code} — {$a->account_name}"])
                                                ->all();
                                        @endphp
                                        <tr class="border-t border-border hover:bg-canvas-subtle">
                                            <td class="px-4 py-3">
                                                <input type="hidden" name="mappings[{{ $index }}][key]" value="{{ $key->value }}">
                                                <div class="text-sm font-medium text-ink">{{ $key->label() }}</div>
                                                <div class="text-xs text-ink-muted mt-0.5 font-mono">{{ $key->value }}</div>
                                                <div class="text-xs text-ink-muted mt-0.5">{{ $key->usedBy() }}</div>
                                            </td>
                                            <td class="px-4 py-3 w-72">
                                                @if($mappingsInstalled)
                                                    <x-select
                                                        name="mappings[{{ $index }}][account_code]"
                                                        :options="$options"
                                                        :value="$selected"
                                                        placeholder="— select account —"
                                                        inline
                                                    />
                                                @else
                                                    <span class="font-mono text-sm text-ink">{{ $row['account_code'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-3">
                                                @if($row['is_default'])
                                                    <x-badge variant="gray">default</x-badge>
                                                @else
                                                    <x-badge variant="warning">override</x-badge>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-card>
                @endforeach

                @if($mappingsInstalled)
                <x-card>
                    <h3 class="text-sm font-semibold text-ink mb-1">Per-Currency Overrides</h3>
                    <p class="text-xs text-ink-muted mb-3 pb-2 border-b border-border">
                        Route one currency's cash or inventory posting to its own account (e.g. inventory.USD → 2001).
                        Leave blank to use the default mapping above.
                    </p>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead>
                                <tr class="text-left text-xs font-medium text-ink-muted uppercase tracking-wider">
                                    <th class="px-4 py-3 text-left">Currency</th>
                                    <th class="px-4 py-3 text-left">Cash account (cash.{CCY})</th>
                                    <th class="px-4 py-3 text-left">Inventory account (inventory.{CCY})</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php $assetOptions = $assetAccounts->mapWithKeys(fn ($a) => [$a->account_code => "{$a->account_code} — {$a->account_name}"])->all(); @endphp
                                @forelse($currencies as $currency)
                                    @php
                                        $cashKey = "cash.{$currency->code}";
                                        $invKey = "inventory.{$currency->code}";
                                    @endphp
                                    <tr class="border-t border-border hover:bg-canvas-subtle">
                                        <td class="px-4 py-3">
                                            <div class="text-sm font-medium text-ink">{{ $currency->code }}</div>
                                            <div class="text-xs text-ink-muted">{{ $currency->name }}</div>
                                        </td>
                                        <td class="px-4 py-3 w-72">
                                            <input type="hidden" name="mappings[{{ $i }}][key]" value="{{ $cashKey }}">
                                            <x-select
                                                name="mappings[{{ $i }}][account_code]"
                                                :options="$assetOptions"
                                                :value="old('mappings.'.$i.'.account_code', $currencyRows->get($cashKey)?->account_code)"
                                                placeholder="— default (cash.default) —"
                                                inline
                                            />
                                            @php $i++; @endphp
                                        </td>
                                        <td class="px-4 py-3 w-72">
                                            <input type="hidden" name="mappings[{{ $i }}][key]" value="{{ $invKey }}">
                                            <x-select
                                                name="mappings[{{ $i }}][account_code]"
                                                :options="$assetOptions"
                                                :value="old('mappings.'.$i.'.account_code', $currencyRows->get($invKey)?->account_code)"
                                                placeholder="— default (inventory.default) —"
                                                inline
                                            />
                                            @php $i++; @endphp
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="px-4 py-6 text-sm text-ink-muted text-center">No active foreign currencies configured.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
                @endif
            </div>
        @if($mappingsInstalled)
        </form>
        @endif

        @if($unprovisionedCurrencies->isNotEmpty())
            <x-card>
                <h3 class="text-sm font-semibold text-ink mb-1">Provision Dedicated Accounts</h3>
                <p class="text-xs text-ink-muted mb-3 pb-2 border-b border-border">
                    These currencies have no dedicated GL accounts — postings fall back to the pooled
                    cash.default / inventory.default mappings. Provisioning creates a Cash and an
                    Inventory chart account plus the cash.{CCY} / inventory.{CCY} mapping rows.
                    New currencies get these automatically on creation.
                </p>
                <div class="flex flex-wrap gap-3">
                    @foreach($unprovisionedCurrencies as $currency)
                        <form method="POST" action="{{ route('accounting.mappings.provision', $currency) }}">
                            @csrf
                            <x-button type="submit" variant="secondary">Provision {{ $currency->code }}</x-button>
                        </form>
                    @endforeach
                </div>
            </x-card>
        @endif
    </div>
</x-app-layout>
