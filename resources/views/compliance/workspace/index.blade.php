@extends('layouts.app')

@section('title', 'Compliance Workspace')

@section('content')
<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold mb-8">Compliance Workspace</h1>

    {{-- Alert Summary --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Alert Queue</h2>
            <div class="text-3xl font-bold text-blue-600">{{ $alertSummary['pending'] ?? 0 }}</div>
            <p class="text-gray-600">Pending Alerts</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">Cases</h2>
            <div class="text-3xl font-bold text-orange-600">{{ $caseSummary['open'] ?? 0 }}</div>
            <p class="text-gray-600">Open Cases</p>
        </div>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-semibold mb-4">High Risk Customers</h2>
            <div class="text-3xl font-bold text-red-600">{{ $riskSummary['highRisk'] ?? 0 }}</div>
            <p class="text-gray-600">Requires Review</p>
        </div>
    </div>

    {{-- KPI Metrics --}}
    <div class="bg-white rounded-lg shadow p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Key Performance Indicators</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            @foreach($kpis as $kpi)
                <div class="text-center">
                    <div class="text-2xl font-bold">{{ $kpi['value'] }}</div>
                    <div class="text-sm text-gray-600">{{ $kpi['label'] }}</div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Deadlines Calendar --}}
    <div class="bg-white rounded-lg shadow p-6">
        <h2 class="text-xl font-semibold mb-4">Upcoming Deadlines</h2>
        <table class="min-w-full">
            <thead>
                <tr class="border-b">
                    <th class="text-left py-2">Report</th>
                    <th class="text-left py-2">Due Date</th>
                    <th class="text-left py-2">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($deadlines as $deadline)
                    <tr class="border-b">
                        <td class="py-3">{{ $deadline['name'] }}</td>
                        <td class="py-3">{{ $deadline['due_date']->format('M d, Y') }}</td>
                        <td class="py-3">
                            <span class="px-2 py-1 rounded text-sm {{ $deadline['status'] === 'overdue' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                {{ ucfirst($deadline['status']) }}
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection