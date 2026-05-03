<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Business Setup</title>
</head>
<body class="min-h-screen bg-gray-50">
    <div class="flex min-h-screen flex-col">
        <header class="bg-white shadow-sm">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <h1 class="text-2xl font-semibold text-gray-900">Business Setup</h1>
            </div>
        </header>

        <main class="flex-1">
            <div class="mx-auto max-w-3xl px-4 py-6 sm:px-6 lg:px-8">
                @if($isSetupComplete)
                    <div class="bg-white border border-[#e5e5e5] rounded-xl p-8 text-center">
                        <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-green-100 mb-4">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <h2 class="text-xl font-semibold text-gray-900 mb-2">Setup Complete</h2>
                        <p class="text-gray-600 mb-6">Your business has been configured and is ready to use.</p>
                        <div class="space-y-2 text-sm text-gray-500">
                            <p>Admin User: Configured</p>
                            <p>Currencies: {{ $currencies->count() ?? 'Active' }}</p>
                            <p>Exchange Rates: Set</p>
                            <p>Branches: Initialized</p>
                        </div>
                        <a href="{{ route('login') }}" class="inline-flex mt-6 px-6 py-2.5 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                            Go to Login
                        </a>
                    </div>
                @else
                    <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
                        <div class="mb-6">
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-900">Step {{ $currentStep }} of 6</span>
                                <span class="text-sm text-gray-500">{{ $progress }}% Complete</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-[#0a0a0a] h-2 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>

                        <div class="step-indicator mb-8 flex justify-between">
                            @for($i = 1; $i <= 6; $i++)
                                <div class="flex flex-col items-center">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $i <= $currentStep ? 'bg-[#0a0a0a] text-white' : 'bg-gray-200 text-gray-500' }}">
                                        {{ $i }}
                                    </div>
                                    <span class="text-xs text-gray-500 mt-1">
                                        @switch($i)
                                            @case(1) Company @case(2) Admin @case(3) Currency @case(4) Rates @case(5) Stock @case(6) Balance @endswitch
                                    </span>
                                </div>
                            @endfor
                        </div>

                        @if(session('error'))
                            <div class="mb-6 rounded-lg bg-red-50 p-4 text-sm text-red-700">
                                {{ session('error') }}
                            </div>
                        @endif

                        @switch($currentStep)
                            @case(1)
                            <form method="POST" action="{{ route('setup.step1') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Company Information</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Business Name</label>
                                        <input type="text" name="business_name" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Address</label>
                                        <textarea name="business_address" rows="2" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg"></textarea>
                                    </div>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Phone</label>
                                            <input type="text" name="business_phone" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700">Email</label>
                                            <input type="email" name="business_email" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg">
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-end">
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Next: Admin User
                                    </button>
                                </div>
                            </form>
                            @break

                            @case(2)
                            <form method="POST" action="{{ route('setup.step2') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Admin User</h3>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Admin Name</label>
                                        <input type="text" name="admin_name" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Email</label>
                                        <input type="email" name="admin_email" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Password</label>
                                        <input type="password" name="admin_password" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" required minlength="12">
                                        <p class="mt-1 text-xs text-gray-500">Minimum 12 characters with uppercase, lowercase, number and special character.</p>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between">
                                    <a href="{{ route('setup.index', ['step' => 1]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Next: Currencies
                                    </button>
                                </div>
                            </form>
                            @break

                            @case(3)
                            <form method="POST" action="{{ route('setup.step3') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Currencies</h3>
                                <p class="text-sm text-gray-600 mb-4">Select the currencies your business will trade in.</p>
                                <div class="space-y-3">
                                    @foreach($currencies as $currency)
                                        <label class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg cursor-pointer">
                                            <input type="checkbox" name="currency_codes[]" value="{{ $currency->code }}" class="w-4 h-4 rounded border-gray-300" checked>
                                            <span class="text-sm font-medium text-gray-900">{{ $currency->code }}</span>
                                            <span class="text-sm text-gray-500">{{ $currency->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="mt-6 flex justify-between">
                                    <a href="{{ route('setup.index', ['step' => 2]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Next: Exchange Rates
                                    </button>
                                </div>
                            </form>
                            @break

                            @case(4)
                            <form method="POST" action="{{ route('setup.step4') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Exchange Rates</h3>
                                <p class="text-sm text-gray-600 mb-4">Configure how rates are fetched and managed.</p>
                                <div class="space-y-4">
                                    <label class="flex items-center gap-3 p-4 bg-gray-50 rounded-lg cursor-pointer">
                                        <input type="checkbox" name="use_default_rates" value="1" class="w-4 h-4 rounded border-gray-300" checked>
                                        <div>
                                            <span class="text-sm font-medium text-gray-900">Use Default Rates</span>
                                            <p class="text-xs text-gray-500">Seed with standard exchange rates for selected currencies.</p>
                                        </div>
                                    </label>
                                </div>
                                <div class="mt-6 flex justify-between">
                                    <a href="{{ route('setup.index', ['step' => 3]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Next: Initial Stock
                                    </button>
                                </div>
                            </form>
                            @break

                            @case(5)
                            <form method="POST" action="{{ route('setup.step5') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Initial Stock</h3>
                                <p class="text-sm text-gray-600 mb-4">Set your initial currency holdings (opening floats).</p>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">MYR Cash (RM)</label>
                                        <input type="text" name="initial_myr_cash" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="0.00" value="0.00" inputmode="decimal">
                                    </div>
                                    <div class="border-t pt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Foreign Currency Stock</label>
                                        <div class="grid grid-cols-2 gap-4">
                                            @foreach($currencies->where('code', '!=', 'MYR') as $currency)
                                                <div>
                                                    <label class="block text-xs text-gray-500">{{ $currency->code }}</label>
                                                    <input type="text" name="initial_stock[{{ $currency->code }}]" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="0.00" value="0.00" inputmode="decimal">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between">
                                    <a href="{{ route('setup.index', ['step' => 4]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Next: Opening Balance
                                    </button>
                                </div>
                            </form>
                            @break

                            @case(6)
                            <form method="POST" action="{{ route('setup.step6') }}">
                                @csrf
                                <h3 class="text-lg font-medium text-gray-900 mb-4">Opening Balance</h3>
                                <p class="text-sm text-gray-600 mb-4">Set your opening balance for accounting.</p>
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">Opening Balance (MYR)</label>
                                        <input type="text" name="opening_balance_myr" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="0.00" value="0.00" inputmode="decimal">
                                    </div>
                                    <div class="border-t pt-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Foreign Currency Opening Balances</label>
                                        <div class="grid grid-cols-2 gap-4">
                                            @foreach($currencies->where('code', '!=', 'MYR') as $currency)
                                                <div>
                                                    <label class="block text-xs text-gray-500">{{ $currency->code }}</label>
                                                    <input type="text" name="opening_balance_foreign[{{ $currency->code }}]" class="mt-1 w-full px-4 py-2.5 text-sm bg-white border border-[#e5e5e5] rounded-lg" placeholder="0.00" value="0.00" inputmode="decimal">
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                                <div class="mt-6 flex justify-between">
                                    <a href="{{ route('setup.index', ['step' => 5]) }}" class="px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[#e5e5e5] text-gray-700 hover:bg-gray-50">
                                        Previous
                                    </a>
                                    <button type="submit" class="px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
                                        Complete Setup
                                    </button>
                                </div>
                            </form>
                            @break
                        @endswitch
                    </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>