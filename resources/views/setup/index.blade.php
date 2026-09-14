<x-app-layout title="Business Setup">
    <div class="space-y-6">
        <x-page-header title="Business Setup" />

        @if($isSetupComplete)
            <x-card class="text-center max-w-2xl mx-auto p-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-success-subtle mb-4">
                    <svg class="w-8 h-8 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold text-ink mb-2">Setup Complete</h2>
                <p class="text-ink-muted mb-6">Your business has been configured and is ready to use.</p>
                <div class="space-y-2 text-sm text-ink-muted">
                    <p>Admin User: Configured</p>
                    <p>Currencies: {{ $currencies->count() ?? 'Active' }}</p>
                    <p>Exchange Rates: Set</p>
                    <p>Branches: Initialized</p>
                </div>
                <x-button href="{{ route('login') }}" variant="primary" class="mt-6">Go to Login</x-button>
            </x-card>
        @else
            <x-card class="max-w-2xl">
                <div class="mb-6">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm font-medium text-ink">Step {{ $currentStep }} of 6</span>
                        <span class="text-sm text-ink-muted">{{ $progress }}% Complete</span>
                    </div>
                    <x-progress-bar :value="$progress" color="bg-primary" width="w-full" />
                </div>

                <div class="step-indicator mb-8 flex justify-between">
                    @for($i = 1; $i <= 6; $i++)
                        <div class="flex flex-col items-center">
                            <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $i <= $currentStep ? 'bg-primary text-on-primary' : 'bg-canvas-subtle text-ink-muted' }}">
                                {{ $i }}
                            </div>
                            <span class="text-xs text-ink-muted mt-1">
                                @switch($i)
                                    @case(1) Company @case(2) Admin @case(3) Currency @case(4) Rates @case(5) Stock @case(6) Balance @endswitch
                            </span>
                        </div>
                    @endfor
                </div>

                @if(session('error'))
                    <x-alert type="error">{{ session('error') }}</x-alert>
                @endif

                @if($errors->any())
                    <x-alert type="error">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </x-alert>
                @endif

                @switch($currentStep)
                    @case(1)
                    <form method="POST" action="{{ route('setup.step1') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Company Information</h3>
                        <div class="space-y-4">
                            <x-input name="business_name" label="Business Name" inline required />
                            <x-textarea name="business_address" label="Address" :rows="2" inline>{{ old('business_address') }}</x-textarea>
                            <div class="grid grid-cols-2 gap-4">
                                <x-input type="text" name="business_phone" label="Phone" inline />
                                <x-input type="email" name="business_email" label="Email" inline />
                            </div>
                        </div>
                        <div class="mt-6 flex justify-end">
                            <x-button type="submit" variant="primary">Next: Admin User</x-button>
                        </div>
                    </form>
                    @break

                    @case(2)
                    <form method="POST" action="{{ route('setup.step2') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Admin User</h3>
                        <div class="space-y-4">
                            <x-input name="admin_name" label="Admin Name" inline required />
                            <x-input type="email" name="admin_email" label="Email" inline required />
                            <x-input type="password" name="admin_password" label="Password" inline required minlength="12" help="Minimum 12 characters with uppercase, lowercase, number and special character." />
                            <x-input type="password" name="admin_password_confirmation" label="Confirm Password" inline required minlength="12" />
                        </div>
                        <div class="mt-6 flex justify-between">
                            <x-button href="{{ route('setup.index', ['step' => 1]) }}" variant="secondary">Previous</x-button>
                            <x-button type="submit" variant="primary">Next: Currencies</x-button>
                        </div>
                    </form>
                    @break

                    @case(3)
                    <form method="POST" action="{{ route('setup.step3') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Currencies</h3>
                        <p class="text-sm text-ink-muted mb-4">Select the currencies your business will trade in.</p>
                        <div class="space-y-4">
                            <x-select
                                name="base_currency"
                                label="Base Currency"
                                :options="$currencies->pluck('code', 'code')->toArray()"
                                value="MYR"
                                required
                            />
                            <div class="space-y-3">
                                @foreach($currencies as $currency)
                                    <x-checkbox
                                        name="active_currencies[]"
                                        value="{{ $currency->code }}"
                                        label="{{ $currency->code }} - {{ $currency->name }}"
                                        :checked="true"
                                    />
                                @endforeach
                            </div>
                            <div class="border-t border-border pt-4">
                                <label class="block text-sm font-medium text-ink-muted mb-2">Other Currency (optional)</label>
                                <p class="text-xs text-ink-muted mb-3">Add a currency that is not in the list above. Filling in a code selects and activates it.</p>
                                <div class="grid grid-cols-3 gap-4">
                                    <x-input name="custom_currency_code" label="Code" inline placeholder="e.g. THB" value="{{ old('custom_currency_code') }}" maxlength="3" />
                                    <x-input name="custom_currency_name" label="Name" inline placeholder="e.g. Thai Baht" value="{{ old('custom_currency_name') }}" />
                                    <x-input name="custom_currency_symbol" label="Symbol" inline placeholder="e.g. ฿" value="{{ old('custom_currency_symbol') }}" />
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <x-button href="{{ route('setup.index', ['step' => 2]) }}" variant="secondary">Previous</x-button>
                            <x-button type="submit" variant="primary">Next: Exchange Rates</x-button>
                        </div>
                    </form>
                    @break

                    @case(4)
                    <form method="POST" action="{{ route('setup.step4') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Exchange Rates</h3>
                        <p class="text-sm text-ink-muted mb-4">Configure how rates are fetched and managed.</p>
                        <div class="space-y-4">
                            <x-checkbox
                                name="use_default_rates"
                                value="1"
                                label="Use Default Rates"
                                help="Seed with standard exchange rates for selected currencies."
                                :checked="true"
                            />
                        </div>
                        @php($customCode = strtoupper((string) session('setup.currencies.custom_currency_code', '')))
                        @if($customCode !== '')
                            <div class="mt-4 border-t border-border pt-4">
                                <p class="text-sm text-ink-muted mb-2">
                                    {{ $customCode }} has no default rate. Enter its opening rates against MYR.
                                </p>
                                <div class="grid grid-cols-2 gap-4">
                                    <x-input name="custom_rates[{{ $customCode }}][buy]" label="{{ $customCode }} Buy Rate" inline required placeholder="0.0000" inputmode="decimal" />
                                    <x-input name="custom_rates[{{ $customCode }}][sell]" label="{{ $customCode }} Sell Rate" inline required placeholder="0.0000" inputmode="decimal" />
                                </div>
                            </div>
                        @endif
                        <div class="mt-6 flex justify-between">
                            <x-button href="{{ route('setup.index', ['step' => 3]) }}" variant="secondary">Previous</x-button>
                            <x-button type="submit" variant="primary">Next: Initial Stock</x-button>
                        </div>
                    </form>
                    @break

                    @case(5)
                    <form method="POST" action="{{ route('setup.step5') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Initial Stock</h3>
                        <p class="text-sm text-ink-muted mb-4">Set your initial currency holdings (opening floats).</p>
                        <div class="space-y-4">
                            <x-input name="initial_myr_cash" label="MYR Cash (RM)" inline placeholder="0.00" value="0.00" inputmode="decimal" />
                            <div class="border-t border-border pt-4">
                                <label class="block text-sm font-medium text-ink-muted mb-2">Foreign Currency Stock</label>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($setupCurrencies->where('code', '!=', 'MYR') as $currency)
                                        <x-input name="initial_stock[{{ $currency->code }}]" :label="$currency->code" inline placeholder="0.00" value="0.00" inputmode="decimal" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <x-button href="{{ route('setup.index', ['step' => 4]) }}" variant="secondary">Previous</x-button>
                            <x-button type="submit" variant="primary">Next: Opening Balance</x-button>
                        </div>
                    </form>
                    @break

                    @case(6)
                    <form method="POST" action="{{ route('setup.step6') }}">
                        @csrf
                        <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Opening Balance</h3>
                        <p class="text-sm text-ink-muted mb-4">Set your opening balance for accounting.</p>
                        <div class="space-y-4">
                            <x-input name="opening_balance_myr" label="Opening Balance (MYR)" inline placeholder="0.00" value="0.00" inputmode="decimal" />
                            <div class="border-t border-border pt-4">
                                <label class="block text-sm font-medium text-ink-muted mb-2">Foreign Currency Opening Balances</label>
                                <div class="grid grid-cols-2 gap-4">
                                    @foreach($setupCurrencies->where('code', '!=', 'MYR') as $currency)
                                        <x-input name="opening_balance_foreign[{{ $currency->code }}]" :label="$currency->code" inline placeholder="0.00" value="0.00" inputmode="decimal" />
                                    @endforeach
                                </div>
                            </div>
                        </div>
                        <div class="mt-6 flex justify-between">
                            <x-button href="{{ route('setup.index', ['step' => 5]) }}" variant="secondary">Previous</x-button>
                            <x-button type="submit" variant="primary">Next: Review</x-button>
                        </div>
                    </form>
                    @break

                    @case(7)
                    @php($setupData = session('setup', []))
                    <h3 class="text-sm font-semibold text-ink uppercase tracking-wider mb-4">Review &amp; Complete</h3>
                    <p class="text-sm text-ink-muted mb-4">Review your configuration, then finish setup.</p>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Business</dt>
                            <dd class="text-ink font-medium">{{ $setupData['business']['business_name'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Admin</dt>
                            <dd class="text-ink font-medium">{{ $setupData['admin']['admin_email'] ?? '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Currencies</dt>
                            <dd class="text-ink font-medium">{{ isset($setupData['currencies']['active_currencies']) ? implode(', ', $setupData['currencies']['active_currencies']) : '—' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Default Rates</dt>
                            <dd class="text-ink font-medium">{{ !empty($setupData['rates']['use_default_rates']) ? 'Yes' : 'No' }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">MYR Cash</dt>
                            <dd class="text-ink font-medium">{{ number_format((float) ($setupData['stock']['initial_myr_cash'] ?? 0), 2) }}</dd>
                        </div>
                        <div class="flex justify-between">
                            <dt class="text-ink-muted">Opening Balance (MYR)</dt>
                            <dd class="text-ink font-medium">{{ number_format((float) ($setupData['opening_balance']['opening_balance_myr'] ?? 0), 2) }}</dd>
                        </div>
                    </dl>
                    <div id="setup-complete-error" class="hidden mt-4 text-sm text-danger-text"></div>
                    <div class="mt-6 flex justify-between">
                        <x-button href="{{ route('setup.index', ['step' => 6]) }}" variant="secondary">Previous</x-button>
                        <x-button id="complete-setup-btn" type="button" variant="primary">Complete Setup</x-button>
                    </div>
                    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
                        document.getElementById('complete-setup-btn').addEventListener('click', function () {
                            var btn = this;
                            var err = document.getElementById('setup-complete-error');
                            btn.disabled = true;
                            fetch('{{ route('setup.complete') }}', {
                                method: 'POST',
                                headers: {
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                                    'Accept': 'application/json',
                                },
                            }).then(function (res) { return res.json(); }).then(function (data) {
                                if (data.success && data.redirect) {
                                    window.location.href = data.redirect;
                                } else {
                                    err.textContent = data.message || 'Setup could not be completed.';
                                    err.classList.remove('hidden');
                                    btn.disabled = false;
                                }
                            }).catch(function () {
                                err.textContent = 'Setup could not be completed. Please try again.';
                                err.classList.remove('hidden');
                                btn.disabled = false;
                            });
                        });
                    </script>
                    @break
                @endswitch
            </x-card>
        @endif
    </div>
</x-app-layout>
