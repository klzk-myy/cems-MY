<x-app-layout title="Risk Trends">
    <x-page-header title="Risk Trends" description="Risk score and alert volume trends" />

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-chart-trend title="High Risk Customer Trend" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']" :values="[65, 72, 68, 75, 80, 78]" color="danger" />
        <x-chart-trend title="Alert Volume Trend" :labels="['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']" :values="[12, 15, 8, 20, 18, 14]" color="warning" />
    </div>
</x-app-layout>
