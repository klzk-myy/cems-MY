@extends('layouts.base')

@section('title', 'STR Reports')

@section('header-title')
    <h1 class="text-xl font-semibold text-[--color-ink]">Suspicious Transaction Reports</h1>
@endsection

@section('header-actions')
    <a href="{{ route('str.create') }}" class="btn btn-primary">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        New STR
    </a>
@endsection

@section('content')
<div class="mb-6">
    <a href="/str" class="inline-flex items-center gap-2 text-sm text-[--color-ink-muted] hover:text-[--color-ink] transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Back to STRs
    </a>
</div>

<div class="bg-white rounded-xl border border-[--color-border] overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full">
            <thead class="bg-[--color-canvas] border-b border-[--color-border]">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">STR ID</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">Date</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">Customer</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">Amount</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-[--color-ink-muted] uppercase tracking-wider">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[--color-border]">
                <tr class="hover:bg-[--color-canvas] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[--color-ink]">STR-2026-001</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink-muted]">2026-05-03</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink]">Ahmad bin Hassan</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink]">MYR 85,000</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Pending Review</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="/str/1" class="text-[--color-accent] hover:text-[--color-accent-dark] transition-colors">View</a>
                    </td>
                </tr>
                <tr class="hover:bg-[--color-canvas] transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-[--color-ink]">STR-2026-002</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink-muted]">2026-05-01</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink]">Siti Nurhaliza</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-[--color-ink]">MYR 120,000</td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Submitted</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <a href="/str/2" class="text-[--color-accent] hover:text-[--color-accent-dark] transition-colors">View</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>
@endsection