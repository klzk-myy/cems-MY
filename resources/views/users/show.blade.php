@extends('layouts.base')

@section('title', 'User Details')

@section('content')
<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border] flex justify-between items-center">
        <h3 class="text-base font-semibold text-[--color-ink]">{{ $user->name ?? 'N/A' }}</h3>
        <div class="flex gap-2">
            <a href="{{ route('users.edit', $user->id ?? 0) }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Edit</a>
            <a href="{{ route('users.index') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-white border border-[--color-border] hover:bg-[--color-canvas-subtle]">Back</a>
        </div>
    </div>
    <div class="p-6">
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
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-medium rounded {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
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