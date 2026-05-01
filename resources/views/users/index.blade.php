@extends('layouts.base')

@section('title', 'Users - CEMS-MY')

@section('content')
<div class="mb-6">
    <div>
        <h1 class="text-2xl font-semibold text-[--color-ink]">Users</h1>
        <p class="text-sm text-[--color-ink-muted] mt-1">Manage system users and roles</p>
    </div>
    @role('admin')
    <a href="{{ route('users.create') }}" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium rounded-lg bg-[#0a0a0a] text-white hover:bg-[#262626]">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
        </svg>
        Add User
    </a>
    @endrole
</div>

<div class="card">
    <div class="px-6 py-4 border-b border-[--color-border]">
        <h3 class="text-base font-semibold text-[--color-ink]">All Users</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Branch</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr class="border-b border-[--color-border] hover:bg-[--color-canvas-subtle]/50">
                    <td class="text-[--color-ink] font-medium">{{ $user->name }}</td>
                    <td class="text-[--color-ink-muted]">{{ $user->email }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded
                            @if($user->role->isAdmin()) bg-red-100 text-red-700
                            @elseif($user->role->isManager()) bg-purple-100 text-purple-700
                            @elseif($user->role->isComplianceOfficer()) bg-orange-100 text-orange-700
                            @else bg-blue-100 text-blue-700
                            @endif">
                            {{ $user->role->label() }}
                        </span>
                    </td>
                    <td class="text-[--color-ink-muted]">{{ $user->branch->name ?? 'N/A' }}</td>
                    <td class="text-[--color-ink]">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded {{ $user->is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-700' }}">
                            {{ $user->is_active ? 'Active' : 'Inactive' }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-8 text-center text-[--color-ink-muted]">No users found</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($users->hasPages())
    <div class="px-6 py-4 border-t border-[--color-border]">
        {{ $users->links() }}
    </div>
    @endif
</div>
@endsection