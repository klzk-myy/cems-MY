@extends('layouts.base')

@section('title', 'Create User')

@section('content')
<div class="card">
    <div class="card-header"><h3 class="card-title">Create New User</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select name="role" class="form-input" required>
                        @foreach($roles ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
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
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-input" required>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="btn btn-primary">Create User</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection