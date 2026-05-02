@extends('layouts.base')

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Create New User</h2>
        </div>

        <form wire:submit="save" class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Username</label>
                    <input type="text" wire:model="username" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('username') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input type="email" wire:model="email" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('email') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input type="password" wire:model="password" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                    <span class="text-xs text-gray-500">Min 12 chars with uppercase, lowercase, number, special char</span>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password</label>
                    <input type="password" wire:model="passwordConfirmation" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    @error('password_confirmation') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Role</label>
                    <select wire:model="role" class="w-full border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <option value="">Select role...</option>
                        @foreach($roles as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('role') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-4 border-t border-gray-200">
                <a href="{{ route('users.index') }}" class="px-4 py-2 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    Create User
                </button>
            </div>
        </form>
    </div>
</div>