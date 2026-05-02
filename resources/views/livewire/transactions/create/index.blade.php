@extends('layouts.base')

@section('title', 'New Transaction')

@section('header-title')
<div class="flex items-center gap-3">
    <a href="{{ route('transactions.index') }}" class="btn btn-ghost btn-icon">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
    </a>
    <div>
        <h1 class="text-xl font-semibold text-gray-900">New Transaction</h1>
        <p class="text-sm text-gray-500">Create a new currency exchange transaction</p>
    </div>
</div>
@endsection

@section('content')
<div class="max-w-4xl mx-auto">
    {{-- Step Indicator --}}
    <div class="mb-8">
        <div class="flex items-center justify-center">
            <div class="flex items-center gap-4">
                {{-- Step 1 --}}
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                        {{ $currentStep >= 1 ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500' }}">
                        @if($currentStep > 1)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            1
                        @endif
                    </div>
                    <span class="text-sm font-medium {{ $currentStep >= 1 ? 'text-gray-900' : 'text-gray-500' }}">Customer</span>
                </div>

                {{-- Connector --}}
                <div class="w-16 h-0.5 {{ $currentStep > 1 ? 'bg-amber-500' : 'bg-gray-200' }}"></div>

                {{-- Step 2 --}}
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                        {{ $currentStep >= 2 ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500' }}">
                        @if($currentStep > 2)
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        @else
                            2
                        @endif
                    </div>
                    <span class="text-sm font-medium {{ $currentStep >= 2 ? 'text-gray-900' : 'text-gray-500' }}">Currency & Amount</span>
                </div>

                {{-- Connector --}}
                <div class="w-16 h-0.5 {{ $currentStep > 2 ? 'bg-amber-500' : 'bg-gray-200' }}"></div>

                {{-- Step 3 --}}
                <div class="flex items-center gap-2">
                    <div class="w-10 h-10 rounded-full flex items-center justify-center font-semibold transition-colors
                        {{ $currentStep >= 3 ? 'bg-amber-500 text-white' : 'bg-gray-100 text-gray-500' }}">
                        3
                    </div>
                    <span class="text-sm font-medium {{ $currentStep >= 3 ? 'text-gray-900' : 'text-gray-500' }}">Review</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Error Messages --}}
    @if(session('error'))
        <div class="alert alert-danger mb-6">
            <span>{{ session('error') }}</span>
        </div>
    @endif

    {{-- Step Content --}}
    <div class="card">
        <div class="card-body">
            @switch($currentStep)
                @case(1)
                    @include('livewire.transactions.create.step1')
                @case(2)
                    @include('livewire.transactions.create.step2')
                @case(3)
                    @include('livewire.transactions.create.step3')
            @endswitch
        </div>
    </div>

    {{-- Navigation --}}
    <div class="flex items-center justify-between mt-6">
        <div>
            @if($currentStep > 1)
                <button type="button" wire:click="previousStep" class="btn btn-secondary">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    Back
                </button>
            @endif
        </div>
        <div class="flex items-center gap-3">
            @if($currentStep < 3)
                <a href="{{ route('transactions.index') }}" class="btn btn-ghost">Cancel</a>
                <button type="button" wire:click="nextStep" class="btn btn-primary">
                    Next
                    <svg class="w-4 h-4 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            @endif
        </div>
    </div>
</div>
@endsection
