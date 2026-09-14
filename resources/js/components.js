// Alpine.data() registrations for the @alpinejs/csp build.
//
// The CSP build cannot evaluate inline x-data object literals, so every
// component is registered here by name and referenced as x-data="name".
// Server-rendered values are passed via data-* attributes and read in init()
// through this.$el.dataset (see journalCreate, notificationBell,
// customerTypeahead, transactionWizard).

export function registerComponents(Alpine) {
    Alpine.data('transferModals', () => ({
        showCancelModal: false,
        showRejectModal: false,
    }));

    Alpine.data('customerModals', () => ({
        showFreeze: false,
        showUnfreeze: false,
        showClose: false,
    }));

    Alpine.data('branchModals', () => ({
        deactivateModal: false,
    }));

    Alpine.data('ratesPage', () => ({
        showOverride: false,
        overrideCurrency: '',
        overrideBuy: '',
        overrideSell: '',
        openOverride(detail) {
            this.overrideCurrency = detail.currency || '';
            this.overrideBuy = detail.buy || '';
            this.overrideSell = detail.sell || '';
            this.showOverride = true;
        },
    }));

    Alpine.data('screeningMatchModals', () => ({
        openModal: null,
    }));

    Alpine.data('findingsIndex', () => ({
        showDismiss: false,
        showCreateCase: false,
        findingId: null,
        findingRef: null,
        openDismiss(id, ref) {
            this.findingId = id;
            this.findingRef = ref;
            this.showDismiss = true;
        },
        openCreateCase(id, ref) {
            this.findingId = id;
            this.findingRef = ref;
            this.showCreateCase = true;
        },
    }));

    Alpine.data('riskCustomer', () => ({
        showRescreen: false,
    }));

    Alpine.data('reconciliationLines', () => ({
        lines: [{ date: '', reference: '', description: '', debit: '', credit: '' }],
        addLine() {
            this.lines.push({ date: '', reference: '', description: '', debit: '', credit: '' });
        },
        removeLine(index) {
            if (this.lines.length > 1) this.lines.splice(index, 1);
        },
    }));

    Alpine.data('journalShow', () => ({
        open: false,
    }));

    Alpine.data('journalCreate', () => ({
        accounts: [],
        lines: [
            { account: '', description: '', debit: '', credit: '' },
            { account: '', description: '', debit: '', credit: '' },
        ],
        init() {
            try {
                this.accounts = JSON.parse(this.$el.dataset.accounts || '[]');
            } catch (e) {
                this.accounts = [];
            }
        },
        addLine() {
            this.lines.push({ account: '', description: '', debit: '', credit: '' });
        },
        removeLine(index) {
            if (this.lines.length > 2) this.lines.splice(index, 1);
        },
        get totalDebit() {
            return this.lines.reduce((sum, l) => sum + (parseFloat(l.debit) || 0), 0).toFixed(2);
        },
        get totalCredit() {
            return this.lines.reduce((sum, l) => sum + (parseFloat(l.credit) || 0), 0).toFixed(2);
        },
        get difference() {
            return (parseFloat(this.totalDebit) - parseFloat(this.totalCredit)).toFixed(2);
        },
        get balanced() {
            return parseFloat(this.difference) === 0 && parseFloat(this.totalDebit) > 0;
        },
    }));

    Alpine.data('budgetRows', () => ({
        rows: [{ account_code: '', amount: '' }],
        addRow() {
            this.rows.push({ account_code: '', amount: '' });
        },
        removeRow(index) {
            if (this.rows.length > 1) this.rows.splice(index, 1);
        },
    }));

    Alpine.data('alertBox', () => ({
        shown: true,
    }));

    Alpine.data('appShell', () => ({
        sidebarCollapsed: false,
    }));

    Alpine.data('customerTypeahead', () => ({
        query: '',
        selectedId: '',
        results: [],
        screening: null,
        open: false,
        loading: false,
        active: -1,
        controller: null,
        init() {
            this.query = this.$el.dataset.initialName || '';
            this.selectedId = this.$el.dataset.initialId || '';
            this.searchUrl = this.$el.dataset.searchUrl || '';
        },
        search() {
            if (this.controller) this.controller.abort();
            const q = this.query.trim();
            if (this.selectedId) this.selectedId = '';
            if (q.length < 2) {
                this.results = [];
                this.screening = null;
                this.open = false;
                this.loading = false;
                return;
            }
            this.controller = new AbortController();
            this.loading = true;
            fetch(this.searchUrl + '?query=' + encodeURIComponent(q), {
                signal: this.controller.signal,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            }).then(r => r.json()).then(data => {
                this.results = data.results || [];
                this.screening = data.query_screening || null;
                this.active = this.results.length ? 0 : -1;
                this.open = true;
                this.loading = false;
            }).catch(e => { if (e.name !== 'AbortError') this.loading = false; });
        },
        move(step) {
            if (!this.open || !this.results.length) return;
            this.active = (this.active + step + this.results.length) % this.results.length;
        },
        choose() {
            if (this.open && this.active >= 0 && this.results[this.active]) {
                this.select(this.results[this.active]);
            }
        },
        enter(event) {
            if (this.open) {
                event.preventDefault();
                this.choose();
            }
        },
        focus() {
            if (this.results.length) this.open = true;
        },
        select(c) {
            this.selectedId = String(c.id);
            this.query = c.full_name;
            this.open = false;
            this.screening = null;
        },
        get banner() {
            if (this.selectedId) {
                const c = this.results.find(r => String(r.id) === this.selectedId);
                if (c && c.is_sanctioned) {
                    return { text: 'Sanctions flag: this customer has a screening hit — the transaction will be blocked.', danger: true };
                }
                return null;
            }
            if (!this.screening || this.screening.action === 'clear') return null;
            const m = (this.screening.matches || [])[0];
            const name = m ? m.entity_name + (m.list ? ' — ' + m.list : '') : '';
            if (this.screening.action === 'block') {
                return { text: 'Possible sanctions BLOCK match' + (name ? ': ' + name : '') + ' (score ' + this.screening.score + ')', danger: true };
            }
            return { text: 'Possible sanctions match' + (name ? ': ' + name : '') + ' (score ' + this.screening.score + ')', danger: false };
        },
    }));

    Alpine.data('notificationBell', () => ({
        open: false,
        count: 0,
        dlq: 0,
        poll() {
            fetch(this.pollUrl, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            })
                .then(r => r.json())
                .then(d => { this.count = d.count; this.dlq = d.dlq_count; })
                .catch(() => {});
        },
        init() {
            // Server data is already fresh on page load (NotificationComposer);
            // only the periodic refresh is needed to pick up changes made in
            // other tabs. Polling is exempt from the session idle timer.
            this.count = parseInt(this.$el.dataset.count, 10) || 0;
            this.dlq = parseInt(this.$el.dataset.dlq, 10) || 0;
            this.pollUrl = this.$el.dataset.pollUrl || '';
            setInterval(() => this.poll(), 60000);
        },
    }));

    Alpine.data('transactionWizard', () => ({
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
            idempotency_key: '',
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
        currencies: {},
        apiBase: '',
        csrf: '',
        idempotencyKey: '',
        init() {
            this.apiBase = this.$el.dataset.apiBase || '';
            this.idempotencyKey = this.$el.dataset.idempotencyKey || '';
            this.formData.idempotency_key = this.idempotencyKey;
            this.csrf = document.querySelector('meta[name=csrf-token]')?.content ?? '';
            try {
                this.currencies = JSON.parse(this.$el.dataset.currencies || '{}');
            } catch (e) {
                this.currencies = {};
            }
            const branchId = this.$el.dataset.branchId;
            if (branchId) {
                this.fetch(this.apiBase + '/branches/' + branchId + '/counters')
                    .then(r => r.json())
                    .then(d => { this.counters = Object.fromEntries((d.data ?? []).map(c => [c.code, c.name])); })
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
        get cddLevel() { return (this.wizard.cdd_level || '').toLowerCase(); },
        get requireProofOfAddress() { return this.cddLevel === 'standard' || this.cddLevel === 'enhanced'; },
        get requirePassport() { return this.cddLevel === 'enhanced'; },
        get requireEnhanced() { return this.cddLevel === 'enhanced'; },
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
        submitStep() {
            if (this.step === 1 && this.validStep1()) return this.callStep1();
            if (this.step === 2 && this.validStep2()) return this.callStep2();
            if (this.step === 3) return this.callStep3();
        },
        async fetch(url, opts = {}) {
            return fetch(url, { credentials: 'same-origin', ...opts, headers: { 'X-CSRF-TOKEN': this.csrf, Accept: 'application/json', ...(opts.headers ?? {}) } });
        },
        async callStep1() {
            this.loading = true;
            this.errorMessage = '';
            this.wizard.blockedMessage = '';
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
            this.loading = true;
            this.errorMessage = '';
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
            this.loading = true;
            this.errorMessage = '';
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
            this.errorMessage = '';
            this.wizard = { session_id: '', cdd_level: '', cdd_description: '', hold_required: false, risk_flags: [], required_documents: [], blockedMessage: '' };
            this.summary = {};
            this.result = { id: '', number: '', status: '' };
            this.formData = { ...this.formData, customer_id: '', type: '', currency_code: '', amount_foreign: '', rate: '', till_id: '', purpose: '', source_of_funds: '', occupation: '', employer_name: '', employer_address: '', annual_volume_estimate: '', beneficial_owner: '', source_of_wealth: '', expected_frequency: '', idempotency_key: this.idempotencyKey };
            this.files = { proof_of_address: null, passport: null };
        },
    }));
}
