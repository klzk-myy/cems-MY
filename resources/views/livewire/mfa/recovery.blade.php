@extends('layouts.base')

<div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200 bg-yellow-50">
            <h2 class="text-lg font-semibold text-gray-900">Save Your Recovery Codes</h2>
        </div>

        <div class="p-6">
            <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                <p class="text-red-800 text-sm font-medium">
                    Important: Save these codes in a secure place. You will need them if you lose access to your authenticator app.
                </p>
            </div>

            <div class="bg-gray-50 rounded-lg p-4 mb-6">
                <div class="grid grid-cols-2 gap-4">
                    @foreach($recoveryCodes as $code)
                        <div class="font-mono text-lg text-center text-gray-800 bg-white border border-gray-200 rounded px-3 py-2">
                            {{ $code }}
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700">
                    I've Saved My Codes - Continue to Dashboard
                </a>
            </div>
        </div>
    </div>
</div>