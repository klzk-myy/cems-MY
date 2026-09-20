@php
    $attributes = $attributes ?? new \Illuminate\View\ComponentAttributeBag([]);
@endphp

<x-app-layout title="New Transaction" {{ $attributes }}>
    <x-page-header title="New Transaction" description="Record a new buy or sell transaction." />

    @php
        $customerFieldNames = ['full_name', 'id_type', 'id_number', 'date_of_birth', 'nationality', 'phone', 'email', 'address', 'occupation', 'employer_name'];
        $customerFields = collect($customerFieldNames)->mapWithKeys(fn ($f) => [$f => old($f, '')])->all();
    @endphp

    <form method="POST" action="{{ route('transactions.store') }}"
          x-data="transactionForm"
          data-currency-units='@json($currencyUnits ?? [])'
          data-currency-inverses='@json($currencyInverses ?? [])'
          data-cdd-specific="{{ $cddThresholds['specific'] ?? 3000 }}"
          data-cdd-standard="{{ $cddThresholds['standard'] ?? 10000 }}"
          data-search-url="{{ route('customers.search') }}"
          data-initial-currency="{{ old('currency_code', '') }}"
          data-initial-quantity="{{ old('quantity', '') }}"
          data-initial-rate="{{ old('rate', '') }}"
          data-initial-customer-id="{{ old('customer_id', '') }}"
          data-customer-fields='@json($customerFields)'>
        @csrf
        @php
            // Reuse the key across validation-error redirects so a retry after
            // a validation error does not create a duplicate booking. A fresh
            // page load always generates a new key, so every successful booking
            // gets its own identity. Duplicate-key submissions are resolved
            // idempotently by TransactionCreationService::findDuplicate.
            $idempotencyKey = old('idempotency_key') ?? $idempotencyKey;
        @endphp
        <input type="hidden" name="branch_id" value="{{ auth()->user()?->branch_id }}">
        <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
        <input type="hidden" name="customer_id" :value="customerId">

        @php
            // The booking till comes from the teller's open session, so it has
            // no form field — surface its errors as a form-level alert.
            $tillError = $errors->first('till_id') ?: $errors->first('counter_id');
        @endphp
        @if($tillError)
            <x-alert type="error" :dismissible="true" class="mt-4">{{ $tillError }}</x-alert>
        @endif

        <x-card title="Customer" description="Type the ID number or name — a matching record loads automatically; otherwise the details register a new customer on submit." class="mt-4">
            <div x-show="customerId" x-cloak
                 class="mb-4 flex items-center gap-2 rounded-lg bg-success-subtle px-3 py-2 text-sm text-success-text">
                <x-icon name="check" class="w-4 h-4" />
                <span>Existing customer loaded — blank fields marked required will be saved to their record.</span>
            </div>
            <div x-show="!customerId" x-cloak
                 class="mb-4 rounded-lg bg-canvas-subtle px-3 py-2 text-sm text-ink-muted">
                No match — these details will register a new customer when you submit.
            </div>
            <div x-cloak
                 class="mb-4 rounded-lg bg-warning-subtle px-3 py-2 text-sm text-warning-text">
                <span x-show="cddTier === 'simplified'">Simplified CDD (&lt; RM<span x-text="cddSpecific.toLocaleString()"></span>): address is required.</span>
                <span x-show="cddTier === 'specific'">Specific CDD applies (&ge; RM<span x-text="cddSpecific.toLocaleString()"></span>): address is required.</span>
                <span x-show="cddTier === 'standard'">Standard CDD applies (&ge; RM<span x-text="cddStandard.toLocaleString()"></span>): address, phone, occupation and employer are required.</span>
                <span x-show="cddTier === 'enhanced'">Enhanced CDD (higher risk): address, phone, occupation and employer are required; source of wealth is required for PEP customers.</span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="relative">
                    <x-input name="full_name" label="Full Name" required
                             x-model="fields.full_name"
                             @input.debounce.400ms="markEdited(); lookup('full_name')"
                             @keydown.escape="open = false"
                             autocomplete="off" />
                    <ul x-show="open && openFor === 'full_name'" x-cloak
                        class="absolute z-20 mt-1 w-full max-h-64 overflow-auto bg-surface border border-border rounded-lg shadow-lg">
                        <template x-for="c in results" :key="c.id">
                            <li @mousedown.prevent="pick(c)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-canvas-subtle flex items-center justify-between gap-2">
                                <span>
                                    <span class="font-medium text-ink" x-text="c.full_name"></span>
                                    <span class="text-ink-muted" x-text="c.id_number ? ' · ' + c.id_number : ''"></span>
                                </span>
                                <span class="flex items-center gap-2 shrink-0">
                                    <span x-show="c.is_sanctioned" class="text-xs font-medium text-danger">SANCTION</span>
                                    <span x-show="c.is_pep" class="text-xs font-medium text-warning">PEP</span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                <x-select name="id_type" label="ID Type" required
                          :options="['MyKad' => 'MyKad (Malaysian IC)', 'Passport' => 'Passport', 'Others' => 'Other ID']"
                          :selected="old('id_type')"
                          x-model="fields.id_type" @change="markEdited()" />

                <div class="relative">
                    <x-input name="id_number" label="ID Number" required
                             x-bind:required="!customerId"
                             x-model="fields.id_number"
                             @input.debounce.500ms="markEdited(); lookup('id_number')"
                             @keydown.escape="open = false"
                             autocomplete="off" />
                    <ul x-show="open && openFor === 'id_number'" x-cloak
                        class="absolute z-20 mt-1 w-full max-h-64 overflow-auto bg-surface border border-border rounded-lg shadow-lg">
                        <template x-for="c in results" :key="c.id">
                            <li @mousedown.prevent="pick(c)"
                                class="px-3 py-2 text-sm cursor-pointer hover:bg-canvas-subtle flex items-center justify-between gap-2">
                                <span>
                                    <span class="font-medium text-ink" x-text="c.full_name"></span>
                                    <span class="text-ink-muted" x-text="c.id_number ? ' · ' + c.id_number : ''"></span>
                                </span>
                                <span class="flex items-center gap-2 shrink-0">
                                    <span x-show="c.is_sanctioned" class="text-xs font-medium text-danger">SANCTION</span>
                                    <span x-show="c.is_pep" class="text-xs font-medium text-warning">PEP</span>
                                </span>
                            </li>
                        </template>
                    </ul>
                </div>

                <x-input type="date" name="date_of_birth" label="Date of Birth" required
                         x-model="fields.date_of_birth" @input="markEdited()" />

                <x-select name="nationality" label="Nationality" required
                          :options="['MY' => 'Malaysian', 'SG' => 'Singaporean', 'US' => 'American', 'GB' => 'British', 'OTHER' => 'Other']"
                          :selected="old('nationality')"
                          x-model="fields.nationality" @change="markEdited()" />

                <x-input name="phone" label="Phone Number"
                         x-model="fields.phone"
                         x-bind:required="need('phone')"
                         x-bind:placeholder="existing && has.phone ? 'On file — leave blank to keep' : ''" />

                <x-input type="email" name="email" label="Email"
                         x-model="fields.email" />

                <x-input name="occupation" label="Occupation"
                         x-model="fields.occupation"
                         x-bind:required="need('occupation')"
                         x-bind:placeholder="existing && has.occupation ? 'On file — leave blank to keep' : ''" />

                <x-input name="employer_name" label="Employer Name"
                         x-model="fields.employer_name"
                         x-bind:required="need('employer_name')"
                         x-bind:placeholder="existing && has.employer_name ? 'On file — leave blank to keep' : ''" />
            </div>

            <x-textarea name="address" label="Address" rows="2" class="mt-4"
                        x-model="fields.address"
                        x-bind:required="need('address')"
                        x-bind:placeholder="existing && has.address ? 'On file — leave blank to keep' : ''"></x-textarea>
        </x-card>

        <x-card title="Transaction" class="mt-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <x-select name="type" label="Transaction Type" :options="['Buy' => 'Buy', 'Sell' => 'Sell']" :selected="old('type')" required />

                <x-select name="currency_code" label="Currency" :options="$currencies ?? []" :selected="old('currency_code')" required
                    x-model="currency_code" />

                <x-input type="number" name="quantity" label="Foreign Amount" step="0.01" value="{{ old('quantity') }}" required
                    x-model="quantity" />

                <div>
                    <x-input type="number" name="rate" label="Exchange Rate" step="0.0001" value="{{ old('rate') }}" required
                        x-model="rate" />
                    <p x-show="isInverse" x-cloak class="mt-1 text-xs text-ink-muted">
                        <span x-text="currency_code"></span> per RM <span x-text="unit"></span>
                    </p>
                    <p x-show="showUnitHint" x-cloak class="mt-1 text-xs text-ink-muted">
                        in MYR per <span x-text="unit"></span> <span x-text="currency_code"></span>
                    </p>
                    <p x-show="amountMyr > 0" x-cloak class="mt-1 text-xs text-ink-muted">
                        &asymp; RM <span x-text="amountMyr.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span>
                    </p>
                </div>

                <x-select name="purpose" label="Purpose" :options="['Travel' => 'Travel', 'Education' => 'Education', 'Medical' => 'Medical', 'Business' => 'Business', 'Investment' => 'Investment', 'Family Support' => 'Family Support', 'Migration' => 'Migration', 'Other' => 'Other']" :selected="old('purpose')" required />

                <x-input name="source_of_funds" label="Source of Funds" placeholder="e.g. Salary, Savings, Business Income" value="{{ old('source_of_funds') }}" required />

                <x-input name="source_of_wealth" label="Source of Wealth" placeholder="e.g. Business Ownership, Inheritance, Investments" value="{{ old('source_of_wealth') }}" x-bind:required="existing && existing.is_pep" help="Required for PEP customers" />
            </div>
        </x-card>

        <div class="mt-6 flex gap-2">
            <x-button type="submit" variant="primary">Create Transaction</x-button>
            <x-button href="{{ route('transactions.index') }}" variant="secondary">Cancel</x-button>
        </div>
    </form>
</x-app-layout>
