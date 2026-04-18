@extends('layouts.base')

@section('title', 'Edit User')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Edit User</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', $user->id ?? 0) }}">
            @csrf
            @method('PUT')
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="{{ $user->name ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" value="{{ $user->email ?? '' }}" required>
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input" required>
                        @foreach($roles ?? [] as $value => $label)
                        <option value="{{ $value }}" @if($user->role->value === $value) selected @endif>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Branch</label>
                    <select name="branch_id" class="form-input">
                        <option value="">No Branch</option>
                    </select>
                </div>
                <div>
                    <label class="form-label">Password (leave blank to keep)</label>
                    <input type="password" name="password" class="form-input">
                </div>
                <div>
                    <label class="form-label">Active</label>
                    <input type="checkbox" name="is_active" value="1" @if($user->is_active ?? false) checked @endif>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Update User</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        {{-- Reset Password Section --}}
        <div class="mt-8 pt-6 border-t border-gray-200">
            <h4 class="text-lg font-semibold text-[--color-ink] mb-4">Security</h4>
            <form method="POST" action="{{ route('users.reset-password', $user->id) }}" x-data="{ confirm: '', password: '' }">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-input" x-model="password" required minlength="12">
                        <p class="text-xs text-[--color-ink-muted] mt-1">Min 12 chars, mixed case, number, special char</p>
                    </div>
                    <div>
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="password_confirmation" class="form-input" x-model="confirm" required minlength="12">
                    </div>
                </div>
                @if ($errors->has('password'))
                    <p class="text-sm text-red-600 mt-2">{{ $errors->first('password') }}</p>
                @endif
                <div class="mt-4">
                    <button type="submit" class="btn btn-danger"
                        onclick="return confirm('Reset password for {{ $user->username }}? This cannot be undone.')"
                        :disabled="password !== confirm || password.length < 12">
                        Reset Password
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection