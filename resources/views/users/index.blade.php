@extends('layouts.base')

@section('title', 'Users')

@section('header-title')
<div>
    <h1 class="text-2xl font-semibold text-[--color-ink]">Users</h1>
    <p class="text-sm text-[--color-ink-muted]">Manage system users</p>
</div>
@endsection

@section('header-actions')
<a href="/users/create" class="btn btn-primary">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
    </svg>
    Add User
</a>
@endsection

@section('content')
<div class="card">
    <div class="table-container">
        <table class="table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users ?? [] as $user)
                <tr>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 bg-[--color-canvas-subtle] rounded-lg flex items-center justify-center font-semibold">
                                {{ substr($user->username, 0, 1) }}
                            </div>
                            <div>
                                <p class="font-medium">{{ $user->full_name ?? $user->username }}</p>
                                <p class="text-xs text-[--color-ink-muted]">{{ $user->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td class="font-mono">{{ $user->username }}</td>
                    <td>
                        <span class="badge badge-info">{{ $user->role->label() ?? 'Unknown' }}</span>
                    </td>
                    <td>{{ $user->branch->name ?? 'N/A' }}</td>
                    <td>
                        @if($user->is_active)
                            <span class="badge badge-success">Active</span>
                        @else
                            <span class="badge badge-default">Inactive</span>
                        @endif
                    </td>
                    <td>
                        <div class="table-actions">
                            <a href="/users/{{ $user->id }}" class="btn btn-ghost btn-icon" title="View">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </a>
                            <a href="/users/{{ $user->id }}/edit" class="btn btn-ghost btn-icon" title="Edit">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </a>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6">
                        <div class="empty-state py-12">
                            <p class="empty-state-title">No users found</p>
                            <p class="empty-state-description">Add your first user to get started</p>
                        </div>
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
