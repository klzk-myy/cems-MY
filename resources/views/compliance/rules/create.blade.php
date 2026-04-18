@extends('layouts.base')

@section('title', 'Create AML Rule')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="card">
        <div class="card-header"><h3 class="card-title">Create AML Rule</h3></div>
        <div class="card-body">
            <form method="POST" action="/compliance/rules">
                @csrf
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select" required>
                        @foreach($ruleTypeOptions ?? [] as $value => $ruleType)
                            <option value="{{ $value }}">{{ $ruleType['label'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-textarea" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Conditions (JSON)</label>
                    <textarea name="conditions" class="form-textarea font-mono" rows="4" placeholder='{"field": "value"}'></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Rule Examples</label>
                    <select class="form-select" id="ruleExampleSelector">
                        <option value="">Select example to load...</option>
                        <option value="velocity">Velocity Rule</option>
                        <option value="structuring">Structuring Rule</option>
                        <option value="amount">Amount Threshold Rule</option>
                        <option value="frequency">Frequency Rule</option>
                        <option value="geographic">Geographic Rule</option>
                    </select>
                    <p class="text-sm text-[--color-ink-muted] mt-2">Select a rule type to load pre-configured example conditions</p>
                </div>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const examples = {
                        velocity: {
                            "rule_code": "VEL-001",
                            "name": "High Velocity Alert",
                            "description": "Detects excessive transaction volume within 24 hour rolling window",
                            "type": "velocity",
                            "conditions": {
                                "window_hours": 24,
                                "max_transactions": 10,
                                "cumulative_threshold": 50000
                            },
                            "action": "flag",
                            "risk_score": 25,
                            "is_active": true
                        },
                        structuring: {
                            "rule_code": "STR-001",
                            "name": "Structuring Detection (Daily)",
                            "description": "Detects transactions broken into amounts below RM10k reporting threshold over 24 hours (BNM Compliance Rule 3.2.1)",
                            "type": "structuring",
                            "conditions": {
                                "window_days": 1,
                                "min_transaction_count": 3,
                                "aggregate_threshold": 45000,
                                "max_individual_amount": 9999
                            },
                            "action": "hold",
                            "risk_score": 40,
                            "is_active": true
                        },
                        amount: {
                            "rule_code": "AMT-001",
                            "name": "Large Transaction Alert",
                            "description": "Triggers on all transactions equal or above RM50,000 threshold",
                            "type": "amount",
                            "conditions": {
                                "min_amount": 50000,
                                "currency": "MYR"
                            },
                            "action": "flag",
                            "risk_score": 20,
                            "is_active": true
                        },
                        frequency: {
                            "rule_code": "FREQ-001",
                            "name": "High Frequency Alert",
                            "description": "Detects rapid repeated transactions within short time window",
                            "type": "frequency",
                            "conditions": {
                                "window_hours": 0.25,
                                "max_transactions": 5
                            },
                            "action": "flag",
                            "risk_score": 25,
                            "is_active": true
                        },
                        geographic: {
                            "rule_code": "GEO-001",
                            "name": "FATF High-Risk Countries",
                            "description": "Flags transactions from customers nationals of FATF high risk jurisdictions",
                            "type": "geographic",
                            "conditions": {
                                "countries": ["IR", "KP", "SY", "MM", "AF"],
                                "match_field": "customer_nationality"
                            },
                            "action": "hold",
                            "risk_score": 50,
                            "is_active": true
                        }
                    };

                    document.getElementById('ruleExampleSelector').addEventListener('change', function() {
                        if (this.value && examples[this.value]) {
                            const rule = examples[this.value];
                            document.querySelector('input[name="name"]').value = rule.name;
                            document.querySelector('select[name="type"]').value = rule.type;
                            document.querySelector('textarea[name="description"]').value = rule.description;
                            document.querySelector('textarea[name="conditions"]').value = JSON.stringify(rule.conditions, null, 4);
                        }
                    });
                });
                </script>
                <div class="flex justify-end gap-3">
                    <a href="/compliance/rules" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
