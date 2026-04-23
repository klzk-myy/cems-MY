@extends('layouts.app')

@section('title', 'Step 1: Branch Details')

@section('content')
<div class="container mx-auto px-4 py-8">
    <div class="max-w-2xl mx-auto">
        <div class="bg-white rounded-lg shadow-lg p-6">
            <div class="flex items-center justify-between mb-6">
                <h1 class="text-2xl font-bold">Step 1: Branch Details</h1>
                <div class="text-sm text-gray-600">Step 1 of 3</div>
            </div>

            <form action="{{ route('branch-openings.step1.process') }}" method="POST">
                @csrf

                <div class="space-y-4">
                    <div>
                        <label for="code" class="block text-sm font-medium text-gray-700">Branch Code *</label>
                        <input type="text" name="code" id="code" value="{{ old('code') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="e.g., HQ, BR001" required>
                        @error('code')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="name" class="block text-sm font-medium text-gray-700">Branch Name *</label>
                        <input type="text" name="name" id="name" value="{{ old('name') }}" 
                               class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                               placeholder="e.g., Head Office, Kuala Lumpur Branch" required>
                        @error('name')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="type" class="block text-sm font-medium text-gray-700">Branch Type *</label>
                        <select name="type" id="type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                            <option value="">Select Type</option>
                            @foreach(['head_office' => 'Head Office', 'branch' => 'Branch', 'sub_branch' => 'Sub-Branch'] as $value => $label)
                                <option value="{{ $value }}" {{ old('type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="flex items-center space-x-2">
                            <input type="checkbox" name="is_main" value="1" {{ old('is_main') ? 'checked' : '' }} 
                                   class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Set as Main Branch (Head Office)</span>
                        </label>
                        <p class="mt-1 text-xs text-gray-500">Only one branch can be marked as main</p>
                    </div>

                    <div>
                        <label for="parent_id" class="block text-sm font-medium text-gray-700">Parent Branch</label>
                        <select name="parent_id" id="parent_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">No Parent (Top Level)</option>
                            @foreach($parentBranches as $parent)
                                <option value="{{ $parent->id }}" {{ old('parent_id') == $parent->id ? 'selected' : '' }}>
                                    {{ $parent->code }} - {{ $parent->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('parent_id')
                            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <hr class="my-6">

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700">City</label>
                            <input type="text" name="city" id="city" value="{{ old('city') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="state" class="block text-sm font-medium text-gray-700">State</label>
                            <input type="text" name="state" id="state" value="{{ old('state') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-gray-700">Address</label>
                        <textarea name="address" id="address" rows="2" value="{{ old('address') }}" 
                                  class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="postal_code" class="block text-sm font-medium text-gray-700">Postal Code</label>
                            <input type="text" name="postal_code" id="postal_code" value="{{ old('postal_code') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="country" class="block text-sm font-medium text-gray-700">Country</label>
                            <input type="text" name="country" id="country" value="{{ old('country', 'Malaysia') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label for="phone" class="block text-sm font-medium text-gray-700">Phone</label>
                            <input type="text" name="phone" id="phone" value="{{ old('phone') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>

                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" name="email" id="email" value="{{ old('email') }}" 
                                   class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        </div>
                    </div>
                </div>

                <div class="mt-8 flex items-center justify-between">
                    <a href="{{ route('branch-openings.index') }}" class="text-gray-600 hover:text-gray-800">
                        Cancel
                    </a>
                    <button type="submit" class="inline-flex items-center px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white font-semibold rounded-lg transition">
                        Continue to Step 2
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
