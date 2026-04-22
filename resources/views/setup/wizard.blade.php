<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Setup Wizard - CEMS-MY</title>
    @vite(['resources/css/app.css'])
    <style>
        .setup-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .setup-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 700px;
            padding: 40px;
        }
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 15px;
            left: 10%;
            right: 10%;
            height: 4px;
            background: #e0e0e0;
            z-index: 0;
        }
        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            z-index: 1;
        }
        .step-number {
            width: 34px;
            height: 34px;
            border-radius: 50%;
            background: #e0e0e0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            color: #666;
            margin-bottom: 8px;
        }
        .step.active .step-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        .step-label {
            font-size: 12px;
            color: #666;
        }
        .step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
            transition: border-color 0.2s;
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        .currency-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        .currency-option {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .currency-option:hover {
            border-color: #667eea;
        }
        .currency-option.selected {
            border-color: #667eea;
            background: #f0f4ff;
        }
        .currency-option input {
            display: none;
        }
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
            margin-right: 10px;
        }
        .navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
        }
        .stock-inputs {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        .success-message {
            text-align: center;
            padding: 40px;
        }
        .success-icon {
            width: 80px;
            height: 80px;
            background: #28a745;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 40px;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #667eea;">Setup Wizard</h1>
                <p style="color: #666;">Step {{ $step }} of 7</p>
            </div>

            <div class="step-indicator">
                <div class="step {{ $step >= 1 ? 'active' : '' }} {{ $step > 1 ? 'completed' : '' }}">
                    <div class="step-number">1</div>
                    <div class="step-label">Company</div>
                </div>
                <div class="step {{ $step >= 2 ? 'active' : '' }} {{ $step > 2 ? 'completed' : '' }}">
                    <div class="step-number">2</div>
                    <div class="step-label">Admin</div>
                </div>
                <div class="step {{ $step >= 3 ? 'active' : '' }} {{ $step > 3 ? 'completed' : '' }}">
                    <div class="step-number">3</div>
                    <div class="step-label">Currencies</div>
                </div>
                <div class="step {{ $step >= 4 ? 'active' : '' }} {{ $step > 4 ? 'completed' : '' }}">
                    <div class="step-number">4</div>
                    <div class="step-label">Rates</div>
                </div>
<div class="step {{ $step >= 5 ? 'active' : '' }} {{ $step > 5 ? 'completed' : '' }}">
                <div class="step-number">5</div>
                <div class="step-label">Stock</div>
            </div>
            <div class="step {{ $step >= 6 ? 'active' : '' }} {{ $step > 6 ? 'completed' : '' }}">
                <div class="step-number">6</div>
                <div class="step-label">Opening Balance</div>
            </div>
            <div class="step {{ $step >= 7 ? 'active' : '' }}">
                <div class="step-number">7</div>
                <div class="step-label">Complete</div>
            </div>
            </div>

            @if($errors->any())
                <div style="background: #f8d7da; color: #721c24; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin: 8px 0 0 20px;">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $existingUsers = \App\Models\User::exists();
                $existingUserEmails = $existingUsers ? \App\Models\User::pluck('email')->toArray() : [];
            @endphp

            @if($step == 2 && $existingUsers)
                <div style="background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 16px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>ℹ️ Users already exist in the system</strong>
                    <p style="margin: 8px 0 0 0; font-size: 14px;">
                        Setup appears to be complete. You can login with existing credentials or create a new admin with a unique email.
                    </p>
                    @if(count($existingUserEmails) > 0)
                        <p style="margin: 8px 0 0 0; font-size: 13px;">
                            <strong>Existing emails:</strong> {{ implode(', ', array_slice($existingUserEmails, 0, 3)) }}{{ count($existingUserEmails) > 3 ? '...' : '' }}
                        </p>
                    @endif
                </div>
            @endif

            @if($step == 1)
                <form action="/setup/step/1" method="POST">
                    @csrf
                    <h2 style="margin-bottom: 20px;">Company Information</h2>
                    
                    <div class="form-group">
                        <label>Business Name *</label>
                        <input type="text" name="business_name" required placeholder="e.g., ABC Money Changer">
                    </div>

                    <div class="form-group">
                        <label>Business Address</label>
                        <input type="text" name="business_address" placeholder="e.g., 123 Main Street, Kuala Lumpur">
                    </div>

                    <div class="form-group">
                        <label>Business Phone</label>
                        <input type="text" name="business_phone" placeholder="e.g., +60 3-1234 5678">
                    </div>

                    <div class="form-group">
                        <label>Business Email</label>
                        <input type="email" name="business_email" placeholder="e.g., info@abcmoney.my">
                    </div>

                    <div class="navigation">
                        <div></div>
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                    </div>
                </form>

            @elseif($step == 2)
                <form action="/setup/step/2" method="POST">
                    @csrf
                    <h2 style="margin-bottom: 20px;">Create Admin User</h2>
                    
                    <div class="form-group">
                        <label>Admin Name *</label>
                        <input type="text" name="admin_name" value="{{ old('admin_name') }}" required placeholder="e.g., John Doe" style="{{ $errors->has('admin_name') ? 'border-color: #dc3545;' : '' }}">
                    </div>

                    <div class="form-group">
                        <label>Admin Email *</label>
                        <input type="email" name="admin_email" value="{{ old('admin_email') }}" required placeholder="e.g., admin@yourbusiness.com" style="{{ $errors->has('admin_email') ? 'border-color: #dc3545;' : '' }}">
                        @if($errors->has('admin_email'))
                            <p style="color: #dc3545; font-size: 14px; margin-top: 4px;">{{ $errors->first('admin_email') }}</p>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>Password *</label>
                        <input type="password" name="admin_password" required minlength="8" placeholder="Min 8 characters" style="{{ $errors->has('admin_password') ? 'border-color: #dc3545;' : '' }}">
                        @if($errors->has('admin_password'))
                            <p style="color: #dc3545; font-size: 14px; margin-top: 4px;">{{ $errors->first('admin_password') }}</p>
                        @endif
                    </div>

                    <div class="form-group">
                        <label>Confirm Password *</label>
                        <input type="password" name="admin_password_confirmation" required placeholder="Re-enter password">
                    </div>

                    <div class="navigation">
                        <a href="/setup/wizard?step=1" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                    </div>
                </form>

            @elseif($step == 3)
                <form action="/setup/step/3" method="POST">
                    @csrf
                    <h2 style="margin-bottom: 20px;">Select Currencies</h2>
                    
                    <div class="form-group">
                        <label>Base Currency (Your Local Currency) *</label>
                        <select name="base_currency" required>
                            <option value="MYR" selected>MYR - Malaysian Ringgit</option>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="SGD">SGD - Singapore Dollar</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Active Currencies (Select all you want to trade) *</label>
                        <div class="currency-grid">
                            @foreach($currencies as $currency)
                                @if($currency->code != 'MYR')
                                    <label class="currency-option">
                                        <input type="checkbox" name="active_currencies[]" value="{{ $currency->code }}" checked>
                                        <strong>{{ $currency->code }}</strong>
                                        <div style="font-size: 12px; color: #666;">{{ $currency->name }}</div>
                                    </label>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <div class="navigation">
                        <a href="/setup/wizard?step=2" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                    </div>
                </form>

            @elseif($step == 4)
                <form action="/setup/step/4" method="POST">
                    @csrf
                    <h2 style="margin-bottom: 20px;">Exchange Rates</h2>
                    
                    <div style="background: #f8f9ff; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                        <label style="display: flex; align-items: center; gap: 12px; cursor: pointer;">
                            <input type="checkbox" name="use_default_rates" value="1" checked style="width: 20px; height: 20px;">
                            <div>
                                <strong>Use Default Exchange Rates</strong>
                                <p style="margin: 4px 0 0; color: #666; font-size: 14px;">
                                    We'll set example rates. You can update them later at /exchange-rates
                                </p>
                            </div>
                        </label>
                    </div>

                    <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                        <strong>💡 Tip:</strong> Exchange rates change daily. You'll need to update them each morning before opening.
                    </div>

                    <div class="navigation">
                        <a href="/setup/wizard?step=3" class="btn btn-secondary">← Back</a>
                        <button type="submit" class="btn btn-primary">Next Step →</button>
                    </div>
                </form>

            @elseif($step == 5)
                <form action="/setup/step/5" method="POST">
                    @csrf
                    <h2 style="margin-bottom: 20px;">Initial Stock (Opening Float)</h2>
                    
                    <div class="form-group">
                        <label>Initial MYR Cash *</label>
                        <input type="number" name="initial_myr_cash" required min="0" step="0.01" value="100000" placeholder="e.g., 100000">
                        <p style="font-size: 12px; color: #666; margin-top: 4px;">Your starting Ringgit cash on hand</p>
                    </div>

                    <div class="form-group">
                        <label>Foreign Currency Stock (Optional)</label>
                        <div class="stock-inputs">
                            @foreach($currencies as $currency)
                                @if($currency->code != 'MYR')
                                    <div>
                                        <label style="font-size: 14px;">{{ $currency->code }}</label>
                                        <input type="number" name="initial_stock[{{ $currency->code }}]" min="0" step="0.01" value="10000" placeholder="0">
                                    </div>
                                @endif
                            @endforeach
                        </div>
                        <p style="font-size: 12px; color: #666; margin-top: 8px;">
                            Enter amounts you currently have. You can add more stock later.
                        </p>
                    </div>

<div class="navigation">
            <a href="/setup/wizard?step=4" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Next Step →</button>
        </div>
    </form>

    @elseif($step == 6)
    <form action="/setup/step/6" method="POST">
        @csrf
        <h2 style="margin-bottom: 20px;">Opening Balance (Accounting)</h2>

        <div style="background: #fff3cd; padding: 16px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
            <strong>ℹ️ What is this?</strong>
            <p style="margin: 8px 0 0 0;">Opening balance represents the initial cash position in your accounting books. This creates the starting journal entry for your business.</p>
        </div>

        <div class="form-group">
            <label>Opening Balance - MYR Cash *</label>
            <input type="number" name="opening_balance_myr" required min="0" step="0.01" value="100000" placeholder="e.g., 100000">
            <p style="font-size: 12px; color: #666; margin-top: 4px;">Your starting cash balance in Malaysian Ringgit (MYR)</p>
        </div>

        <div class="form-group">
            <label>Opening Balance - Foreign Currency Cash</label>
            <div class="stock-inputs">
                @foreach($currencies as $currency)
                    @if($currency->code != 'MYR')
                    <div>
                        <label style="font-size: 14px;">{{ $currency->code }}</label>
                        <input type="number" name="opening_balance_foreign[{{ $currency->code }}]" min="0" step="0.01" value="0" placeholder="0">
                    </div>
                    @endif
                @endforeach
            </div>
            <p style="font-size: 12px; color: #666; margin-top: 8px;">
                Enter the opening balance for each foreign currency in your books.
            </p>
        </div>

        <div class="navigation">
            <a href="/setup/wizard?step=5" class="btn btn-secondary">← Back</a>
            <button type="submit" class="btn btn-primary">Review & Complete →</button>
        </div>
    </form>

    @elseif($step == 7)
    <div class="success-message">
        <div class="success-icon">✓</div>
        <h2 style="margin-bottom: 16px;">Ready to Complete!</h2>
        <p style="color: #666; margin-bottom: 30px;">
            We'll set up your business with the information you provided.
        </p>

        <div style="background: #f8f9ff; padding: 20px; border-radius: 8px; text-align: left; margin-bottom: 30px;">
            <h3 style="margin-bottom: 12px;">What will be created:</h3>
            <ul style="list-style: none; padding: 0;">
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Company profile and head office branch</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Admin user account</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Chart of accounts (80+ GL accounts)</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Selected currencies</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Exchange rates</li>
                <li style="padding: 8px 0; border-bottom: 1px solid #e0e0e0;">✓ Initial stock positions</li>
                <li style="padding: 8px 0;">✓ Opening balance journal entry</li>
            </ul>
        </div>

        <button onclick="completeSetup()" class="btn btn-primary" style="width: 100%;">
            Complete Setup
        </button>
        <a href="/setup/wizard?step=6" class="btn btn-secondary" style="width: 100%; margin-top: 10px; display: inline-block; text-decoration: none; text-align: center;">
            Go Back
        </a>
    </div>
    @endif
        </div>
    </div>

    <script>
        document.querySelectorAll('.currency-option').forEach(option => {
            option.addEventListener('click', function() {
                this.classList.toggle('selected');
                const checkbox = this.querySelector('input');
                checkbox.checked = !checkbox.checked;
            });
        });

        async function completeSetup() {
            const btn = document.querySelector('button[onclick="completeSetup()"]');
            btn.disabled = true;
            btn.textContent = 'Setting up your business...';

            try {
                const response = await fetch('/setup/complete', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    window.location.href = result.redirect;
                } else {
                    alert('Error: ' + result.message);
                    btn.disabled = false;
                    btn.textContent = 'Complete Setup';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'Complete Setup';
            }
        }
    </script>
</body>
</html>
