@extends('layouts.base')

@section('title', 'Performance Monitoring - CEMS-MY')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Performance Monitoring</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">System performance and cache metrics</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-[--color-ink-muted]">Cache Hit Rate</h3>
                <span class="text-xl">📊</span>
            </div>
            <p class="text-2xl font-semibold text-[--color-ink]">--</p>
            <p class="text-xs text-[--color-ink-muted] mt-1">Percentage</p>
        </div>

        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-[--color-ink-muted]">Query Count</h3>
                <span class="text-xl">📈</span>
            </div>
            <p class="text-2xl font-semibold text-[--color-ink]">--</p>
            <p class="text-xs text-[--color-ink-muted] mt-1">Last request</p>
        </div>

        <div class="bg-white border border-[--color-border] rounded-xl p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-sm font-medium text-[--color-ink-muted]">Memory Usage</h3>
                <span class="text-xl">💾</span>
            </div>
            <p class="text-2xl font-semibold text-[--color-ink]">{{ round(memory_get_usage() / 1024 / 1024, 2) }} MB</p>
            <p class="text-xs text-[--color-ink-muted] mt-1">Current</p>
        </div>
    </div>

    <div class="bg-white border border-[--color-border] rounded-xl">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h2 class="text-lg font-semibold text-[--color-ink]">System Status</h2>
        </div>
        <div class="p-6">
            <table class="w-full text-sm">
                <tbody>
                    <tr class="border-t border-[--color-border]">
                        <td class="py-3 text-[--color-ink-muted]">PHP Version</td>
                        <td class="py-3 text-right">{{ PHP_VERSION }}</td>
                    </tr>
                    <tr class="border-t border-[--color-border]">
                        <td class="py-3 text-[--color-ink-muted]">Laravel Version</td>
                        <td class="py-3 text-right">{{ Illuminate\Support\Facades\App::version() }}</td>
                    </tr>
                    <tr class="border-t border-[--color-border]">
                        <td class="py-3 text-[--color-ink-muted]">Environment</td>
                        <td class="py-3 text-right">{{ app()->environment() }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection