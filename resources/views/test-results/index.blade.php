@extends('layouts.base')

@section('title', 'Test Results')

<div class="p-6">
    <div class="mb-6">
        <a href="{{ url()->previous() }}" class="text-sm text-[--color-accent] hover:underline">&larr; Back</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <h1 class="text-2xl font-semibold">Test Results</h1>
        <button class="px-4 py-2 bg-[--color-accent] text-white rounded-lg hover:opacity-90">Run New Test</button>
    </div>

    <div class="bg-[--color-bg-secondary] rounded-xl border border-[--color-border] overflow-hidden">
        <table class="w-full">
            <thead class="bg-[--color-bg-tertiary]">
                <tr>
                    <th class="px-4 py-3 text-left text-sm font-medium">ID</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Test Name</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Status</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Duration</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Date</th>
                    <th class="px-4 py-3 text-left text-sm font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[--color-border]">
                @forelse($testResults ?? [] as $result)
                <tr class="hover:bg-[--color-bg-tertiary]/50">
                    <td class="px-4 py-3 text-sm">{{ $result->id }}</td>
                    <td class="px-4 py-3 text-sm">{{ $result->name }}</td>
                    <td class="px-4 py-3">
                        <span class="px-2 py-1 text-xs rounded-full {{ $result->passed ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                            {{ $result->passed ? 'Passed' : 'Failed' }}
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm">{{ $result->duration ?? 'N/A' }}ms</td>
                    <td class="px-4 py-3 text-sm">{{ $result->created_at ?? now()->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3">
                        <a href="{{ route('test-results.show', $result->id) }}" class="text-sm text-[--color-accent] hover:underline">View</a>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-[--color-text-muted]">No test results found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>