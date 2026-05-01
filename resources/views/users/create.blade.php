@extends('layouts.base')

@section('title', 'Create User')

@section('content')
<div class="card max-w-2xl">
    <div class="px-6 py-4 border-b border-[--color-border]"><h3 class="text-base font-semibold text-[--color-ink]">Create New User</h3></div>
    <div class="p-6">
        <form method="POST" action="{{ route('users.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Name</label>
                    <input type="text" name="name" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Email</label>
                    <input type="email" name="email" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Role</label>
                    <select name="role" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                        @foreach($roles ?? [] as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
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
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Password</label>
                    <input type="password" name="password" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-[--color-ink] mb-1.5">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="w-full px-4 py-2.5 text-sm bg-white border border-[--color-border] rounded-lg" required>
                </div>
            </div>
            <div class="mt-6 flex gap-3">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-[#0a0a0a] text-white text-sm font-medium rounded-lg hover:bg-[#262626]">Create User</button>
                <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection