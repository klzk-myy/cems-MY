<x-app-layout title="Financial Ratios">
    <div class="space-y-6">
        <x-page-header
            title="Financial Ratios"
            description="Key financial performance indicators"
        />

        <x-stat-grid cols="3">
            <x-stat-card label="Profit Margin" value="-" />
            <x-stat-card label="Current Ratio" value="-" />
            <x-stat-card label="Debt to Equity" value="-" />
            <x-stat-card label="Return on Equity" value="-" />
            <x-stat-card label="Asset Turnover" value="-" />
            <x-stat-card label="Expense Ratio" value="-" />
        </x-stat-grid>

        <x-alert type="warning">
            <strong>Note:</strong> Financial ratio computation is available via <code>FinancialRatioService</code>.
            A period selector and automated calculation will be added in a future update.
        </x-alert>
    </div>
</x-app-layout>
