@extends('layouts.base')

@section('title', 'Counters - CEMS-MY')

@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#171717]">Counters</h1>
            <p class="text-sm text-[#6b6b6b] mt-1">Manage teller counters and till sessions</p>
        </div>
        <a href="#" class="px-4 py-2 text-sm font-medium text-white bg-[#0a0a0a] rounded-lg hover:bg-[#262626]">Open Counter</a>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#171717]">Counter 1</h3>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">Teller</span>
                    <span class="text-[#171717]">Ahmad Razali</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">MYR Balance</span>
                    <span class="text-[#171717] font-semibold">RM 50,000</span>
                </div>
            </div>
        </div>
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#171717]">Counter 2</h3>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">Teller</span>
                    <span class="text-[#171717]">Siti Nurhaliza</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">MYR Balance</span>
                    <span class="text-[#171717] font-semibold">RM 45,000</span>
                </div>
            </div>
        </div>
        <div class="bg-white border border-[#e5e5e5] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-semibold text-[#171717]">Counter 3</h3>
                <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-[#f7f7f8] text-[#6b6b6b]">Closed</span>
            </div>
            <div class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">Teller</span>
                    <span class="text-[#171717]">-</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-[#6b6b6b]">MYR Balance</span>
                    <span class="text-[#171717] font-semibold">-</span>
                </div>
            </div>
        </div>
    </div>
    <div class="bg-white border border-[#e5e5e5] rounded-xl overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-[#f7f7f8] border-b border-[#e5e5e5]">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Counter</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Current Teller</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">MYR Float</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-[#6b6b6b]">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                    <td class="px-4 py-3 text-[#171717] font-medium">Counter 1</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span></td>
                    <td class="px-4 py-3 text-[#171717]">Ahmad Razali</td>
                    <td class="px-4 py-3 text-[#171717] font-semibold">RM 50,000</td>
                    <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">View</a></td>
                </tr>
                <tr class="border-b border-[#e5e5e5] hover:bg-[#f7f7f8]/50">
                    <td class="px-4 py-3 text-[#171717] font-medium">Counter 2</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-green-100 text-green-700">Open</span></td>
                    <td class="px-4 py-3 text-[#171717]">Siti Nurhaliza</td>
                    <td class="px-4 py-3 text-[#171717] font-semibold">RM 45,000</td>
                    <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">View</a></td>
                </tr>
                <tr class="hover:bg-[#f7f7f8]/50">
                    <td class="px-4 py-3 text-[#171717] font-medium">Counter 3</td>
                    <td class="px-4 py-3"><span class="inline-flex px-2 py-0.5 text-xs font-medium rounded bg-[#f7f7f8] text-[#6b6b6b]">Closed</span></td>
                    <td class="px-4 py-3 text-[#6b6b6b]">-</td>
                    <td class="px-4 py-3 text-[#6b6b6b]">-</td>
                    <td class="px-4 py-3"><a href="#" class="text-[#d4a843] hover:underline">Open</a></td>
                </tr>
            </tbody>
        </table>
    </div>
@endsection
