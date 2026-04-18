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
    </div>
</div>
@endsection