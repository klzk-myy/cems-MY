<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CEMS-MY Setup</title>
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
            max-width: 600px;
            padding: 40px;
        }
        .setup-logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .setup-logo h1 {
            color: #667eea;
            font-size: 2rem;
            font-weight: bold;
        }
        .setup-logo p {
            color: #666;
            margin-top: 8px;
        }
        .progress-bar {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 30px;
        }
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transition: width 0.3s ease;
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
        .btn {
            width: 100%;
            padding: 14px;
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
            margin-top: 10px;
        }
        .setup-option {
            border: 2px solid #e0e0e0;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 16px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .setup-option:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        .setup-option h3 {
            color: #333;
            margin-bottom: 8px;
        }
        .setup-option p {
            color: #666;
            font-size: 14px;
        }
        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 16px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-logo">
                <h1>CEMS-MY</h1>
                <p>Currency Exchange Management System</p>
            </div>

            @if($isSetupComplete)
                <div class="alert alert-success">
                    <strong>Setup Complete!</strong> Your system is ready to use.
                </div>
                <div style="text-align: center; margin-top: 20px;">
                    <a href="/login" class="btn btn-primary">Go to Login</a>
                </div>
            @else
                <div class="alert alert-info">
                    Welcome! Let's set up your currency exchange business.
                </div>

                <div style="margin-bottom: 30px;">
                    <h2 style="margin-bottom: 20px;">Choose Setup Method</h2>
                    
                    <a href="/setup/wizard" style="text-decoration: none; color: inherit;">
                        <div class="setup-option">
                            <h3>Step-by-Step Wizard</h3>
                            <p>Guided setup with customization options for your business needs.</p>
                        </div>
                    </a>

                    <div class="setup-option" onclick="showQuickSetup()">
                        <h3>Quick Setup (Recommended)</h3>
                        <p>One-click setup with default values. Perfect for testing or demo.</p>
                    </div>
                </div>

                <div id="quickSetupForm" style="display: none;">
                    <h3 style="margin-bottom: 20px;">Quick Setup</h3>
                    <form id="setupForm">
                        <div class="form-group">
                            <label>Business Name</label>
                            <input type="text" name="business_name" value="My Exchange Business" required>
                        </div>

                        <div class="form-group">
                            <label>Admin Email</label>
                            <input type="email" name="admin_email" value="admin@example.com" required>
                        </div>

                        <div class="form-group">
                            <label>Admin Password</label>
                            <input type="password" name="admin_password" value="Admin@123456" required>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" name="setup_exchange_rates" checked>
                            <label>Set default exchange rates</label>
                        </div>

                        <div class="checkbox-group">
                            <input type="checkbox" name="setup_branch_pools" checked>
                            <label>Initialize currency stock</label>
                        </div>

                        <button type="submit" class="btn btn-primary">
                            Start Setup
                        </button>
                        <button type="button" class="btn btn-secondary" onclick="hideQuickSetup()">
                            Cancel
                        </button>
                    </form>
                </div>
            @endif
        </div>
    </div>

    <script>
        function showQuickSetup() {
            document.getElementById('quickSetupForm').style.display = 'block';
        }

        function hideQuickSetup() {
            document.getElementById('quickSetupForm').style.display = 'none';
        }

        document.getElementById('setupForm')?.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);
            data.setup_exchange_rates = formData.has('setup_exchange_rates');
            data.setup_branch_pools = formData.has('setup_branch_pools');

            const btn = e.target.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.textContent = 'Setting up...';

            try {
                const response = await fetch('/setup/quick', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    alert(result.message + '\n\nRedirecting to login...');
                    window.location.href = result.redirect;
                } else {
                    alert('Error: ' + result.message);
                    btn.disabled = false;
                    btn.textContent = 'Start Setup';
                }
            } catch (error) {
                alert('Error: ' + error.message);
                btn.disabled = false;
                btn.textContent = 'Start Setup';
            }
        });
    </script>
</body>
</html>
