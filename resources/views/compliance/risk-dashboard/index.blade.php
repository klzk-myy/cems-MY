@extends('layouts.base')

@section('title', 'Risk Dashboard - CEMS-MY')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-semibold text-[--color-ink]">Risk Dashboard</h1>
    <p class="text-sm text-[--color-ink-muted] mt-1">Customer risk overview and trends</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
    <div class="card p-6">
        <div class="text-2xl font-bold text-red-600">12</div>
        <div class="text-sm text-[--color-ink-muted]">High Risk Customers</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-yellow-600">45</div>
        <div class="text-sm text-[--color-ink-muted]">Medium Risk</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-green-600">234</div>
        <div class="text-sm text-[--color-ink-muted]">Low Risk</div>
    </div>
    <div class="card p-6">
        <div class="text-2xl font-bold text-[--color-ink]">3</div>
        <div class="text-sm text-[--color-ink-muted]">PEP Customers</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Recent Risk Changes</h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-[--color-ink-muted] text-center">Risk change history will appear here</p>
        </div>
    </div>
    <div class="card">
        <div class="px-6 py-4 border-b border-[--color-border]">
            <h3 class="text-base font-semibold text-[--color-ink]">Risk Distribution</h3>
        </div>
        <div class="p-6">
            <p class="text-sm text-[--color-ink-muted] text-center">Risk distribution chart will appear here</p>
        </div>
    </div>
</div>
@endsection