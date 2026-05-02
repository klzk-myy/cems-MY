@extends('layouts.base')

@section('title', 'Branch Opening - Step 1')

@section('content')
<div>
    <div class="bg-white rounded-lg shadow-lg p-6">
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Step 1: Branch Details</h1>
            <div class="text-sm text-gray-600">Step 1 of 3</div>
        </div>

        @if ($error)
            <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                <p class="text-sm text-red-600">{{ $error }}</p>
            </div>
        @endif

        <form wire:submit="processStep1">
            <div class="space-y-4">
                <div>
                    <label for="code" class="block text-sm font-medium text-gray-700">Branch Code *</label>
                    <input type="text" wire:model="code" id="code"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="e.g., HQ, BR001" required>
                </div>

                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Branch Name *</label>
                    <input type="text" wire:model="name" id="name"
                           class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                           placeholder="e.g., Head Office, Kuala Lumpur Branch" required>
                </div>

                <div>
                    <label for="type" class="block text-sm font-medium text-gray-700">Branch Type *</label>
                    <select wire:model="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <option value="">Select Type</option>
                        @foreach($branchTypes as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="flex items-center space-x-2">
                        <input type="checkbox" wire:model="is_main" value="1"
                               class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">Set as Main Branch (Head Office)</span>
                    </label>
                    <p class="mt-1 text-xs text-gray-500">Only one branch can be marked as main</p>
                </div>

                <div>
                    <label for="parent_id" class="block text-sm font-medium text-gray-700">Parent Branch</label>
                    <select wire:model="parent_id" id="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">No Parent (Top Level)</option>
                        @foreach($parentBranches as $parent)
                            <option value="{{ $parent['id'] }}">{{ $parent['code'] }} - {{ $parent['name'] }}</option>
                        @endforeach
                    </select>
                </div>

                <hr class="my-6">

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                        <input type="text" wire:model="city" id="city"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                        <input type="text" wire:model="state" id="state"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div>
                    <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                    <textarea wire:model="address" id="address" rows="2"
                              class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code</label>
                        <input type="text" wire:model="postal_code" id="postal_code"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                        <input type="text" wire:model="country" id="country" value="Malaysia"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                        <input type="text" wire:model="phone" id="phone"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" wire:model="email" id="email"
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                    </div>
                </div>
            </div>

            <div class="mt-8 flex items-center justify-between">
                <a href="{{ route('branches.open.index') }}" class="text-gray-600 hover:text-gray-800">
                    Cancel
                </a>
                <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                    Continue to Step 2
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
