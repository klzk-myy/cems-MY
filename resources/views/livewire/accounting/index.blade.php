<div class="min-h-screen bg-[var(--color-background)] p-6">
    <div class="max-w-7xl mx-auto">
        <h1 class="text-2xl font-bold text-[var(--color-ink)] mb-6">Accounting Overview</h1>

        <div class="grid grid-cols-4 gap-6 mb-6">
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Total Assets</p>
                <p class="text-2xl font-bold mt-1">${{ number_format($totalAssets, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Total Liabilities</p>
                <p class="text-2xl font-bold mt-1">${{ number_format($totalLiabilities, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Revenue (YTD)</p>
                <p class="text-2xl font-bold mt-1 text-green-600">${{ number_format($revenueYTD, 2) }}</p>
            </div>
            <div class="bg-white rounded-lg shadow p-6">
                <p class="text-sm text-gray-500">Expenses (YTD)</p>
                <p class="text-2xl font-bold mt-1 text-red-600">${{ number_format($expensesYTD, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6">
            <a href="{{ route('accounting.journal.index') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">Journal Entries</h3>
                <p class="text-sm text-gray-500 mt-1">{{ $journalCount }} entries</p>
            </a>

            <a href="{{ route('accounting.ledger') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">General Ledger</h3>
                <p class="text-sm text-gray-500 mt-1">View accounts</p>
            </a>

            <a href="{{ route('accounting.trial-balance') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">Trial Balance</h3>
                <p class="text-sm text-gray-500 mt-1">As of {{ date('Y-m-d') }}</p>
            </a>

            <a href="{{ route('accounting.balance-sheet') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">Balance Sheet</h3>
                <p class="text-sm text-gray-500 mt-1">Statement</p>
            </a>

            <a href="{{ route('accounting.profit-loss') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">Profit & Loss</h3>
                <p class="text-sm text-gray-500 mt-1">Income statement</p>
            </a>

            <a href="{{ route('accounting.cash-flow') }}" class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition">
                <h3 class="font-medium text-[var(--color-ink)]">Cash Flow</h3>
                <p class="text-sm text-gray-500 mt-1">Statement</p>
            </a>
        </div>
    </div>
</div>