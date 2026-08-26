<x-app-layout title="Transaction Wizard">
    <div class="space-y-6">
        <x-page-header title="New Transaction" description="Step-by-step transaction creation wizard" />

        <div x-data="{
            step: 1,
            totalSteps: 3,
            loading: false,
            errorMessage: '',
            counters: {},
            formData: {
                customer_id: '',
                type: '',
                currency_code: '',
                amount_foreign: '',
                rate: '',
                till_id: '',
                purpose: '',
                source_of_funds: '',
                idempotency_key: '{{ Str::uuid() }}',
                occupation: '',
                employer_name: '',
                employer_address: '',
                annual_volume_estimate: '',
                beneficial_owner: '',
                source_of_wealth: '',
                expected_frequency: '',
            },
            files: { proof_of_address: null, passport: null },
            wizard: { session_id: '', cdd_level: '', cdd_description: '', hold_required: false, risk_flags: [], required_documents: [], blockedMessage: '' },
            summary: {},
            result: { id: '', number: '', status: '' },
            currencies: { USD: 'USD', EUR: 'EUR', GBP: 'GBP', SGD: 'SGD', JPY: 'JPY', AUD: 'AUD', CN: 'CNY', THB: 'THB', IDR: 'IDR' },
            apiBase: '{{ url('api/v1') }}',
            csrf: document.querySelector('meta[name=csrf-token]')?.content ?? '',
            init() {
                const branchId = {{ auth()->user()?->branch_id ?? 'null' }};
                if (branchId) {
                    this.fetch('{{ url('api/v1/branches') }}/' + branchId + '/counters')
                        .then(r => r.json()).then(d => { this.counters = Object.fromEntries((d.data ?? []).map(c => [c.code, c.name])); })
                        .catch(() => {});
                }
            },
            get amountLocal() {
                const f = parseFloat(this.formData.amount_foreign) || 0;
                const r = parseFloat(this.formData.rate) || 0;
                return (f * r).toFixed(2);
            },
            get foreignFormatted() {
                const f = parseFloat(this.formData.amount_foreign);
                return isNaN(f) ? '—' : (this.formData.currency_code + ' ' + f.toFixed(2));
            },
            get requireProofOfAddress() { return this.wizard.cdd_level === 'standard' || this.wizard.cdd_level === 'enhanced'; },
            get requirePassport() { return this.wizard.cdd_level === 'enhanced'; },
            get requireEnhanced() { return this.wizard.cdd_level === 'enhanced'; },
            payload() {
                return {
                    customer_id: parseInt(this.formData.customer_id) || null,
                    type: this.formData.type,
                    currency_code: this.formData.currency_code,
                    amount_foreign: parseFloat(this.formData.amount_foreign),
                    rate: parseFloat(this.formData.rate),
                    till_id: this.formData.till_id,
                    purpose: this.formData.purpose,
                    source_of_funds: this.formData.source_of_funds,
                };
            },
            validStep1() {
                const p = this.payload();
                return p.customer_id && p.type && p.currency_code && p.amount_foreign > 0 && p.rate > 0 && p.till_id && p.purpose && p.source_of_funds;
            },
            validStep2() {
                if (!this.formData.occupation) return false;
                if (this.requireProofOfAddress && !this.files.proof_of_address) return false;
                if (this.requirePassport && !this.files.passport) return false;
                if (this.requireEnhanced) {
                    if (!this.formData.beneficial_owner || !this.formData.source_of_wealth || !this.formData.expected_frequency) return false;
                }
                return true;
            },
            validStep3() { return true; },
            async fetch(url, opts = {}) {
                return fetch(url, { credentials: 'same-origin', ...opts, headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(opts.headers ?? {}) } });
            },
            async callStep1() {
                this.loading = true; this.errorMessage = ''; this.wizard.blockedMessage = '';
                try {
                    const res = await this.fetch(this.apiBase + '/wizard/transactions/step1', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(this.payload()),
                    });
                    const data = await res.json();
                    if (res.status === 403 && data.status === 'blocked') {
                        this.wizard.blockedMessage = data.message;
                        return;
                    }
                    if (!res.ok) { this.errorMessage = data.message || 'Request failed'; return; }
                    this.wizard.session_id = data.wizard_session_id;
                    this.wizard.cdd_level = data.cdd_level;
                    this.wizard.cdd_description = data.cdd_description;
                    this.wizard.hold_required = data.hold_required;
                    this.wizard.risk_flags = data.risk_flags ?? [];
                    this.wizard.required_documents = data.required_documents ?? [];
                    this.step = 2;
                } catch (e) {
                    this.errorMessage = 'Network error — please retry.';
                } finally { this.loading = false; }
            },
            async callStep2() {
                this.loading = true; this.errorMessage = '';
                try {
                    const fd = new FormData();
                    fd.append('wizard_session_id', this.wizard.session_id);
                    fd.append('cdd_level', this.wizard.cdd_level);
                    fd.append('customer[occupation]', this.formData.occupation);
                    fd.append('customer[employer_name]', this.formData.employer_name);
                    fd.append('customer[employer_address]', this.formData.employer_address);
                    fd.append('customer[annual_volume_estimate]', this.formData.annual_volume_estimate);
                    if (this.requireEnhanced) {
                        fd.append('customer[beneficial_owner]', this.formData.beneficial_owner);
                        fd.append('customer[source_of_wealth]', this.formData.source_of_wealth);
                        fd.append('transaction[expected_frequency]', this.formData.expected_frequency);
                    }
                    if (this.files.proof_of_address) fd.append('customer[proof_of_address]', this.files.proof_of_address);
                    if (this.files.passport) fd.append('customer[passport]', this.files.passport);
                    const res = await this.fetch(this.apiBase + '/wizard/transactions/step2', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!res.ok) { this.errorMessage = data.message || 'Request failed'; return; }
                    this.summary = data.transaction_summary;
                    this.step = 3;
                } catch (e) {
                    this.errorMessage = 'Network error — please retry.';
                } finally { this.loading = false; }
            },
            async callStep3() {
                this.loading = true; this.errorMessage = '';
                try {
                    const res = await this.fetch(this.apiBase + '/wizard/transactions/step3', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            wizard_session_id: this.wizard.session_id,
                            confirm_details: true,
                            idempotency_key: this.formData.idempotency_key,
                        }),
                    });
                    const data = await res.json();
                    if (!res.ok) { this.errorMessage = data.message || 'Request failed'; return; }
                    this.result = { id: data.transaction_id, number: data.transaction_number, status: data.transaction_status };
                    this.step = 4;
                } catch (e) {
                    this.errorMessage = 'Network error — please retry.';
                } finally { this.loading = false; }
            },
            reset() {
                this.step = 1;
                this.errorMessage = ''; this.wizard.blockedMessage = '';
                this.wizard = { session_id: '', cdd_level: '', cdd_description: '', hold_required: false, risk_flags: [], required_documents: [], blockedMessage: '' };
                this.summary = {}; this.result = { id: '', number: '', status: '' };
                this.formData = { ...this.formData, customer_id: '', type: '', currency_code: '', amount_foreign: '', rate: '', till_id: '', purpose: '', source_of_funds: '', occupation: '', employer_name: '', employer_address: '', annual_volume_estimate: '', beneficial_owner: '', source_of_wealth: '', expected_frequency: '', idempotency_key: '{{ Str::uuid() }}' };
                this.files = { proof_of_address: null, passport: null };
            },
        }" class="max-w-4xl mx-auto">

            <!-- Step Indicator -->
            <div class="flex items-center justify-between mb-8">
                <template x-for="i in totalSteps" :key="i">
                    <div class="flex items-center" :class="i < totalSteps ? 'flex-1' : ''">
                        <div class="flex items-center justify-center w-10 h-10 rounded-full border-2 transition-all"
                             :class="step >= i ? 'bg-primary text-white border-primary' : 'border-gray-300 text-gray-400'">
                            <span x-text="i"></span>
                        </div>
                        <div x-show="i < totalSteps" class="flex-1 h-0.5 mx-2"
                             :class="step > i ? 'bg-primary' : 'bg-gray-200'"></div>
                    </div>
                </template>
            </div>

            <!-- Error Message -->
            <div x-show="errorMessage" class="mb-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm" x-text="errorMessage"></div>

            <!-- Step 1: Transaction Details -->
            <div x-show="step === 1" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-4">Step 1: Transaction Details</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Customer ID</label>
                        <input type="number" x-model="formData.customer_id" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Transaction Type</label>
                        <select x-model="formData.type" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                            <option value="">Select type</option>
                            <template x-for="(label, val) in { buy: 'Buy', sell: 'Sell' }" :key="val">
                                <option :value="val" x-text="label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Currency</label>
                        <select x-model="formData.currency_code" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                            <option value="">Select currency</option>
                            <template x-for="(label, val) in currencies" :key="val">
                                <option :value="val" x-text="label"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Foreign Amount</label>
                        <input type="number" step="0.01" x-model="formData.amount_foreign" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Exchange Rate</label>
                        <input type="number" step="0.0001" x-model="formData.rate" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Local Amount (MYR)</label>
                        <input type="text" :value="amountLocal" readonly class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg bg-gray-50">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Till / Counter</label>
                        <select x-model="formData.till_id" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                            <option value="">Select till</option>
                            <template x-for="(name, code) in counters" :key="code">
                                <option :value="code" x-text="name + ' (' + code + ')'"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Purpose</label>
                        <select x-model="formData.purpose" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                            <option value="">Select purpose</option>
                            <template x-for="p in ['Travel', 'Education', 'Medical', 'Business', 'Investment', 'Family Support', 'Migration', 'Other']" :key="p">
                                <option :value="p" x-text="p"></option>
                            </template>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Source of Funds</label>
                        <input type="text" x-model="formData.source_of_funds" placeholder="e.g. Salary, Savings, Business Income" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                </div>

                <!-- Blocked banner -->
                <div x-show="wizard.blockedMessage" class="mt-4 p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <strong>Transaction blocked:</strong> <span x-text="wizard.blockedMessage"></span>
                </div>
            </div>

            <!-- Step 2: Customer Details & CDD -->
            <div x-show="step === 2" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-1">Step 2: Customer Details</h2>
                <p class="text-sm text-gray-500 mb-4">
                    <span x-text="wizard.cddDescription || 'Customer due diligence information'"></span>
                    <span x-show="wizard.hold_required" class="ml-2 inline-block px-2 py-0.5 bg-amber-100 text-amber-700 rounded text-xs">Compliance hold will apply</span>
                </p>

                <template x-if="wizard.risk_flags.length">
                    <div class="mb-4 p-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-lg text-sm">
                        <strong>Risk flags detected:</strong>
                        <ul class="list-disc ml-5 mt-1">
                            <template x-for="f in wizard.risk_flags" :key="f"><li x-text="f"></li></template>
                        </ul>
                    </div>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium mb-1">Occupation *</label>
                        <input type="text" x-model="formData.occupation" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Employer Name</label>
                        <input type="text" x-model="formData.employer_name" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium mb-1">Employer Address</label>
                        <input type="text" x-model="formData.employer_address" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Estimated Annual Volume</label>
                        <input type="number" step="0.01" x-model="formData.annual_volume_estimate" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                    </div>

                    <!-- Enhanced CDD only -->
                    <template x-if="requireEnhanced">
                        <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-[#e5e5e5] pt-4 mt-2">
                            <div>
                                <label class="block text-sm font-medium mb-1">Beneficial Owner *</label>
                                <input type="text" x-model="formData.beneficial_owner" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                            </div>
                            <div>
                                <label class="block text-sm font-medium mb-1">Expected Frequency *</label>
                                <select x-model="formData.expected_frequency" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg">
                                    <option value="">Select frequency</option>
                                    <template x-for="f in ['weekly', 'monthly', 'quarterly', 'annually']" :key="f">
                                        <option :value="f" x-text="f.charAt(0).toUpperCase() + f.slice(1)"></option>
                                    </template>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-medium mb-1">Source of Wealth *</label>
                                <textarea x-model="formData.source_of_wealth" rows="2" class="w-full px-4 py-2.5 text-sm border border-[#e5e5e5] rounded-lg"></textarea>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Document Uploads -->
                <div class="mt-6 border-t border-[#e5e5e5] pt-4">
                    <h3 class="text-sm font-semibold mb-3">Required Documents</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div x-show="requireProofOfAddress || requirePassport" class="md:col-span-2">
                            <ul class="text-xs text-gray-500 mb-2">
                                <template x-for="d in wizard.required_documents" :key="d.type">
                                    <li>
                                        <span x-text="d.label"></span>:
                                        <span :class="d.required ? 'text-red-600 font-medium' : 'text-gray-400'" x-text="d.required ? 'Required' : 'Optional'"></span>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <div x-show="requireProofOfAddress || requirePassport">
                            <label class="block text-sm font-medium mb-1">
                                Proof of Address <span x-show="requireProofOfAddress" class="text-red-500">*</span>
                            </label>
                            <input type="file" @change="files.proof_of_address = $event.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm border border-[#e5e5e5] rounded-lg p-2">
                        </div>
                        <div x-show="requirePassport">
                            <label class="block text-sm font-medium mb-1">
                                Passport <span class="text-red-500">*</span>
                            </label>
                            <input type="file" @change="files.passport = $event.target.files[0]" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-sm border border-[#e5e5e5] rounded-lg p-2">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Step 3: Review & Confirm -->
            <div x-show="step === 3" class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                <h2 class="text-lg font-semibold mb-4">Step 3: Review & Confirm</h2>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Customer</span><span x-text="summary.customer_name || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Type</span><span x-text="summary.type || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Currency</span><span x-text="summary.currency || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Foreign Amount</span><span x-text="(summary.currency || '') + ' ' + (summary.amount_foreign || '—')"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Rate</span><span x-text="summary.rate || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5] font-bold"><span class="text-ink-muted">Local Amount (MYR)</span><span x-text="summary.amount_local || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Purpose</span><span x-text="summary.purpose || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">Source of Funds</span><span x-text="summary.source_of_funds || '—'"></span></div>
                    <div class="flex justify-between py-2 border-b border-[#e5e5e5]"><span class="text-ink-muted">CDD Level</span><span x-text="(summary.cdd_level || '—').charAt(0).toUpperCase() + (summary.cdd_level || '').slice(1)"></span></div>
                    <div x-show="summary.hold_required" class="flex justify-between py-2 border-b border-[#e5e5e5] text-amber-700"><span class="text-ink-muted">Status</span><span>Pending Compliance Approval</span></div>
                </div>
            </div>

            <!-- Step 4: Success -->
            <div x-show="step === 4" class="bg-white border border-[#e5e5e5] rounded-xl p-6 text-center">
                <div class="w-16 h-16 mx-auto mb-4 bg-green-100 rounded-full flex items-center justify-center">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h2 class="text-lg font-semibold mb-1">Transaction Created</h2>
                <p class="text-sm text-gray-500 mb-4">
                    <span x-text="'Reference: ' + (result.number || '—')"></span>
                    <span class="ml-2 inline-block px-2 py-0.5 bg-gray-100 rounded text-xs" x-text="(result.status || '—').toUpperCase()"></span>
                </p>
                <div class="flex justify-center gap-3">
                    <a :href="'/transactions/' + result.id" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">View Transaction</a>
                    <button @click="reset()" class="px-4 py-2 text-sm font-medium rounded-lg border border-[#e5e5e5]">New Transaction</button>
                </div>
            </div>

            <!-- Navigation -->
            <div class="flex justify-between mt-6">
                <button x-show="step > 1 && step < 4" @click="step--" :disabled="loading"
                        class="px-4 py-2 text-sm font-medium rounded-lg border border-[#e5e5e5] disabled:opacity-50">
                    Previous
                </button>
                <div x-show="step === 1"></div>
                <button x-show="step === 1" @click="callStep1()" :disabled="loading || !validStep1()"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] disabled:opacity-50">
                    <span x-show="!loading">Continue</span>
                    <span x-show="loading">Checking...</span>
                </button>
                <button x-show="step === 2" @click="callStep2()" :disabled="loading || !validStep2()"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] disabled:opacity-50">
                    <span x-show="!loading">Review</span>
                    <span x-show="loading">Saving...</span>
                </button>
                <button x-show="step === 3" @click="callStep3()" :disabled="loading"
                        class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626] disabled:opacity-50">
                    <span x-show="!loading">Confirm &amp; Submit</span>
                    <span x-show="loading">Submitting...</span>
                </button>
            </div>
        </div>
    </div>
</x-app-layout>
