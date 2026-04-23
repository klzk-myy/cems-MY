@extends('layouts.app')

@section('title', 'Branch Opening Wizard')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h1 class="text-2xl font-bold mb-6">Branch Opening Wizard</h1>
            
            <p class="text-gray-600 mb-6">
                This wizard will guide you through the process of opening a new branch, including:
            </p>

            <div class="space-y-4 mb-8">
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">1</div>
                    <div class="ml-4">
                        <h3 class="font-semibold">Branch Details</h3>
                        <p class="text-sm text-gray-600">Enter branch information, type, and location</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">2</div>
                    <div class="ml-4">
                        <h3 class="font-semibold">Currency Pools</h3>
                        <p class="text-sm text-gray-600">Set initial balances for each currency</p>
                    </div>
                </div>
                <div class="flex items-start">
                    <div class="flex-shrink-0 w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold">3</div>
                    <div class="ml-4">
                        <h3 class="font-semibold">Opening Balance</h3>
                        <p class="text-sm text-gray-600">Create initial journal entry for capital</p>
                    </div>
                </div>
            </div>

            <div class="border-t pt-6">
                <a href="{{ route('branch-openings.step1') }}" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                    Start Branch Opening
                </a>
                <a href="{{ route('branches.index') }}" class="inline-flex items-center ml-4 px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg transition">
                    Back to Branches
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
