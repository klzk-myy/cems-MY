@extends('layouts.base')

@section('title', 'Transactions - CEMS-MY')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Transactions</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Manage foreign currency transactions</p>
        </div>
        <a href="{{ route('transactions.create') }}" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">New Transaction</a>
    </div>
    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Reference</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Date</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Customer</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Type</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">MYR</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                    <td class="px-4 py-3 font-mono text-xs text-[#171717]">TXN-20260426-001</td>
                    <td class="px-4 py-3 text-[#6b6b6b]">26 Apr 2026</td>
                    <td class="px-4 py-3 text-[#171717]">Ahmad Razali</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-blue-100 text-blue-700">BUY</span></td>
                    <td class="px-4 py-3 text-[#171717] font-semibold">RM 5,000</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Completed</span></td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
