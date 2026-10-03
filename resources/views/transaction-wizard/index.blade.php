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
                        <div x-show="networkError" x-cloak class="mt-2">
                            <button type="button" @click="submitStep()" :disabled="loading"
                                    class="inline-flex items-center rounded-md border border-danger-border px-2.5 py-1.5 text-xs font-medium text-danger-text transition-colors hover:bg-danger-subtle disabled:cursor-not-allowed disabled:opacity-50">
                                Try again
                            </button>
                        </div>
                    </x-alert>
                </div>

                <!-- Step 1: Transaction Details -->
                <div x-show="step === 1" x-cloak class="rounded-xl border border-border bg-surface p-6">
                    <h2 class="mb-1 text-lg font-semibold text-ink">Transaction Details</h2>
                    <p class="mb-5 text-sm text-ink-muted">
                        The local amount sets the customer's due-diligence tier. Standard and Enhanced tiers ask for more documents.
                    </p>

                    <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <x-input :inline="true" name="customer_id" label="Customer ID"
                                     type="number" min="1" required
                                     help="The customer record ID — not the national ID number."
                                     x-model="formData.customer_id"
                                     @input="clearFieldError('customer_id')" />
                            <p x-show="fieldError('customer_id')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('customer_id')"></p>
                        </div>

                        <div>
                            <x-select :inline="true" name="type" label="Transaction Type"
                                      :options="['Buy' => 'Buy', 'Sell' => 'Sell']"
                                      placeholder="Select type" required
                                      x-model="formData.type"
                                      @input="clearFieldError('type')" />
                            <p x-show="fieldError('type')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('type')"></p>
                        </div>

                        <div>
                            <x-select :inline="true" name="currency_code" label="Currency"
                                      :options="$currencies ?? []"
                                      placeholder="Select currency" required
                                      x-model="formData.currency_code"
                                      @input="clearFieldError('currency_code')" />
                            <p x-show="fieldError('currency_code')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('currency_code')"></p>
                        </div>

                        <div>
                            <x-input :inline="true" name="quantity" label="Foreign Amount"
                                     type="number" step="0.01" min="0.01" max="9999999999.9999" required
                                     x-model="formData.quantity"
                                     @input="clearFieldError('quantity')" />
                            <p x-show="fieldError('quantity')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('quantity')"></p>
                        </div>

                        <div>
                            <x-input :inline="true" name="rate" label="Exchange Rate"
                                     type="number" step="0.0001" min="0.0001" max="999999" required
                                     x-model="formData.rate"
                                     @input="clearFieldError('rate')" />
                            <p x-show="currencyInverse()" x-cloak class="mt-1 text-xs text-ink-muted">
                                <span x-text="formData.currency_code"></span> per RM <span x-text="currencyUnit()"></span>
                            </p>
                            <p x-show="!currencyInverse() && currencyUnit() > 1" x-cloak class="mt-1 text-xs text-ink-muted">
                                in MYR per <span x-text="currencyUnit()"></span> <span x-text="formData.currency_code"></span>
                            </p>
                            <p x-show="fieldError('rate')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('rate')"></p>
                        </div>

                        <x-input :inline="true" name="amount_myr" label="Local Amount (MYR)"
                                 readonly class="font-semibold"
                                 x-model="amountMyr" />

                        <div>
                            <x-select :inline="true" name="purpose" label="Purpose"
                                      :options="$purposes"
                                      placeholder="Select purpose" required
                                      x-model="formData.purpose"
                                      @input="clearFieldError('purpose')" />
                            <p x-show="fieldError('purpose')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('purpose')"></p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input :inline="true" name="source_of_funds" label="Source of Funds"
                                     placeholder="e.g. Salary, Savings, Business Income" required maxlength="255"
                                     x-model="formData.source_of_funds"
                                     @input="clearFieldError('source_of_funds')" />
                            <p x-show="fieldError('source_of_funds')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('source_of_funds')"></p>
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
                        <div>
                            <x-input :inline="true" name="occupation" label="Occupation"
                                     required maxlength="255"
                                     x-model="formData.occupation"
                                     @input="clearFieldError('occupation')" />
                            <p x-show="fieldError('occupation')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('occupation')"></p>
                        </div>

                        <div>
                            <x-input :inline="true" name="employer_name" label="Employer Name"
                                     maxlength="255"
                                     x-model="formData.employer_name"
                                     @input="clearFieldError('employer_name')" />
                            <p x-show="fieldError('employer_name')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('employer_name')"></p>
                        </div>

                        <div class="md:col-span-2">
                            <x-input :inline="true" name="employer_address" label="Employer Address"
                                     maxlength="1000"
                                     x-model="formData.employer_address"
                                     @input="clearFieldError('employer_address')" />
                            <p x-show="fieldError('employer_address')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('employer_address')"></p>
                        </div>

                        <div>
                            <x-input :inline="true" name="annual_volume_myr"
                                     label="Estimated Annual Volume"
                                     type="number" step="0.01" min="0"
                                     x-model="formData.annual_volume_myr"
                                     @input="clearFieldError('annual_volume_myr')" />
                            <p x-show="fieldError('annual_volume_myr')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                               x-text="fieldError('annual_volume_myr')"></p>
                        </div>

                        <!-- Enhanced CDD only -->
                        <template x-if="requireEnhanced">
                            <div class="grid grid-cols-1 gap-4 border-t border-border pt-4 md:col-span-2 md:grid-cols-2">
                                <div>
                                    <x-input :inline="true" name="beneficial_owner" label="Beneficial Owner"
                                             required maxlength="255"
                                             x-model="formData.beneficial_owner"
                                             @input="clearFieldError('beneficial_owner')" />
                                    <p x-show="fieldError('beneficial_owner')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                                       x-text="fieldError('beneficial_owner')"></p>
                                </div>

                                <div>
                                    <x-select :inline="true" name="expected_frequency" label="Expected Frequency"
                                              :options="$frequencies"
                                              placeholder="Select frequency" required
                                              x-model="formData.expected_frequency"
                                              @input="clearFieldError('expected_frequency')" />
                                    <p x-show="fieldError('expected_frequency')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                                       x-text="fieldError('expected_frequency')"></p>
                                </div>

                                <div class="md:col-span-2">
                                    <x-textarea :inline="true" name="source_of_wealth" label="Source of Wealth"
                                                rows="2" required maxlength="500"
                                                x-model="formData.source_of_wealth"
                                                @input="clearFieldError('source_of_wealth')" />
                                    <p x-show="fieldError('source_of_wealth')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                                       x-text="fieldError('source_of_wealth')"></p>
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
                                         @change="pickFile('proof_of_address', $event)" />
                                <p x-show="fieldError('proof_of_address')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                                   x-text="fieldError('proof_of_address')"></p>
                                <p x-show="fileName('proof_of_address')" x-cloak class="mt-1 text-xs text-ink-muted"
                                   x-text="fileName('proof_of_address')"></p>
                            </div>

                            <div x-show="requirePassport" x-cloak>
                                <x-input :inline="true" name="passport" type="file"
                                         required accept=".pdf,.jpg,.jpeg,.png"
                                         label="Passport"
                                         @change="pickFile('passport', $event)" />
                                <p x-show="fieldError('passport')" x-cloak class="mt-1 text-xs text-danger-text" role="alert"
                                   x-text="fieldError('passport')"></p>
                                <p x-show="fileName('passport')" x-cloak class="mt-1 text-xs text-ink-muted"
                                   x-text="fileName('passport')"></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3: Review & Confirm -->
                <div x-show="step === 3" x-cloak class="rounded-xl border border-border bg-surface p-6">
                    <h2 class="mb-4 text-lg font-semibold text-ink">Review &amp; Confirm</h2>

                    <dl class="text-sm">
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Customer</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.customer_name || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Type</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.type || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Currency</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.currency || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Foreign Amount</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="(summary.currency || '') + ' ' + (summary.quantity || '—')"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Rate</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.rate || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 font-medium text-ink">Local Amount (MYR)</dt>
                            <dd class="min-w-0 break-words text-right text-base font-semibold text-ink" x-text="summary.amount_myr || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Purpose</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.purpose || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">Source of Funds</dt>
                            <dd class="min-w-0 break-words text-right text-ink" x-text="summary.source_of_funds || '—'"></dd>
                        </div>
                        <div class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-ink-muted">CDD Level</dt>
                            <dd class="min-w-0 break-words text-right text-ink"
                                x-text="(summary.cdd_level || '—').charAt(0).toUpperCase() + (summary.cdd_level || '').slice(1)"></dd>
                        </div>
                        <div x-show="summary.hold_required" x-cloak
                             class="flex justify-between gap-4 border-b border-border py-2.5">
                            <dt class="shrink-0 text-warning-text">Status</dt>
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

                    <p x-show="!(result.status || '').length" x-cloak
                       class="mb-4 text-sm text-ink-muted">
                        This transaction has been recorded.
                    </p>
                    <p x-show="(result.status || '').toUpperCase() === 'PENDINGAPPROVAL'" x-cloak
                       class="mb-4 text-sm text-warning-text">
                        This transaction is pending. A compliance officer must review it before it can complete.
                    </p>
                    <p x-show="(result.status || '').toUpperCase() === 'COMPLETED'" x-cloak
                       class="mb-4 text-sm text-ink-muted">
                        This transaction is complete.
                    </p>

                    <div class="flex flex-wrap justify-center gap-3">
                        <a x-show="result.id" x-cloak :href="'/transactions/' + result.id"
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
                <p x-show="(step === 1 && !validStep1()) || (step === 2 && !validStep2())" x-cloak
                   class="mb-2 text-right text-xs text-ink-muted">
                    To continue:
                    <span x-text="(step === 1 ? missingStep1() : missingStep2()).join(', ')"></span>
                </p>

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
