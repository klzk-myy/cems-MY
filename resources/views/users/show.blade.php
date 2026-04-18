@extends('layouts.base')

@section('title', 'User Details')

@section('content')
<div class="card">
    <div class="card-header flex justify-between items-center">
        <h3 class="card-title">{{ $user->name ?? 'N/A' }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('users.edit', $user->id ?? 0) }}" class="btn btn-secondary">Edit</a>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Back</a>
        </div>
    </div>
    <div class="card-body">
        <dl class="grid grid-cols-2 gap-6">
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Email</dt>
                <dd class="font-medium">{{ $user->email ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Role</dt>
                <dd>
                    @if(isset($user->role))
                        @statuslabel($user->role)
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Branch</dt>
                <dd>{{ $user->branch_name ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Status</dt>
                <dd>
                    @if(isset($user->is_active))
                        <span class="badge {{ $user->is_active ? 'badge-success' : 'badge-danger' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    @else
                        <span class="text-[--color-ink-muted]">N/A</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Created At</dt>
                <dd class="font-mono">{{ $user->created_at ?? 'N/A' }}</dd>
            </div>
            <div>
                <dt class="text-sm text-[--color-ink-muted]">Last Login</dt>
                <dd class="font-mono">{{ $user->last_login_at ?? 'N/A' }}</dd>
            </div>
        </dl>
    </div>
</div>
@endsection