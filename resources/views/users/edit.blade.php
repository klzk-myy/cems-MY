@extends('layouts.base')

@section('title', 'Edit User')

@section('content')
<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Edit User</h3></div>
    <div class="p-6">
        <form method="POST" action="{{ route('users.update', $user->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Name</label>
                    <input type="text" name="name" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $user->name ?? '' }}" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" value="{{ $user->email ?? '' }}" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Role</label>
                    <select name="role" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        @foreach($roles ?? [] as $value => $label)
                        <option value="{{ $value }}" @if($user->role->value === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Branch</label>
                    <select name="branch_id" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg">
                        <option value="">No Branch</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Password (leave blank to keep)</label>
                    <input type="password" name="password" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Active</label>
                    <input type="checkbox" name="is_active" value="1" @if($user->is_active ?? false) checked @endif>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Update User</button>
                <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>

        {{-- Reset Password Section --}}
        @role('admin')
        <div class="mt-8 pt-6 border-t border-gray-200">
            <h4 class="text-lg font-semibold text-[--color-ink] mb-4">Security</h4>
            <form method="POST" action="{{ route('users.reset-password', $user->id) }}" x-data="{ confirm: '', password: '' }">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-[--color-ink] mb-1.5">New Password</label>
                        <input type="password" name="password" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" x-model="password" required minlength="12">
                        <p class="text-xs text-[--color-ink-muted] mt-1">Min 12 chars, mixed case, number, special char</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" x-model="confirm" required minlength="12">
                    </div>
                </div>
                @if ($errors->has('password'))
                    <p class="text-sm text-red-600 mt-2">{{ $errors->first('password') }}</p>
                @endif
                <div class="mt-4">
                    <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-red-600 text-white hover:bg-red-700"
                        onclick="return confirm('Reset password for {{ $user->username }}? This cannot be undone.')"
                        :disabled="password !== confirm || password.length < 12">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
        @endrole
    </div>
</div>
@endsection