<x-app-layout title="Transaction Wizard">
    <div class="space-y-6">
        <x-page-header title="New Transaction" description="Record a currency exchange, one step at a time." />

        @php
            $purposes = [
                'Travel' => 'Travel',
                'Education' => 'Education',
                'Medical' => 'Medical',
                'Business' => 'Business',
                'Investment' => 'Investment',
                'Family Support' => 'Family Support',
                'Migration' => 'Migration',
                'Other' => 'Other',
            ];

            $frequencies = [
                'weekly' => 'Weekly',
                'monthly' => 'Monthly',
                'quarterly' => 'Quarterly',
                'annually' => 'Annually',
            ];
        @endphp

        <div x-data="transactionWizard"
             data-api-base="{{ url('api/v1') }}"
             data-idempotency-key="{{ $idempotencyKey }}"
             data-currencies='@json($currencies ?? [])'
             data-currency-units='@json($currencyUnits ?? [])'
             data-currency-inverses='@json($currencyInverses ?? [])'
             class="w-full">

            <form @submit.prevent="submitStep()">

                <!-- Step indicator -->
                <ol class="mb-8 flex items-center" aria-label="Transaction steps">
                    <template x-for="(label, idx) in ['Transaction Details', 'Customer & CDD', 'Review']" :key="label">
                        <li class="flex items-center" :class="idx < 2 ? 'flex-1' : ''">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border-2 text-sm font-semibold transition-colors"
                                      :class="step >= idx + 1
                                          ? 'border-primary bg-primary text-on-primary'
                                          : 'border-border bg-surface text-ink-muted'"
                                      x-text="idx + 1"></span>
                                <span class="text-sm"
                                      :class="step >= idx + 1 ? 'font-medium text-ink' : 'text-ink-muted'"
                                      :aria-current="step === idx + 1 ? 'step' : null"
                                      x-text="label"></span>
                            </div>
                            <div x-show="idx < 2" x-cloak class="mx-3 h-0.5 flex-1"
                                 :class="step > idx + 1 ? 'bg-primary' : 'bg-border'"></div>
                        </li>
                    </template>
                </ol>

                <!-- Request error -->
                <div x-if="errorMessage" class="mb-6">
                    <x-alert type="error" title="We couldn't complete this step" role="alert">
                        <span x-text="errorMessage"></span>
                    </x-alert>
                </div>

                <!-- Step 1: Transaction Details -->
                <div x-show="step === 1" x-cloak class="rounded-xl border border-border bg-surface p-6">
                    <h2 class="mb-5 text-lg font-semibold text-ink">Transaction Details</h2>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input :inline="true" name="customer_id" label="Customer ID"
                                 type="number" min="1" required
                                 x-model="formData.customer_id" />

                        <x-select :inline="true" name="type" label="Transaction Type"
                                  :options="['Buy' => 'Buy', 'Sell' => 'Sell']"
                                  placeholder="Select type" required
                                  x-model="formData.type" />

                        <x-select :inline="true" name="currency_code" label="Currency"
                                  :options="$currencies ?? []"
                                  placeholder="Select currency" required
                                  x-model="formData.currency_code" />

                        <x-input :inline="true" name="quantity" label="Foreign Amount"
                                 type="number" step="0.01" min="0.01" required
                                 x-model="formData.quantity" />

                        <div>
                            <x-input :inline="true" name="rate" label="Exchange Rate"
                                     type="number" step="0.0001" min="0.0001" required
                                     x-model="formData.rate" />
                            <p x-show="currencyInverse()" x-cloak class="mt-1 text-xs text-ink-muted">
                                <span x-text="formData.currency_code"></span> per RM <span x-text="currencyUnit()"></span>
                            </p>
                            <p x-show="!currencyInverse() && currencyUnit() > 1" x-cloak class="mt-1 text-xs text-ink-muted">
                                in MYR per <span x-text="currencyUnit()"></span> <span x-text="formData.currency_code"></span>
                            </p>
                        </div>

                        <x-input :inline="true" name="amount_myr" label="Local Amount (MYR)"
                                 readonly class="font-semibold"
                                 x-model="amountMyr" />

                        <x-select :inline="true" name="purpose" label="Purpose"
                                  :options="$purposes"
                                  placeholder="Select purpose" required
                                  x-model="formData.purpose" />

                        <div class="md:col-span-2">
                            <x-input :inline="true" name="source_of_funds" label="Source of Funds"
                                     placeholder="e.g. Salary, Savings, Business Income" required
                                     x-model="formData.source_of_funds" />
                        </div>
                    </div>

                    <!-- Blocked banner -->
                    <div x-if="wizard.blockedMessage" class="mt-4">
                        <x-alert type="error" title="Transaction blocked" role="alert">
                            <span x-text="wizard.blockedMessage"></span>
                        </x-alert>
                    </div>
                </div>

                <!-- Step 2: Customer Details & CDD -->
                <div x-show="step === 2" x-cloak class="rounded-xl border border-border bg-surface p-6">
                    <h2 class="mb-1 text-lg font-semibold text-ink">Customer Details</h2>
                    <p class="mb-4 flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                        <span x-text="wizard.cdd_description || 'Customer due diligence information'"></span>
                        <span x-show="wizard.hold_required" x-cloak
                              class="inline-flex items-center rounded border border-warning-border bg-warning-subtle px-2 py-0.5 text-xs font-medium text-warning-text">
                            Compliance hold will apply
                        </span>
                    </p>

                    <div x-if="wizard.risk_flags.length" class="mb-4">
                        <x-alert type="warning" title="Risk flags detected">
                            <ul class="list-disc pl-4 text-sm">
                                <template x-for="flag in wizard.risk_flags" :key="flag">
                                    <li x-text="flag"></li>
                                </template>
                            </ul>
                        </x-alert>
                    </div>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <x-input :inline="true" name="occupation" label="Occupation"
                                 required x-model="formData.occupation" />

                        <x-input :inline="true" name="employer_name" label="Employer Name"
                                 x-model="formData.employer_name" />

                        <div class="md:col-span-2">
                            <x-input :inline="true" name="employer_address" label="Employer Address"
                                     x-model="formData.employer_address" />
                        </div>

                        <x-input :inline="true" name="annual_volume_myr"
                                 label="Estimated Annual Volume"
                                 type="number" step="0.01" min="0"
                                 x-model="formData.annual_volume_myr" />

                        <!-- Enhanced CDD only -->
                        <template x-if="requireEnhanced">
                            <div class="grid grid-cols-1 gap-4 border-t border-border pt-4 md:col-span-2 md:grid-cols-2">
                                <x-input :inline="true" name="beneficial_owner" label="Beneficial Owner"
                                         required x-model="formData.beneficial_owner" />

                                <x-select :inline="true" name="expected_frequency" label="Expected Frequency"
                                          :options="$frequencies"
                                          placeholder="Select frequency" required
                                          x-model="formData.expected_frequency" />

                                <div class="md:col-span-2">
                                    <x-textarea :inline="true" name="source_of_wealth" label="Source of Wealth"
                                                rows="2" required
                                                x-model="formData.source_of_wealth" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Document Uploads -->
                    <div class="mt-6 border-t border-border pt-4">
                        <h3 class="mb-3 text-sm font-semibold text-ink">Required Documents</h3>
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div x-show="requireProofOfAddress || requirePassport" x-cloak class="md:col-span-2">
                                <ul class="mb-2 text-xs text-ink-muted">
                                    <template x-for="d in wizard.required_documents" :key="d.type">
                                        <li>
                                            <span x-text="d.label"></span>:
                                            <span :class="d.required ? 'font-medium text-danger-text' : 'text-ink-muted'"
                                                  x-text="d.required ? 'Required' : 'Optional'"></span>
                                        </li>
                                    </template>
                                </ul>
                            </div>

                            <div x-show="requireProofOfAddress" x-cloak>
                                <x-input :inline="true" name="proof_of_address" type="file"
                                         required accept=".pdf,.jpg,.jpeg,.png"
                                         label="Proof of Address"
                                         @change="files.proof_of_address = $event.target.files[0]" />
                            </div>

                            <div x-show="requirePassport" x-cloak>
                                <x-input :inline="true" name="passport" type="file"
                                         required accept=".pdf,.jpg,.jpeg,.png"
                                         label="Passport"
                                         @change="files.passport = $event.target.files[0]" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review & Confirm -->
                <div x-show="step === 3" x-cloak class="rounded-xl border border-border bg-surface p-6">
                    <h2 class="mb-4 text-lg font-semibold text-ink">Review &amp; Confirm</h2>

                    <dl class="text-sm">
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Customer</dt>
                            <dd class="text-ink" x-text="summary.customer_name || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Type</dt>
                            <dd class="text-ink" x-text="summary.type || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Currency</dt>
                            <dd class="text-ink" x-text="summary.currency || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Foreign Amount</dt>
                            <dd class="text-ink" x-text="(summary.currency || '') + ' ' + (summary.quantity || '—')"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Rate</dt>
                            <dd class="text-ink" x-text="summary.rate || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="font-medium text-ink">Local Amount (MYR)</dt>
                            <dd class="text-base font-semibold text-ink" x-text="summary.amount_myr || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Purpose</dt>
                            <dd class="text-ink" x-text="summary.purpose || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">Source of Funds</dt>
                            <dd class="text-ink" x-text="summary.source_of_funds || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-ink-muted">CDD Level</dt>
                            <dd class="text-ink"
                                x-text="(summary.cdd_level || '—').charAt(0).toUpperCase() + (summary.cdd_level || '').slice(1)"></dd>
                        </div>
                        <div x-show="summary.hold_required" x-cloak
                             class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="text-warning-text">Status</dt>
                            <dd class="inline-flex items-center rounded border border-warning-border bg-warning-subtle px-2.5 py-0.5 text-xs font-medium text-warning-text">
                                Pending Compliance Approval
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Step 4: Success -->
                <div x-show="step === 4" x-cloak class="rounded-xl border border-border bg-surface p-6 text-center">
                    <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-success-subtle">
                        <svg class="h-8 w-8 text-success-text" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                        </svg>
                    </div>
                    <h2 class="mb-1 text-lg font-semibold text-ink">Transaction Created</h2>
                    <p class="mb-4 flex flex-wrap items-center justify-center gap-2 text-sm text-ink-muted">
                        <span x-text="'Reference: ' + (result.number || '—')"></span>
                        <span class="inline-flex items-center rounded border px-2.5 py-0.5 text-xs font-medium"
                              :class="(result.status || '').toUpperCase() === 'COMPLETED'
                                  ? 'border-success-border bg-success-subtle text-success-text'
                                  : 'border-warning-border bg-warning-subtle text-warning-text'"
                              x-text="(result.status || '—').toUpperCase()"></span>
                    </p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <a :href="'/transactions/' + result.id"
                           class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-on-primary transition-colors hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            View Transaction
                        </a>
                        <button type="button" @click="reset()"
                                class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-canvas-subtle focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2">
                            New Transaction
                        </button>
                    </div>
                </div>

                <!-- Navigation -->
                <div class="mt-6 flex items-center"
                     :class="step === 1 || step === 4 ? 'justify-end' : 'justify-between'">
                    <button type="button" x-show="step > 1 && step < 4" x-cloak
                            :disabled="loading" @click="step--"
                            class="inline-flex items-center justify-center gap-2 rounded-lg border border-border bg-surface px-4 py-2 text-sm font-medium text-ink transition-colors hover:bg-canvas-subtle focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        Previous
                    </button>

                    <button type="submit" x-show="step === 1" x-cloak
                            :disabled="loading || !validStep1()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-on-primary transition-colors hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!loading" x-cloak>Continue</span>
                        <span x-show="loading" x-cloak>Checking…</span>
                    </button>

                    <button type="submit" x-show="step === 2" x-cloak
                            :disabled="loading || !validStep2()"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-on-primary transition-colors hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!loading" x-cloak>Review</span>
                        <span x-show="loading" x-cloak>Saving…</span>
                    </button>

                    <button type="submit" x-show="step === 3" x-cloak :disabled="loading"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2 text-sm font-medium text-on-primary transition-colors hover:bg-primary-hover focus:outline-none focus:ring-2 focus:ring-primary focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-show="!loading" x-cloak>Confirm &amp; Submit</span>
                        <span x-show="loading" x-cloak>Submitting…</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
