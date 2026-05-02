@extends('layouts.base')

@section('title', 'Application Setup - CEMS-MY')

@section('content')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Application Setup</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Configure your currency exchange business</p>
    </div>

    <div class="bg-white border border-[--color-border] rounded-xl p-8 text-center">
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <span class="text-2xl">⚙️</span>
        </div>
        <h2 class="text-lg font-semibold text-[--color-ink] mb-2">Setup Wizard</h2>
        <p class="text-sm text-[--color-ink-muted] mb-6">This page should use the setup wizard Livewire component.</p>
        <a href="/setup/wizard" class="inline-flex px-4 py-2 text-sm font-medium rounded-lg bg-[--color-primary] text-white hover:bg-[--color-ink]">
            Go to Setup Wizard
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <a href="/setup/step1" class="bg-white border border-[--color-border] rounded-xl p-6 hover:border-[--color-primary] transition-colors">
            <div class="text-2xl mb-2">🏢</div>
            <h3 class="font-semibold text-[--color-ink]">Company Info</h3>
            <p class="text-sm text-[--color-ink-muted]">Step 1 of 6</p>
        </a>
        <a href="/setup/step2" class="bg-white border border-[--color-border] rounded-xl p-6 hover:border-[--color-primary] transition-colors">
            <div class="text-2xl mb-2">👤</div>
            <h3 class="font-semibold text-[--color-ink]">Admin User</h3>
            <p class="text-sm text-[--color-ink-muted]">Step 2 of 6</p>
        </a>
        <a href="/setup/step3" class="bg-white border border-[--color-border] rounded-xl p-6 hover:border-[--color-primary] transition-colors">
            <div class="text-2xl mb-2">💱</div>
            <h3 class="font-semibold text-[--color-ink]">Currencies</h3>
            <p class="text-sm text-[--color-ink-muted]">Step 3 of 6</p>
        </a>
    </div>
</div>
@endsection