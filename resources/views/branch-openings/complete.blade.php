@extends('layouts.app')

@section('title', 'Branch Opening Complete')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="text-center mb-6">
                <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 rounded-full mb-4">
                    <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">Branch Opening Complete!</h1>
                <p class="text-gray-600 mt-2">
                    {{ $branch->name }} ({{ $branch->code }}) has been successfully opened
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <h3 class="font-semibold mb-3">Summary</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-gray-600">Branch Code:</span>
                        <span class="font-medium">{{ $branch->code }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Branch Name:</span>
                        <span class="font-medium">{{ $branch->name }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Branch Type:</span>
                        <span class="font-medium">{{ ucwords(str_replace('_', ' ', $branch->type)) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span class="text-gray-600">Currency Pools:</span>
                        <span class="font-medium">{{ $stats['pool_count'] }} currencies</span>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <a href="{{ route('branches.show', $branch->id) }}" 
                   class="block w-full text-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                    View Branch Details
                </a>
                <a href="{{ route('branches.index') }}" 
                   class="block w-full text-center px-6 py-3 bg-gray-200 hover:bg-gray-300 text-gray-800 font-semibold rounded-lg transition">
                    Back to Branches
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
