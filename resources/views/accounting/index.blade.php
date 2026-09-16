<x-app-layout title="Accounting">
    <div class="space-y-6">
        <x-page-header title="Accounting" />

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <x-card title="Entries">
                <ul class="space-y-2 mt-4">
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.journal') }}">Journal Entries</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.expenses.index') }}">Petty Cash Expenses</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.budget') }}">Budget Management</x-button></li>
                </ul>
            </x-card>

            <x-card title="Financial Reports">
                <ul class="space-y-2 mt-4">
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.chart-of-accounts.index') }}">Chart of Accounts</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.ledger') }}">General Ledger</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.trial-balance') }}">Trial Balance</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.profit-loss') }}">Profit &amp; Loss</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.balance-sheet') }}">Balance Sheet</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.cash-flow') }}">Cash Flow</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.ratios') }}">Financial Ratios</x-button></li>
                </ul>
            </x-card>

            <x-card title="Periods & Operations">
                <ul class="space-y-2 mt-4">
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.periods') }}">Accounting Periods</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.fiscal-years') }}">Fiscal Years</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.revaluation') }}">Currency Revaluation</x-button></li>
                    <li><x-button variant="ghost" size="sm" href="{{ route('accounting.reconciliation') }}">Bank Reconciliation</x-button></li>
                </ul>
            </x-card>
        </div>
    </div>
</x-app-layout>
