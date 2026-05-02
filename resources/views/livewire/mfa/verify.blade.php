@extends('layouts.base')

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Verify Your Identity</h2>
        </div>

        <div class="p-6">
            <p class="text-gray-600 mb-6">Enter the 6-digit code from your authenticator app.</p>

            @if($error)
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    {{ $error }}
                </div>
            @endif

            <form wire:submit="verify" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Verification Code</label>
                    <input type="text" wire:model="code" maxlength="6" placeholder="000000" class="w-full text-center text-2xl tracking-widest border-gray-300 rounded-lg border focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" autocomplete="one-time-code">
                    @error('code') <span class="text-red-500 text-sm">{{ $message }}</span> @enderror
                </div>

                <div class="flex items-center">
                    <input type="checkbox" wire:model="remember" id="remember" class="w-4 h-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                    <label for="remember" class="ml-2 text-sm text-gray-700">Trust this device for 30 days</label>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 text-gray-700 text-sm font-medium rounded-lg border border-gray-300 hover:bg-gray-50">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                        Verify
                    </button>
                </div>
            </form>

            <div class="mt-6 pt-6 border-t border-gray-200">
                <a href="{{ route('mfa.recovery') }}" class="text-sm text-indigo-600 hover:text-indigo-900">
                    Lost access to your authenticator? Use a recovery code
                </a>
            </div>
        </div>
    </div>
</div>