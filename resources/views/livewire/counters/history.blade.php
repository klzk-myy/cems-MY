@extends('layouts.base')

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Counter History - {{ $counter->name }}</h2>
                <a href="{{ route('counters.index') }}" class="text-gray-600 hover:text-gray-800 text-sm">
                    Back to Counters
                </a>
            </div>
        </div>

        <div class="p-6">
            <form method="GET" class="mb-6">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div>
                        <label for="from_date" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                        <input type="date" wire:model="fromDate" name="from_date" id="from_date"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="to_date" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                        <input type="date" wire:model="toDate" name="to_date" id="to_date"
                               class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                    <div>
                        <label for="user_id" class="block text-sm font-medium text-gray-700 mb-1">User</label>
                        <select wire:model="userId" name="user_id" id="user_id"
                                class="block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All Users</option>
                            @foreach($users as $user)
                                <option value="{{ $user['id'] }}">{{ $user['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Filter
                        </button>
                        <button type="button" wire:click="clearFilters" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300">
                            Clear
                        </button>
                    </div>
                </div>
            </form>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Operator</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opening Float</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Opened By</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Closed By</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse($sessions as $session)
                            <tr>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $session->session_date }}</td>
                                <td class="px-4 py-3 text-sm">
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full {{ $session->status->value === 'open' ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $session->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $session->user->username ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ number_format($session->opening_float ?? 0, 2) }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $session->openedByUser->username ?? 'N/A' }}</td>
                                <td class="px-4 py-3 text-sm text-gray-900">{{ $session->closedByUser->username ?? 'N/A' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-6 text-center text-gray-500">
                                    No counter sessions found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-4">
                {{ $sessions->links() }}
            </div>
        </div>
    </div>
</div>
